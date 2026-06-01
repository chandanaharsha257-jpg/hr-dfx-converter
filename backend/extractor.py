from dataclasses import dataclass, field
from typing import List, Tuple
import math
 
 
@dataclass
class PDFLine:
    x1: float
    y1: float
    x2: float
    y2: float
    layer: str = "LINES"
 
 
@dataclass
class PDFText:
    x: float
    y: float
    text: str
    font_size: float = 10.0
    layer: str = "TEXT"
 
 
@dataclass
class PDFPage:
    width: float
    height: float
    lines: List[PDFLine] = field(default_factory=list)
    texts: List[PDFText] = field(default_factory=list)
 
    @property
    def entity_count(self):
        return len(self.lines) + len(self.texts)
 
 
def _auto_diagram_crop(page_width: float, page_height: float) -> Tuple[float, float, float, float]:
    """
    Returns (xmin, xmax, ymin, ymax) in PDF coordinates (Y from top).
 
    Exact values from vector analysis of the AutoCAD A1 drawing:
      - Right table left border: x = 820.4 pts → cut at 818
      - Bottom title block top:  y = 748.1 pts → cut at 746
    """
    xmin = 0.0
    xmax = 818.0
    ymin = 0.0
    ymax = 746.0
    return xmin, xmax, ymin, ymax
 
 
def extract_diagram_image(pdf_path: str, dpi: int = 300):
    """
    Render ONLY the diagram area of the PDF as a PNG.
    Returns (png_bytes, img_width_px, img_height_px,
             (xmin,xmax,ymin,ymax) in PDF pts, page_width, page_height)
    """
    import fitz
 
    doc  = fitz.open(pdf_path)
    page = doc[0]
    pw, ph = float(page.rect.width), float(page.rect.height)
 
    xmin, xmax, ymin, ymax = _auto_diagram_crop(pw, ph)
 
    clip  = fitz.Rect(xmin, ymin, xmax, ymax)
    scale = dpi / 72.0
    mat   = fitz.Matrix(scale, scale)
 
    pix       = page.get_pixmap(matrix=mat, clip=clip, alpha=False)
    png_bytes = pix.tobytes("png")
    doc.close()
 
    return png_bytes, pix.width, pix.height, (xmin, xmax, ymin, ymax), pw, ph
 
 
def _vectorize_image_raster(pdf_path: str, page_data: PDFPage):
    try:
        import fitz, cv2, numpy as np
    except ImportError:
        raise RuntimeError("pip install pymupdf opencv-python numpy")
 
    doc  = fitz.open(pdf_path)
    page = doc[0]
    pix  = page.get_pixmap(matrix=fitz.Matrix(2, 2), alpha=False)
    img  = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, pix.n)
    gray = cv2.cvtColor(img, cv2.COLOR_RGB2GRAY)
    img_h, img_w = gray.shape
    scale_x = page_data.width  / img_w
    scale_y = page_data.height / img_h
 
    _, thresh  = cv2.threshold(gray, 210, 255, cv2.THRESH_BINARY_INV)
    h_kernel   = cv2.getStructuringElement(cv2.MORPH_RECT, (35, 1))
    v_kernel   = cv2.getStructuringElement(cv2.MORPH_RECT, (1, 35))
    detect_h   = cv2.morphologyEx(thresh, cv2.MORPH_OPEN, h_kernel, iterations=2)
    detect_v   = cv2.morphologyEx(thresh, cv2.MORPH_OPEN, v_kernel, iterations=2)
    combined   = cv2.addWeighted(detect_h, 0.5, detect_v, 0.5, 0.0)
 
    lines = cv2.HoughLinesP(combined, 1, math.pi/180, 70, minLineLength=40, maxLineGap=8)
    if lines is not None:
        for line in lines:
            x1, y1, x2, y2 = line[0]
            cx1, cy1 = x1*scale_x, y1*scale_y
            cx2, cy2 = x2*scale_x, y2*scale_y
            if abs(cx1-cx2) < 4.0: cx2 = cx1
            if abs(cy1-cy2) < 4.0: cy2 = cy1
            page_data.lines.append(PDFLine(cx1, cy1, cx2, cy2, layer="TRACED_WALLS"))
 
 
def extract_pdf(pdf_path: str, diagram_only: bool = False) -> PDFPage:
    try:
        import fitz
    except ImportError:
        raise RuntimeError("pip install pymupdf")
 
    doc  = fitz.open(pdf_path)
    if len(doc) == 0:
        raise RuntimeError("PDF has no pages.")
 
    page = doc[0]
    rect = page.rect
    page_data = PDFPage(width=float(rect.width), height=float(rect.height))
 
    if diagram_only:
        xmin, xmax, ymin_pdf, ymax_pdf = _auto_diagram_crop(rect.width, rect.height)
    else:
        xmin, xmax, ymin_pdf, ymax_pdf = 0.0, rect.width, 0.0, rect.height
 
    def _in_crop(ax, ay, bx, by):
        return (xmin <= ax <= xmax and xmin <= bx <= xmax and
                ymin_pdf <= ay <= ymax_pdf and ymin_pdf <= by <= ymax_pdf)
 
    for drawing in page.get_drawings():
        # ── Filter 1: skip hatch/fill-only paths (no stroke color) ──────────
        # These produce diagonal hatch lines and solid fills — unwanted in DXF.
        stroke_color = drawing.get("color")       # None = no outline stroke
        fill_color   = drawing.get("fill")        # None = no fill
        dashes       = drawing.get("dashes", [])  # non-empty = dashed linetype

        # Skip: pure fills with no stroke (solid triangles, hatch fills)
        if fill_color is not None and stroke_color is None:
            continue

        # Skip: very short dashed patterns (hatch lines are typically short + dashed)
        line_items = [it for it in drawing["items"] if it[0] == "l"]
        if line_items:
            lengths = []
            for it in line_items:
                dx = it[2].x - it[1].x
                dy = it[2].y - it[1].y
                lengths.append((dx*dx + dy*dy) ** 0.5)
            avg_len = sum(lengths) / len(lengths)
            # Hatch lines are short (< 30 pts) and there are many of them
            if avg_len < 30 and len(line_items) > 20:
                continue

        for item in drawing["items"]:
            if item[0] == "l":
                p1, p2 = item[1], item[2]
                x1,y1,x2,y2 = float(p1.x),float(p1.y),float(p2.x),float(p2.y)
                if not _in_crop(x1,y1,x2,y2): continue
                page_data.lines.append(PDFLine(x1=x1,y1=y1,x2=x2,y2=y2,layer="PDF_LINES"))

            elif item[0] == "re":
                r = item[1]
                for x1,y1,x2,y2 in [(r.x0,r.y0,r.x1,r.y0),(r.x1,r.y0,r.x1,r.y1),
                                     (r.x1,r.y1,r.x0,r.y1),(r.x0,r.y1,r.x0,r.y0)]:
                    if not _in_crop(x1,y1,x2,y2): continue
                    page_data.lines.append(PDFLine(x1,y1,x2,y2,layer="PDF_LINES"))

            elif item[0] == "c":
                p0,p1,p2,p3 = item[1],item[2],item[3],item[4]
                xs = [p0.x,p1.x,p2.x,p3.x]; ys = [p0.y,p1.y,p2.y,p3.y]
                if diagram_only and not (all(xmin<=x<=xmax for x in xs) and
                                         all(ymin_pdf<=y<=ymax_pdf for y in ys)):
                    continue
                pts = []
                for i in range(11):
                    t=i/10; mt=1-t
                    x=mt**3*p0.x+3*mt**2*t*p1.x+3*mt*t**2*p2.x+t**3*p3.x
                    y=mt**3*p0.y+3*mt**2*t*p1.y+3*mt*t**2*p2.y+t**3*p3.y
                    pts.append((x,y))
                for i in range(len(pts)-1):
                    page_data.lines.append(PDFLine(pts[i][0],pts[i][1],pts[i+1][0],pts[i+1][1],layer="PDF_LINES"))
 
    for block in page.get_text("dict").get("blocks", []):
        if "lines" not in block: continue
        for line in block["lines"]:
            for span in line["spans"]:
                text = span["text"].strip()
                if not text: continue
                x, y = float(span["bbox"][0]), float(span["bbox"][1])
                if diagram_only and not (xmin<=x<=xmax and ymin_pdf<=y<=ymax_pdf): continue
                page_data.texts.append(PDFText(x=x,y=y,text=text,
                    font_size=float(span["size"]),layer="PDF_TEXT"))
 
    if page_data.entity_count < 8:
        page_data.lines.clear(); page_data.texts.clear()
        _vectorize_image_raster(pdf_path, page_data)
 
    return page_data
 










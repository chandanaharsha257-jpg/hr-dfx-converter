import os
import uuid
import ezdxf
import fitz

from fastapi import FastAPI, UploadFile, File, HTTPException
from fastapi.responses import FileResponse
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

app = FastAPI(title="Cloud CAD Engine Backend Pipeline", version="2.5.0")
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_credentials=True,
                   allow_methods=["*"], allow_headers=["*"])

UPLOAD_DIR = "uploads"
OUTPUT_DIR = "outputs"
os.makedirs(UPLOAD_DIR, exist_ok=True)
os.makedirs(OUTPUT_DIR, exist_ok=True)


class CanvasLine(BaseModel):
    x1: float; y1: float; x2: float; y2: float

class CanvasText(BaseModel):
    x: float; y: float; text: str; size: float

class ExportPayload(BaseModel):
    lines: list[CanvasLine]
    texts: list[CanvasText]


def build_2d_dxf(lines_data, texts_data, page_height, x_off, y_off, diag_w, diag_h, out_path):
    """
    Build a strict 2D flat DXF — all Z=0, top-down view, single DIAGRAM layer.
    Patches VPORT directly in the saved file to guarantee 2D view in AutoCAD.
    """
    doc = ezdxf.new(dxfversion="R2010")

    # Headers that force 2D
    doc.header["$INSUNITS"]    = 4
    doc.header["$MEASUREMENT"] = 1
    doc.header["$EXTMIN"]      = (0, 0, 0)
    doc.header["$EXTMAX"]      = (diag_w, diag_h, 0)
    doc.header["$LIMMIN"]      = (0, 0)
    doc.header["$LIMMAX"]      = (diag_w, diag_h)
    doc.header["$ELEVATION"]   = 0
    doc.header["$THICKNESS"]   = 0
    doc.header["$WORLDVIEW"]   = 1
    doc.header["$TILEMODE"]    = 1
    doc.header["$UCSORG"]      = (0, 0, 0)
    doc.header["$UCSXDIR"]     = (1, 0, 0)
    doc.header["$UCSYDIR"]     = (0, 1, 0)

    # Load CONTINUOUS linetype to guarantee solid lines (no dashes)
    doc.linetypes.get("Continuous")  # always exists in R2010

    doc.layers.new(name="DIAGRAM",  dxfattribs={
        "color": 4, "lineweight": 25, "linetype": "Continuous"
    })
    doc.layers.new(name="PDF_TEXT", dxfattribs={
        "color": 2,  "linetype": "Continuous"
    })
    msp = doc.modelspace()

    # All geometry strictly at Z=0, CONTINUOUS linetype, no thickness
    for line in lines_data:
        x1 = round(line.x1 - x_off, 4)
        y1 = round((page_height - line.y1) - y_off, 4)
        x2 = round(line.x2 - x_off, 4)
        y2 = round((page_height - line.y2) - y_off, 4)
        msp.add_line((x1, y1, 0), (x2, y2, 0), dxfattribs={
            "layer":     "DIAGRAM",
            "linetype":  "Continuous",
            "thickness": 0,
        })

    for text in texts_data:
        if text.text.strip():
            tx = round(text.x - x_off, 4)
            ty = round((page_height - text.y) - y_off, 4)
            # Use TEXT (not MTEXT) — simpler, always flat 2D
            msp.add_text(text.text, dxfattribs={
                "layer":      "PDF_TEXT",
                "insert":     (tx, ty, 0),
                "height":     max(text.font_size * 0.35, 1.5),
                "thickness":  0,
            })

    doc.saveas(out_path)

    # ── Patch VPORT in saved file to force top-down 2D view ──────────────
    # AutoCAD reads the *Active VPORT to set the initial view direction.
    # We inject view direction (0,0,1) = top-down, view target = origin.
    cx = diag_w / 2
    cy = diag_h / 2

    with open(out_path, "r") as f:
        content = f.read()

    # Inject after the *Active VPORT name line
    vport_patch = (
        f"  0\nVPORT\n  5\n2B\n100\nAcDbSymbolTableRecord\n"
        f"100\nAcDbViewportTableRecord\n  2\n*Active\n 70\n     0\n"
        f" 10\n0.0\n 20\n0.0\n"                           # lower-left corner
        f" 11\n1.0\n 21\n1.0\n"                           # upper-right corner
        f" 12\n{cx:.4f}\n 22\n{cy:.4f}\n"                # center
        f" 13\n0.0\n 23\n0.0\n"                           # snap base
        f" 14\n10.0\n 24\n10.0\n"                         # snap spacing
        f" 15\n10.0\n 25\n10.0\n"                         # grid spacing
        f" 16\n0.0\n 26\n0.0\n 36\n1.0\n"                # view direction = top-down (0,0,1)
        f" 17\n0.0\n 27\n0.0\n 37\n0.0\n"                # view target = origin
        f" 40\n{diag_h * 1.05:.4f}\n"                    # view height
        f" 41\n{diag_w / diag_h:.4f}\n"                  # aspect ratio
        f" 42\n50.0\n 43\n0.0\n 44\n0.0\n"               # lens/front/back clip
        f" 50\n0.0\n 51\n0.0\n 71\n     0\n"             # snap/grid/circle sides
        f" 72\n   100\n 73\n     1\n 74\n     1\n"
        f" 75\n     0\n 76\n     0\n 77\n     0\n 78\n     0\n"
    )

    # Replace any existing VPORT section content
    import re
    # Remove old VPORT entries and insert our clean one
    content = re.sub(
        r'  0\r?\nVPORT\r?\n.*?(?=  0\r?\n(?:VPORT|ENDTAB))',
        '',
        content,
        flags=re.DOTALL
    )
    content = content.replace(
        "TABLE\n  2\nVPORT\n",
        "TABLE\n  2\nVPORT\n" + vport_patch
    )

    with open(out_path, "w") as f:
        f.write(content)


@app.get("/")
def read_root():
    return {"status": "running", "message": "PDF to DXF Converter API is live"}


@app.post("/parse-to-json")
async def parse_to_json(file: UploadFile = File(...)):
    if not file.filename.lower().endswith(".pdf"):
        raise HTTPException(400, "Only PDF files allowed")
    job_id   = str(uuid.uuid4())
    temp_pdf = os.path.join(UPLOAD_DIR, f"{job_id}.pdf")
    with open(temp_pdf, "wb") as f:
        f.write(await file.read())
    try:
        from extractor import extract_pdf
        page_data = extract_pdf(temp_pdf, diagram_only=False)
        if os.path.exists(temp_pdf): os.remove(temp_pdf)
        return {
            "status": "success",
            "width": float(page_data.width), "height": float(page_data.height),
            "lines": [{"x1":l.x1,"y1":l.y1,"x2":l.x2,"y2":l.y2,"layer":l.layer} for l in page_data.lines],
            "texts": [{"x":t.x,"y":t.y,"text":t.text,"size":t.font_size} for t in page_data.texts],
        }
    except Exception as e:
        if os.path.exists(temp_pdf): os.remove(temp_pdf)
        raise HTTPException(500, str(e))


@app.post("/export-canvas-to-dxf")
async def export_canvas_to_dxf(payload: ExportPayload):
    try:
        doc = ezdxf.new(dxfversion="R2010")
        doc.header["$INSUNITS"] = 4
        msp = doc.modelspace()
        doc.layers.new(name="EDITED_LINES", dxfattribs={"color": 4})
        doc.layers.new(name="EDITED_TEXT",  dxfattribs={"color": 2})
        for line in payload.lines:
            msp.add_line((line.x1, -line.y1, 0), (line.x2, -line.y2, 0),
                         dxfattribs={"layer": "EDITED_LINES"})
        for text in payload.texts:
            if text.text.strip():
                msp.add_mtext(text.text, dxfattribs={
                    "layer": "EDITED_TEXT", "insert": (text.x, -text.y, 0),
                    "char_height": max(text.size * 0.35, 2.0),
                })
        fname = f"edited_{uuid.uuid4()}.dxf"
        path  = os.path.join(OUTPUT_DIR, fname)
        doc.saveas(path)
        return {"status": "success",
                "download_url": f"https://lavish-creativity-production-c53a.up.railway.app/download-export/{fname}"}
    except Exception as e:
        raise HTTPException(500, str(e))


@app.post("/pdf-to-dxf")
async def pdf_to_dxf(file: UploadFile = File(...)):
    if not file.filename.lower().endswith(".pdf"):
        raise HTTPException(400, "Only PDF allowed")
    job_id   = str(uuid.uuid4())
    pdf_path = os.path.join(UPLOAD_DIR, f"{job_id}.pdf")
    dxf_name = f"converted_{job_id}.dxf"
    dxf_path = os.path.join(OUTPUT_DIR, dxf_name)
    with open(pdf_path, "wb") as f:
        f.write(await file.read())
    try:
        from extractor import extract_pdf, _auto_diagram_crop
        _fd = fitz.open(pdf_path)
        page_height = float(_fd[0].rect.height)
        _fd.close()
        page_data = extract_pdf(pdf_path, diagram_only=True)
        xmin, xmax, ymin_pdf, ymax_pdf = _auto_diagram_crop(page_data.width, page_data.height)
        x_off  = xmin
        y_off  = page_height - ymax_pdf
        diag_w = xmax - xmin
        diag_h = ymax_pdf - ymin_pdf

        build_2d_dxf(page_data.lines, page_data.texts,
                     page_height, x_off, y_off, diag_w, diag_h, dxf_path)

        if os.path.exists(pdf_path): os.remove(pdf_path)
        return {"status": "success",
                "download_url": f"https://lavish-creativity-production-c53a.up.railway.app/download-export/{dxf_name}"}
    except Exception as e:
        if os.path.exists(pdf_path): os.remove(pdf_path)
        raise HTTPException(500, str(e))


@app.post("/pdf-to-dxf-full")
async def pdf_to_dxf_full(file: UploadFile = File(...)):
    """Convert full PDF page to DXF — no cropping, includes tables and title block."""
    if not file.filename.lower().endswith(".pdf"):
        raise HTTPException(400, "Only PDF allowed")
    job_id   = str(uuid.uuid4())
    pdf_path = os.path.join(UPLOAD_DIR, f"{job_id}.pdf")
    dxf_name = f"fullpage_{job_id}.dxf"
    dxf_path = os.path.join(OUTPUT_DIR, dxf_name)
    with open(pdf_path, "wb") as f:
        f.write(await file.read())
    try:
        from extractor import extract_pdf
        _fd = fitz.open(pdf_path)
        page_height = float(_fd[0].rect.height)
        page_width  = float(_fd[0].rect.width)
        _fd.close()

        # diagram_only=False → extract everything, no crop
        page_data = extract_pdf(pdf_path, diagram_only=False)

        x_off  = 0.0
        y_off  = 0.0
        diag_w = page_width
        diag_h = page_height

        build_2d_dxf(page_data.lines, page_data.texts,
                     page_height, x_off, y_off, diag_w, diag_h, dxf_path)

        if os.path.exists(pdf_path): os.remove(pdf_path)
        return {"status": "success",
                "download_url": f"https://lavish-creativity-production-c53a.up.railway.app/download-export/{dxf_name}"}
    except Exception as e:
        if os.path.exists(pdf_path): os.remove(pdf_path)
        raise HTTPException(500, str(e))


@app.post("/pdf-to-image")
async def pdf_to_image(file: UploadFile = File(...)):
    if not file.filename.lower().endswith(".pdf"):
        raise HTTPException(400, "Only PDF allowed")
    job_id   = str(uuid.uuid4())
    pdf_path = os.path.join(UPLOAD_DIR, f"{job_id}.pdf")
    png_name = f"diagram_{job_id}.png"
    png_path = os.path.join(OUTPUT_DIR, png_name)
    with open(pdf_path, "wb") as f:
        f.write(await file.read())
    try:
        from extractor import _auto_diagram_crop
        doc  = fitz.open(pdf_path)
        page = doc[0]
        xmin, xmax, ymin, ymax = _auto_diagram_crop(page.rect.width, page.rect.height)
        pix  = page.get_pixmap(matrix=fitz.Matrix(3, 3),
                               clip=fitz.Rect(xmin, ymin, xmax, ymax), alpha=False)
        pix.save(png_path)
        doc.close()
        if os.path.exists(pdf_path): os.remove(pdf_path)
        return {"status": "success", "width_px": pix.width, "height_px": pix.height,
                "download_url": f"https://lavish-creativity-production-c53a.up.railway.app/download-export/{png_name}"}
    except Exception as e:
        if os.path.exists(pdf_path): os.remove(pdf_path)
        raise HTTPException(500, str(e))


@app.get("/download-export/{filename}")
def download_export(filename: str):
    path = os.path.join(OUTPUT_DIR, filename)
    if not os.path.exists(path):
        raise HTTPException(404, "File not found")
    media = "image/png" if filename.endswith(".png") else "application/octet-stream"
    return FileResponse(path, filename=filename, media_type=media)

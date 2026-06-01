"""
writer.py — Stage 4 & 5 of the PDF→DXF pipeline
Converts PDFPage entities → DXF file using ezdxf
"""

from extractor import PDFPage, PDFLine, PDFCircle, PDFArc, PDFText
from typing import Dict, Any

LAYERS = {
    "LINES":      7,   # White/Black
    "RECTANGLES": 3,   # Green
    "CIRCLES":    1,   # Red
    "ARCS":       4,   # Cyan
    "CURVES":     6,   # Magenta
    "TEXT":       2,   # Yellow
    "TITLE_BLOCK": 5,  # Blue
}

def write_dxf(page: PDFPage, output_path: str) -> Dict[str, Any]:
    try:
        import ezdxf
    except ImportError:
        raise RuntimeError("ezdxf not installed. Run: pip install ezdxf")

    # Native R2010 file declaration for reliable modification compatibility
    doc = ezdxf.new(dxfversion="R2010")
    doc.header["$INSUNITS"] = 4       # Millimeters
    doc.header["$MEASUREMENT"] = 1   # Metric base system

    msp = doc.modelspace()

    # Build Layers safely
    for layer_name, color in LAYERS.items():
        if layer_name not in doc.layers:
            doc.layers.add(layer_name, color=color)

    # ── Geometry Generation ──
    line_count = 0
    for entity in page.lines:
        msp.add_line(
            start=(entity.x1, entity.y1),
            end=(entity.x2, entity.y2),
            dxfattribs={"layer": entity.layer}
        )
        line_count += 1

    circle_count = 0
    for entity in page.circles:
        msp.add_circle(
            center=(entity.cx, entity.cy),
            radius=entity.radius,
            dxfattribs={"layer": entity.layer}
        )
        circle_count += 1

    arc_count = 0
    for entity in page.arcs:
        msp.add_arc(
            center=(entity.cx, entity.cy),
            radius=entity.radius,
            start_angle=entity.start_angle % 360,
            end_angle=entity.end_angle % 360,
            dxfattribs={"layer": entity.layer}
        )
        arc_count += 1

    # ── Text Object Generation ──
    text_count = 0
    for entity in page.texts:
        clean_text = entity.text.strip()
        if not clean_text:
            continue
            
        # Use simple TEXT entities to make double-clicking directly editable
        msp.add_text(
            text=clean_text,
            dxfattribs={
                "layer": entity.layer,
                "height": max(entity.font_size * 0.8, 1.5), 
                "insert": (entity.x, entity.y),
            }
        )
        text_count += 1

    # Finalize File output structure
    doc.saveas(output_path)

    return {
        "lines":   line_count,
        "circles": circle_count,
        "arcs":    arc_count,
        "texts":   text_count,
        "total":   line_count + circle_count + arc_count + text_count,
        "page_width_mm":  round(page.width * 0.3528, 2),
        "page_height_mm": round(page.height * 0.3528, 2),
    }
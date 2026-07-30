#!/usr/bin/env python3
import contextlib
import io
import json
import os
import sys

import fitz
from PIL import Image
import easyocr


def optimize_image(png_bytes: bytes, max_width: int) -> bytes:
    image = Image.open(io.BytesIO(png_bytes)).convert("L")
    width, height = image.size

    if width > max_width:
        ratio = max_width / float(width)
        image = image.resize((int(width * ratio), int(height * ratio)), Image.LANCZOS)

    output = io.BytesIO()
    image.save(output, format="PNG", optimize=True)
    return output.getvalue()


def main() -> int:
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "error": "PDF path requerido"}))
        return 1

    pdf_path = sys.argv[1]
    max_pages = int(sys.argv[2]) if len(sys.argv) > 2 else 3
    dpi = int(sys.argv[3]) if len(sys.argv) > 3 else 96
    max_width = int(sys.argv[4]) if len(sys.argv) > 4 else 1400

    if not os.path.exists(pdf_path):
        print(json.dumps({"ok": False, "error": "Archivo no existe"}))
        return 1

    tmp_dir = os.environ.get("TMPDIR") or os.path.expanduser("~/tmp")
    os.makedirs(tmp_dir, exist_ok=True)
    os.environ["TMPDIR"] = tmp_dir

    model_dir = os.environ.get("EASYOCR_MODULE_PATH") or os.path.expanduser("~/ocr_models")
    os.makedirs(model_dir, exist_ok=True)

    with contextlib.redirect_stdout(sys.stderr):
        reader = easyocr.Reader(
            ['es'],
            gpu=False,
            model_storage_directory=model_dir,
            user_network_directory=model_dir,
        )

    document = fitz.open(pdf_path)
    page_count = min(len(document), max_pages)
    pages = []

    for index in range(page_count):
        page = document.load_page(index)
        matrix = fitz.Matrix(dpi / 72.0, dpi / 72.0)
        pixmap = page.get_pixmap(matrix=matrix, alpha=False, colorspace=fitz.csGRAY)
        image_bytes = optimize_image(pixmap.tobytes("png"), max_width=max_width)
        pixmap = None

        with contextlib.redirect_stdout(sys.stderr):
            result = reader.readtext(
                image_bytes,
                detail=0,
                paragraph=True,
                canvas_size=1280,
                mag_ratio=1.0,
            )

        page_text = "\n".join([segment.strip() for segment in result if str(segment).strip()])
        pages.append({
            "page": index + 1,
            "text": page_text,
        })

    document.close()

    full_text = "\n\n".join([entry["text"] for entry in pages if entry["text"].strip()])
    print(json.dumps({
        "ok": True,
        "engine": "python_easyocr",
        "pages_processed": page_count,
        "text": full_text,
        "pages": pages,
        "dpi": dpi,
        "max_width": max_width,
    }, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(1)

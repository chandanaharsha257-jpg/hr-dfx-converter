# PDF to AutoCAD (DXF) Converter
## Step-by-Step Setup Guide

---

## Project Structure

```
pdf_to_dxf_project/
├── backend/                  ← Python (FastAPI) — does the conversion
│   ├── main.py               ← API server (upload endpoint, download endpoint)
│   ├── extractor.py          ← Stage 2-3: reads PDF, extracts lines/circles/text
│   ├── writer.py             ← Stage 4-5: writes DXF file (AutoCAD editable)
│   ├── requirements.txt      ← Python dependencies
│   ├── uploads/              ← incoming PDFs (auto-created)
│   └── outputs/              ← generated DXF files (auto-created)
│
└── frontend/                 ← PHP — user uploads PDF, downloads DXF
    ├── index.php             ← upload form (drag & drop)
    ├── download.php          ← proxies DXF file to browser
    └── config.php            ← set your API URL here
```

---

## Step 1 — Install Python (one time)

1. Download Python 3.10+ from https://python.org
2. During install: ✅ check "Add Python to PATH"
3. Open terminal (Command Prompt on Windows) and verify:
   ```
   python --version
   ```

---

## Step 2 — Install Python Dependencies

Open terminal inside the `backend/` folder:

```bash
cd pdf_to_dxf_project/backend
pip install -r requirements.txt
```

This installs:
- `fastapi` — web framework
- `uvicorn` — web server
- `pdfminer.six` — reads PDF content
- `ezdxf` — writes DXF files
- `python-multipart` — handles file uploads

---

## Step 3 — Start the Python API

Still inside `backend/`:

```bash
uvicorn main:app --reload --port 8000
```

You should see:
```
INFO:     Uvicorn running on http://127.0.0.1:8000 (Press CTRL+C to quit)
```

Test it: open http://localhost:8000 in browser → should show `{"status":"running"}`

---

## Step 4 — Set Up PHP Frontend

### Option A: XAMPP (easiest for beginners)
1. Download XAMPP from https://apachefriends.org
2. Install and start Apache
3. Copy the `frontend/` folder to: `C:\xampp\htdocs\pdf_converter\`
4. Open: http://localhost/pdf_converter/

### Option B: PHP built-in server (quick test)
```bash
cd pdf_to_dxf_project/frontend
php -S localhost:8080
```
Open: http://localhost:8080

---

## Step 5 — Configure the API URL

Open `frontend/config.php` and check:
```php
define('PYTHON_API_URL', 'http://localhost:8000');
```

- **Local development**: keep as `http://localhost:8000`
- **Production server**: change to your server's IP or domain

---

## Step 6 — Test the Full Flow

1. Open the PHP frontend in browser
2. Click or drag-drop your vector PDF
3. Click **CONVERT TO DXF**
4. Wait 2–10 seconds
5. See entity summary (lines, circles, text counts)
6. Click **DOWNLOAD DXF FILE**
7. Open the `.dxf` file in AutoCAD
8. All geometry and text are on separate layers → fully editable!

---

## AutoCAD Layers Created

| Layer       | Color   | Contains                      |
|-------------|---------|-------------------------------|
| LINES       | White   | Straight lines, paths         |
| RECTANGLES  | Green   | Rectangle outlines            |
| CIRCLES     | Red     | Full circles                  |
| ARCS        | Cyan    | Partial arcs/curves           |
| CURVES      | Magenta | Bezier fallback lines         |
| TEXT        | Yellow  | All text (MTEXT, editable)    |
| TITLE_BLOCK | Blue    | Converter watermark           |

---

## Common Issues

| Problem | Solution |
|---------|----------|
| "Cannot connect to conversion service" | Start the Python API: `uvicorn main:app --reload --port 8000` |
| "Only PDF files are accepted" | Make sure your file has .pdf extension |
| Empty DXF (0 entities) | PDF is raster (scanned image), not vector. Vector PDFs come from CAD, Word, Illustrator |
| PHP curl error | Enable `extension=curl` in php.ini |
| Port 8000 in use | Change port: `uvicorn main:app --port 8001` and update config.php |

---

## Production Deployment

```
PHP frontend  →  Apache/Nginx on shared hosting or VPS
Python API    →  Same VPS, run with: gunicorn -w 4 -k uvicorn.workers.UvicornWorker main:app
```

Use a reverse proxy (nginx) to route `/api/` to Python and `/` to PHP.
"# hr-dfx-converter" 

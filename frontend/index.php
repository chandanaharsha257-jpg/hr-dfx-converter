<!DOCTYPE html>
<html>
<head>
<title>HR DFX Converter</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#071225">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="HR DFX">
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="icon-192.png">
<style>
body{margin:0;background:#000;overflow:hidden;font-family:Arial}
.topbar{height:70px;background:#071225;display:flex;justify-content:space-between;align-items:center;padding:0 25px}
.logo{color:#00d9ff;font-size:38px;font-weight:bold}
button{background:#00d9ff;border:0;border-radius:10px;padding:12px 20px;font-weight:bold;font-size:16px;cursor:pointer}
.actions{display:flex;gap:12px}
.tools{position:absolute;top:90px;left:12px;display:flex;flex-direction:column;gap:10px;z-index:10}
.tools button{width:58px;height:58px;padding:0;font-size:28px}
.active{background:yellow!important}
canvas{background:#000;display:block;cursor:crosshair}

/* Loading overlay */
#loadingOverlay{
    display:none;
    position:fixed;top:0;left:0;width:100%;height:100%;
    background:rgba(0,0,0,0.75);
    z-index:999;
    align-items:center;justify-content:center;flex-direction:column;gap:16px;
}
#loadingOverlay.show{display:flex;}
#loadingOverlay p{color:#00d9ff;font-size:22px;font-weight:bold;}
.spinner{
    width:52px;height:52px;
    border:6px solid #071225;
    border-top:6px solid #00d9ff;
    border-radius:50%;
    animation:spin .8s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}

/* Status bar */
#statusBar{
    position:fixed;bottom:0;left:0;width:100%;
    background:var(--bar-bg);color:var(--accent);
    font-size:13px;padding:5px 15px;box-sizing:border-box;
    z-index:100;
}

/* ── Theme variables ───────────────────────────────────────────── */
:root{
    --body-bg:#000; --topbar-bg:#071225; --accent:#00d9ff;
    --btn-bg:#00d9ff; --btn-color:#000; --bar-bg:#071225;
    --canvas-bg:#000; --logo-color:#00d9ff;
}
body[data-theme="white"]{
    --body-bg:#f0f0f0; --topbar-bg:#dce8f5; --accent:#005a8e;
    --btn-bg:#005a8e;  --btn-color:#fff;    --bar-bg:#dce8f5;
    --canvas-bg:#fff;  --logo-color:#005a8e;
}
body[data-theme="blue"]{
    --body-bg:#0a1628; --topbar-bg:#0d2040; --accent:#4db8ff;
    --btn-bg:#1565c0;  --btn-color:#fff;    --bar-bg:#0d2040;
    --canvas-bg:#0a1628; --logo-color:#4db8ff;
}
body[data-theme="system"]{
    --body-bg:#1e1e1e; --topbar-bg:#252526; --accent:#569cd6;
    --btn-bg:#3a3d41;  --btn-color:#d4d4d4; --bar-bg:#252526;
    --canvas-bg:#1e1e1e; --logo-color:#569cd6;
}

/* Apply theme vars to existing elements */
body          { background:var(--body-bg)!important; }
.topbar       { background:var(--topbar-bg)!important; }
.logo         { color:var(--logo-color)!important; }
button        { background:var(--btn-bg)!important; color:var(--btn-color)!important; }
canvas        { background:var(--canvas-bg)!important; }
#loadingOverlay { background:rgba(0,0,0,0.75); }
#loadingOverlay p { color:var(--accent)!important; }
.spinner      { border-color:var(--topbar-bg)!important; border-top-color:var(--accent)!important; }

/* Theme picker pill */
#themePicker{
    display:flex;align-items:center;gap:6px;
    background:rgba(255,255,255,0.08);
    border-radius:20px;padding:4px 10px;
}
#themePicker span{ color:var(--accent);font-size:13px;font-weight:bold;margin-right:4px; }
.theme-btn{
    width:26px;height:26px;border-radius:50%;border:2px solid transparent;
    cursor:pointer;padding:0!important;font-size:11px!important;
    min-width:unset!important;transition:border .2s;
}
.theme-btn.active-theme{ border-color:var(--accent)!important; }
#themeBlack { background:#000!important;color:#00d9ff!important; }
#themeWhite { background:#f0f0f0!important;color:#005a8e!important; }
#themeBlue  { background:#1565c0!important;color:#4db8ff!important; }
#themeSystem{ background:#3a3d41!important;color:#d4d4d4!important; }
</style>
</head>

<body>

<!-- Loading overlay -->
<div id="loadingOverlay">
    <div class="spinner"></div>
    <p id="loadingMsg">Processing...</p>
</div>

<!-- Status bar -->
<div id="statusBar">Ready. Open a PDF, then choose ⚡ Diagram Only or 📄 Full Page to DXF.</div>

<div class="topbar">
    <div class="logo">HR DFX Converter</div>
    <div class="actions">
        <button onclick="document.getElementById('pdfInput').click()">📂 Open PDF</button>
        <button onclick="directPDFtoDXF()">⚡ Diagram Only to DXF</button>
        <button onclick="fullPageToDXF()">📄 Full Page to DXF</button>
        <button onclick="exportEditedDXF()">💾 Export Edited DXF</button>
    </div>
    <div id="themePicker">
        <span>🎨</span>
        <button class="theme-btn active-theme" id="themeBlack" title="Black" onclick="setTheme('black','themeBlack')">B</button>
        <button class="theme-btn" id="themeWhite" title="White" onclick="setTheme('white','themeWhite')">W</button>
        <button class="theme-btn" id="themeBlue"  title="Blue"  onclick="setTheme('blue','themeBlue')">B</button>
        <button class="theme-btn" id="themeSystem" title="System Default" onclick="setTheme('system','themeSystem')">S</button>
    </div>
</div>

<div class="tools">
    <button id="selectBtn" onclick="setTool('select')">↖</button>
    <button id="lineBtn" onclick="setTool('line')">╱</button>
    <button id="textBtn" onclick="setTool('text')">T</button>
    <button onclick="deleteSelected()">❌</button>
    <button onclick="resetView()">🔍</button>
</div>

<input type="file" id="pdfInput" accept="application/pdf" style="display:none;">
<canvas id="canvas"></canvas>

<script>
const API_URL = "https://lavish-creativity-production-c53a.up.railway.app";

const canvas = document.getElementById("canvas");
const ctx    = canvas.getContext("2d");

// ── State ──────────────────────────────────────────────────────────────────
let lines        = [];
let texts        = [];
let currentFile  = null;
let diagramDXFUrl = null;   // ← stores the diagram-only DXF URL after Direct PDF to DXF

let tool         = "select";
let selectedLine = -1;
let selectedText = -1;

let scale   = 1;
let offsetX = 0;
let offsetY = 0;

let pdfWidth  = 1000;
let pdfHeight = 700;

let isDrawing = false;
let isDragging = false;
let isPanning  = false;

let startX = 0, startY = 0;
let lastX  = 0, lastY  = 0;

// ── UI helpers ─────────────────────────────────────────────────────────────
function setStatus(msg){
    document.getElementById("statusBar").textContent = msg;
}

function showLoading(msg){
    document.getElementById("loadingMsg").textContent = msg || "Processing...";
    document.getElementById("loadingOverlay").classList.add("show");
}

function hideLoading(){
    document.getElementById("loadingOverlay").classList.remove("show");
}

// ── Canvas setup ───────────────────────────────────────────────────────────
function resizeCanvas(){
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight - 70;
    redraw();
}
window.addEventListener("resize", resizeCanvas);
resizeCanvas();
setTool("select");

function setTool(t){
    tool = t;
    document.querySelectorAll(".tools button").forEach(b => b.classList.remove("active"));
    if(t === "select") document.getElementById("selectBtn").classList.add("active");
    if(t === "line")   document.getElementById("lineBtn").classList.add("active");
    if(t === "text")   document.getElementById("textBtn").classList.add("active");
}

function getMouse(e){
    const r = canvas.getBoundingClientRect();
    return { x: e.clientX - r.left, y: e.clientY - r.top };
}

function screenToWorld(x, y){
    return { x:(x - offsetX)/scale, y:(y - offsetY)/scale };
}

function fitToScreen(){
    const margin = 80;
    const sx = (canvas.width  - margin*2) / pdfWidth;
    const sy = (canvas.height - margin*2) / pdfHeight;
    scale   = Math.min(sx, sy);
    offsetX = (canvas.width  - pdfWidth  * scale) / 2;
    offsetY = (canvas.height - pdfHeight * scale) / 2;
}

function resetView(){ fitToScreen(); redraw(); }

// ── Open PDF  →  load full drawing into canvas ─────────────────────────────
document.getElementById("pdfInput").addEventListener("change", async function(){
    currentFile = this.files[0];
    if(!currentFile) return;

    showLoading("Loading PDF...");
    setStatus("Loading PDF...");

    const formData = new FormData();
    formData.append("file", currentFile);

    try {
        const res  = await fetch(`${API_URL}/parse-to-json`, { method:"POST", body:formData });
        const data = await res.json();

        if(data.status === "success"){
            lines     = data.lines  || [];
            texts     = data.texts  || [];
            pdfWidth  = data.width  || 1000;
            pdfHeight = data.height || 700;
            selectedLine = -1;
            selectedText = -1;
            diagramDXFUrl = null;   // reset diagram DXF when new PDF opened

            fitToScreen();
            redraw();
            setStatus(`PDF loaded — ${lines.length} lines. Edit then click "Export Edited DXF".`);
        } else {
            setStatus("PDF load failed.");
            alert("PDF load failed");
        }
    } catch(err) {
        setStatus("Error: " + err.message);
        alert("Error: " + err.message);
    } finally {
        hideLoading();
    }
});

// ── Direct PDF to DXF  →  extract diagram only, show on canvas ────────────
async function directPDFtoDXF(){
    if(!currentFile){
        alert("Open a PDF first (📂 Open PDF), then click Direct PDF to DXF.");
        return;
    }

    showLoading("Extracting diagram only...");
    setStatus("Extracting diagram — removing tables & title block...");

    const formData = new FormData();
    formData.append("file", currentFile);

    try {
        const res  = await fetch(`${API_URL}/pdf-to-dxf`, { method:"POST", body:formData });
        const data = await res.json();

        if(data.status === "success"){
            // ── Store the DXF download URL for later export ──
            diagramDXFUrl = data.download_url;

            // ── Also reload the canvas with diagram-only geometry ──
            // Re-parse the same PDF with diagram crop to show correct area
            const formData2 = new FormData();
            formData2.append("file", currentFile);

            const res2  = await fetch(`${API_URL}/parse-to-json`, { method:"POST", body:formData2 });
            const data2 = await res2.json();

            if(data2.status === "success"){
                // Apply same crop percentages as backend (68% width, 87% height)
                const cropXMax = data2.width  * 0.678;
                const cropYMax = data2.height * 0.870;
                const cropXMin = data2.width  * 0.036;
                const cropYMin = data2.height * 0.036;

                // Filter lines to diagram region only
                lines = (data2.lines || []).filter(l =>
                    l.x1 >= cropXMin && l.x2 >= cropXMin &&
                    l.x1 <= cropXMax && l.x2 <= cropXMax &&
                    l.y1 >= cropYMin && l.y2 >= cropYMin &&
                    l.y1 <= cropYMax && l.y2 <= cropYMax
                );

                texts = (data2.texts || []).filter(t =>
                    t.x >= cropXMin && t.x <= cropXMax &&
                    t.y >= cropYMin && t.y <= cropYMax
                );

                pdfWidth  = cropXMax - cropXMin;
                pdfHeight = cropYMax - cropYMin;

                // Offset coords to start at 0,0
                lines = lines.map(l => ({
                    ...l,
                    x1: l.x1 - cropXMin, y1: l.y1 - cropYMin,
                    x2: l.x2 - cropXMin, y2: l.y2 - cropYMin,
                }));
                texts = texts.map(t => ({
                    ...t,
                    x: t.x - cropXMin,
                    y: t.y - cropYMin,
                }));

                selectedLine = -1;
                selectedText = -1;
                fitToScreen();
                redraw();
            }

            setStatus(`✅ Diagram loaded (${lines.length} lines). Edit if needed, then click "Export Edited DXF" to download.`);
        } else {
            setStatus("Direct PDF to DXF failed.");
            alert("Direct PDF to DXF failed");
        }
    } catch(err) {
        setStatus("Error: " + err.message);
        alert("Error: " + err.message);
    } finally {
        hideLoading();
    }
}

// ── Full Page to DXF  →  entire PDF page, no crop ─────────────────────────
async function fullPageToDXF(){
    if(!currentFile){
        alert("Open a PDF first (📂 Open PDF), then click Full Page to DXF.");
        return;
    }
    showLoading("Converting full page to DXF...");
    setStatus("Converting full page (including tables & title block)...");
    try {
        const formData = new FormData();
        formData.append("file", currentFile);
        const res  = await fetch(`${API_URL}/pdf-to-dxf-full`, { method:"POST", body:formData });
        const data = await res.json();
        if(data.status === "success"){
            // Trigger immediate download
            const a = document.createElement("a");
            a.href = data.download_url;
            a.download = currentFile.name.replace(/\.pdf$/i, "_fullpage.dxf");
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setStatus("✅ Full page DXF downloaded. Open in AutoCAD and press Z then E.");
        } else {
            setStatus("Full Page to DXF failed.");
            alert("Full Page to DXF failed: " + (data.detail || "unknown error"));
        }
    } catch(err) {
        setStatus("Error: " + err.message);
        alert("Error: " + err.message);
    } finally {
        hideLoading();
    }
}

// ── Export Edited DXF  →  download ────────────────────────────────────────
async function exportEditedDXF(){
    // If diagram DXF already generated by Direct PDF to DXF → download it directly
    if(diagramDXFUrl){
        setStatus("Downloading diagram DXF...");
        window.open(diagramDXFUrl, "_blank");
        setStatus("✅ DXF downloaded.");
        return;
    }

    // Otherwise export whatever is currently on the canvas (edited version)
    if(lines.length === 0 && texts.length === 0){
        alert("Canvas is empty. Open a PDF first.");
        return;
    }

    showLoading("Exporting DXF...");
    setStatus("Exporting canvas to DXF...");

    try {
        const res  = await fetch(`${API_URL}/export-canvas-to-dxf`, {
            method:"POST",
            headers:{"Content-Type":"application/json"},
            body:JSON.stringify({ lines, texts })
        });
        const data = await res.json();

        if(data.status === "success"){
            window.open(data.download_url, "_blank");
            setStatus("✅ DXF exported and downloaded.");
        } else {
            setStatus("Export failed.");
            alert("Export failed");
        }
    } catch(err) {
        setStatus("Error: " + err.message);
        alert("Error: " + err.message);
    } finally {
        hideLoading();
    }
}

// ── Mouse events ───────────────────────────────────────────────────────────
canvas.addEventListener("mousedown", function(e){
    const m = getMouse(e);
    const p = screenToWorld(m.x, m.y);
    lastX = m.x; lastY = m.y;

    if(e.button === 2){ isPanning = true; return; }

    if(tool === "line"){ isDrawing = true; startX = p.x; startY = p.y; return; }

    if(tool === "text"){
        const value = prompt("Enter text:");
        if(value){ texts.push({x:p.x, y:p.y, text:value, size:14}); redraw(); }
        return;
    }

    if(tool === "select"){
        selectedText = findNearestText(p.x, p.y);
        selectedLine = findNearestLine(p.x, p.y);
        if(selectedText !== -1){ selectedLine = -1; isDragging = true; }
        else if(selectedLine !== -1){ isDragging = true; }
        redraw();
    }
});

canvas.addEventListener("mousemove", function(e){
    const m = getMouse(e);
    const p = screenToWorld(m.x, m.y);
    const dxScreen = m.x - lastX, dyScreen = m.y - lastY;
    const dxWorld  = dxScreen / scale, dyWorld = dyScreen / scale;

    if(isPanning){ offsetX += dxScreen; offsetY += dyScreen; lastX=m.x; lastY=m.y; redraw(); return; }

    if(isDrawing && tool === "line"){
        redraw();
        ctx.save();
        ctx.setTransform(scale,0,0,scale,offsetX,offsetY);
        ctx.strokeStyle = "yellow"; ctx.lineWidth = 2/scale;
        ctx.beginPath(); ctx.moveTo(startX,startY); ctx.lineTo(p.x,p.y); ctx.stroke();
        ctx.restore();
        return;
    }

    if(isDragging && tool === "select"){
        if(selectedLine !== -1){
            lines[selectedLine].x1 += dxWorld; lines[selectedLine].y1 += dyWorld;
            lines[selectedLine].x2 += dxWorld; lines[selectedLine].y2 += dyWorld;
        }
        if(selectedText !== -1){ texts[selectedText].x += dxWorld; texts[selectedText].y += dyWorld; }
        lastX = m.x; lastY = m.y;
        redraw();
    }
});

canvas.addEventListener("mouseup", function(e){
    const m = getMouse(e);
    const p = screenToWorld(m.x, m.y);
    if(isDrawing){ lines.push({x1:startX, y1:startY, x2:p.x, y2:p.y}); }
    isDrawing = false; isDragging = false; isPanning = false;
    redraw();
});

canvas.addEventListener("wheel", function(e){
    e.preventDefault();
    const m = getMouse(e);
    const wb = screenToWorld(m.x, m.y);
    let ns = Math.max(0.1, Math.min(scale * (e.deltaY < 0 ? 1.15 : 0.85), 20));
    scale = ns;
    offsetX = m.x - wb.x * scale;
    offsetY = m.y - wb.y * scale;
    redraw();
});

canvas.addEventListener("contextmenu", e => e.preventDefault());

// ── Theme-aware canvas colors ──────────────────────────────────────────────
function getCanvasColors(){
    const theme = document.body.getAttribute('data-theme') || 'black';
    if(theme === 'white'){
        return { bg:'#ffffff', line:'#000000', lineSelect:'#cc0000', text:'#000000', textSelect:'#cc0000', handle:'#cc0000' };
    } else if(theme === 'blue'){
        return { bg:'#0a1628', line:'#4db8ff', lineSelect:'#ffdd57', text:'#4db8ff', textSelect:'#ffdd57', handle:'#ffdd57' };
    } else if(theme === 'system'){
        return { bg:'#1e1e1e', line:'#569cd6', lineSelect:'#ffdd57', text:'#9cdcfe', textSelect:'#ffdd57', handle:'#ffdd57' };
    } else {
        // black (default)
        return { bg:'#000000', line:'#00ffff', lineSelect:'#ffff00', text:'#ffff00', textSelect:'#ffff00', handle:'#ffff00' };
    }
}

// ── Draw ───────────────────────────────────────────────────────────────────
function redraw(){
    const C = getCanvasColors();
    // Fill canvas background explicitly so it matches theme
    ctx.clearRect(0,0,canvas.width,canvas.height);
    ctx.fillStyle = C.bg;
    ctx.fillRect(0,0,canvas.width,canvas.height);

    ctx.save();
    ctx.setTransform(scale,0,0,scale,offsetX,offsetY);

    lines.forEach((l,i)=>{
        ctx.strokeStyle = i === selectedLine ? C.lineSelect : C.line;
        ctx.lineWidth   = i === selectedLine ? 3/scale : 1/scale;
        ctx.beginPath(); ctx.moveTo(l.x1,l.y1); ctx.lineTo(l.x2,l.y2); ctx.stroke();
        if(i === selectedLine){ drawHandle(l.x1,l.y1,C.handle); drawHandle(l.x2,l.y2,C.handle); }
    });

    texts.forEach((t,i)=>{
        ctx.fillStyle = i === selectedText ? C.textSelect : C.text;
        ctx.font = `${t.size}px Arial`;
        ctx.fillText(t.text, t.x, t.y);
    });

    ctx.restore();
}

function drawHandle(x,y,color){
    const s = 5/scale;
    ctx.fillStyle = color || "#ffff00";
    ctx.fillRect(x-s, y-s, s*2, s*2);
}

function findNearestLine(x,y){
    let nearest=-1, min=8/scale;
    lines.forEach((l,i)=>{
        const d = pointLineDistance(x,y,l.x1,l.y1,l.x2,l.y2);
        if(d < min){ min=d; nearest=i; }
    });
    return nearest;
}

function findNearestText(x,y){
    let nearest=-1, min=18/scale;
    texts.forEach((t,i)=>{
        if(Math.hypot(x-t.x,y-t.y) < min){ min=Math.hypot(x-t.x,y-t.y); nearest=i; }
    });
    return nearest;
}

function pointLineDistance(px,py,x1,y1,x2,y2){
    const A=px-x1,B=py-y1,C=x2-x1,D=y2-y1;
    const dot=A*C+B*D, len=C*C+D*D;
    let param=len?dot/len:-1, xx, yy;
    if(param<0){xx=x1;yy=y1;}else if(param>1){xx=x2;yy=y2;}else{xx=x1+param*C;yy=y1+param*D;}
    return Math.hypot(px-xx,py-yy);
}

function deleteSelected(){
    if(selectedLine !== -1){ lines.splice(selectedLine,1); selectedLine=-1; redraw(); return; }
    if(selectedText !== -1){ texts.splice(selectedText,1); selectedText=-1; redraw(); return; }
    alert("Select a line or text first.");
}
</script>
<script>
// ── PWA Service Worker Registration ────────────────────────────────────────
if('serviceWorker' in navigator){
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(() => console.log('SW registered'))
            .catch(err => console.log('SW error:', err));
    });
}

// ── PWA Install Prompt ──────────────────────────────────────────────────────
let deferredPrompt = null;
window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    // Show install button in status bar
    const bar = document.getElementById('statusBar');
    const btn = document.createElement('button');
    btn.textContent = '📲 Install App';
    btn.style.cssText = 'margin-left:15px;padding:3px 12px;font-size:12px;border-radius:8px;cursor:pointer;background:#00d9ff;color:#000;border:none;font-weight:bold;';
    btn.onclick = () => {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(() => {
            deferredPrompt = null;
            btn.remove();
        });
    };
    bar.appendChild(btn);
});

window.addEventListener('appinstalled', () => {
    console.log('App installed!');
    deferredPrompt = null;
});
</script>

<script>
function setTheme(name, btnId){
    // Remove data-theme for black (default), set for others
    if(name === 'black'){
        document.body.removeAttribute('data-theme');
    } else {
        document.body.setAttribute('data-theme', name);
    }
    // Update active ring on buttons
    document.querySelectorAll('.theme-btn').forEach(b => b.classList.remove('active-theme'));
    document.getElementById(btnId).classList.add('active-theme');
    // Redraw canvas with new bg color
    if(typeof redraw === 'function') redraw();
    localStorage.setItem('dxf-theme', name);
    localStorage.setItem('dxf-theme-btn', btnId);
}

// Restore saved theme on load
(function(){
    const t = localStorage.getItem('dxf-theme');
    const b = localStorage.getItem('dxf-theme-btn');
    if(t && b) setTheme(t, b);
})();
</script>
</body>
</html>

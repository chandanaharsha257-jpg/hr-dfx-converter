<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Interactive CAD Canvas Editor</title>
    <style>
        body { background-color: #1a252f; color: #ecf0f1; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; }
        .editor-container { display: flex; gap: 20px; max-width: 1400px; margin: 0 auto; }
        .toolbar { background: #2c3e50; padding: 15px; border-radius: 8px; display: flex; flex-direction: column; gap: 10px; width: 200px; }
        .workspace { position: relative; background: #0d1b2a; border: 2px dashed #34495e; border-radius: 8px; overflow: hidden; flex-grow: 1; height: 700px; }
        canvas { position: absolute; top: 0; left: 0; cursor: crosshair; }
        button { background: #3498db; border: none; color: white; padding: 10px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #2980b9; }
        .active-tool { background: #2ecc71 !important; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
</head>
<body>

<h2>📐 Blueprint Workspace Editor</h2>
<p>Click on any line to stretch it, or double-click text blocks to modify dimensions before saving.</p>

<div class="editor-container">
    <div class="toolbar">
        <h3>CAD Tools</h3>
        <button id="tool-select" class="active-tool">Select / Move</button>
        <button id="tool-line">Draw Line</button>
        <button id="tool-delete" style="background:#e74c3c;">Delete Selected</button>
        <hr style="border-color: #34495e; width: 100%;">
        <button id="export-btn" style="background:#2ecc71;">Export Final DXF</button>
    </div>

    <div class="workspace">
        <canvas id="cadCanvas" width="1000" height="700"></canvas>
    </div>
</div>

<script>
    // Initialize the interactive vector canvas workspace
    const canvas = new fabric.Canvas('cadCanvas', {
        backgroundColor: '#0b132b',
        selection: true
    });

    // Mock Data simulating what comes out of your Python extractor logic
    const drawingData = {
        lines: [
            {x1: 100, y1: 100, x2: 500, y2: 100},
            {x1: 100, y1: 100, x2: 100, y2: 400},
            {x1: 100, y1: 400, x2: 500, y2: 400},
            {x1: 500, y1: 100, x2: 500, y2: 400}
        ],
        texts: [
            {x: 250, y: 80, text: "MAIN WALL: 4000mm", size: 16},
            {x: 220, y: 250, text: "BEDROOM AREA", size: 20}
        ]
    };

    // Render lines into fully selectable, alterable canvas paths
    drawingData.lines.forEach(l => {
        const lineObj = new fabric.Line([l.x1, l.y1, l.x2, l.y2], {
            stroke: '#00b4d8',
            strokeWidth: 3,
            hasControls: true, // Allows stretching resizing adjustments
            hasBorders: true
        });
        canvas.add(lineObj);
    });

    // Render editable inline text entities
    drawingData.texts.forEach(t => {
        const textObj = new fabric.IText(t.text, {
            left: t.x,
            top: t.y,
            fontFamily: 'Arial',
            fontSize: t.size,
            fill: '#ffb703',
            hasControls: true
        });
        canvas.add(textObj);
    });

    // Delete tool implementation
    document.getElementById('tool-delete').addEventListener('click', () => {
        const activeObjects = canvas.getActiveObjects();
        if (activeObjects.length > 0) {
            activeObjects.forEach(obj => canvas.remove(obj));
            canvas.discardActiveObject().renderAll();
        }
    });

    // Export handler logic: packages the current canvas coordinates to send to Python
    document.getElementById('export-btn').addEventListener('click', () => {
        const structuralLayers = canvas.getObjects();
        const exportPayload = { lines: [], texts: [] };

        structuralLayers.forEach(obj => {
            if (obj.type === 'line') {
                // Read adjusted coordinate geometries
                const coords = obj.getObjectsAndPoints ? obj.getPoints() : obj;
                exportPayload.lines.push({
                    x1: obj.x1 + obj.left, y1: obj.y1 + obj.top,
                    x2: obj.x2 + obj.left, y2: obj.y2 + obj.top
                });
            } else if (obj.type === 'i-text') {
                exportPayload.texts.push({
                    x: obj.left,
                    y: obj.top,
                    text: obj.text,
                    size: obj.fontSize
                });
            }
        });

        console.log("Ready to post back modified entities to backend engine:", exportPayload);
        alert("Modified vectors grouped! Check your browser log console to view data structures.");
    });
</script>
</body>
</html>
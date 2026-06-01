<?php
/**
 * config.php — Central configuration for PDF to DXF frontend
 * Edit PYTHON_API_URL to point to your FastAPI backend.
 */

// Python FastAPI backend URL
// Local:      http://localhost:8000
// Production: http://your-server.com:8000
define('PYTHON_API_URL', 'http://localhost:8000');

// Max upload size (must also match php.ini upload_max_filesize)
define('MAX_FILE_SIZE_MB', 50);

// Allowed MIME types
define('ALLOWED_MIME', 'application/pdf');

// App name shown in UI
define('APP_NAME', 'PDF → AutoCAD Converter');

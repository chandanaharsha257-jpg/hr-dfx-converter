<?php
/**
 * download.php — Proxies DXF file download from Python API to the browser.
 * Keeps the Python API URL server-side (not exposed to user).
 */

require_once 'config.php';

$raw_url  = $_GET['url']  ?? '';
$raw_name = $_GET['name'] ?? 'drawing';

if (empty($raw_url)) {
    http_response_code(400);
    exit("Missing download URL.");
}

// Validate: URL must start with our trusted API base
if (strpos($raw_url, PYTHON_API_URL . '/download/') !== 0) {
    http_response_code(403);
    exit("Invalid download source.");
}

// Fetch the file from Python API
$ch = curl_init($raw_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_FOLLOWLOCATION => true,
]);
$file_data  = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error || $http_code !== 200) {
    http_response_code(500);
    exit("Download failed (HTTP $http_code). " . $curl_error);
}

// Build a clean filename
$basename = pathinfo($raw_name, PATHINFO_FILENAME);
$filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $basename) . '.dxf';

// Stream to browser
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($file_data));
header('Cache-Control: no-cache');

echo $file_data;
exit;

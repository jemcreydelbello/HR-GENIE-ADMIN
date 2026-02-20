<?php
session_start();

// Security check - only allow authenticated admins
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

// Get the file parameter
$file = isset($_GET['file']) ? basename($_GET['file']) : '';

if (empty($file)) {
    http_response_code(400);
    exit('Bad Request');
}

// Validate file extension
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
$file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if (!in_array($file_ext, $allowed_extensions)) {
    http_response_code(403);
    exit('Forbidden');
}

// Build the file path - handle both relative and absolute cases
$upload_dir_path = __DIR__ . '/uploads/tickets/';
$file_path = $upload_dir_path . $file;

// Normalize paths for comparison
$file_path_normalized = str_replace('\\', '/', $file_path);
$upload_dir_normalized = str_replace('\\', '/', $upload_dir_path);

// Simple security check - ensure file is in the uploads/tickets directory
if (strpos($file_path_normalized, $upload_dir_normalized) !== 0) {
    http_response_code(403);
    exit('Forbidden');
}

// Check if file exists
if (!file_exists($file_path) || !is_file($file_path)) {
    http_response_code(404);
    exit('File Not Found');
}

// Set appropriate content type
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'bmp' => 'image/bmp',
    'svg' => 'image/svg+xml'
];

$content_type = $mime_types[$file_ext] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: public, max-age=3600');
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');

// Send the file
readfile($file_path);
exit;
?>

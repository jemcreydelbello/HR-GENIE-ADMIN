<?php
/**
 * Download ticket attachment
 * Secure file download handler
 */

session_start();

// Check if user is logged in (admin OR client)
// Admin: $_SESSION['admin_id'], Client: $_SESSION['client_id']
$is_admin = isset($_SESSION['admin_id']);
$is_client = isset($_SESSION['client_id']);

if (!$is_admin && !$is_client) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Access denied. Login required.';
    exit;
}

include 'db.php';

// Get ticket ID and verify it exists
$ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';

if ($ticket_id <= 0 || empty($filename)) {
    header('HTTP/1.0 400 Bad Request');
    echo 'Invalid request.';
    exit;
}

// Verify ticket and attachment exist
$sql = "SELECT attachment FROM TICKETS WHERE ticket_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.0 404 Not Found');
    echo 'Ticket not found.';
    exit;
}

$row = $result->fetch_assoc();
if ($row['attachment'] !== $filename) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Invalid attachment.';
    exit;
}

$file_path = __DIR__ . '/uploads/tickets/' . $filename;

if (!file_exists($file_path)) {
    header('HTTP/1.0 404 Not Found');
    echo 'File not found.';
    exit;
}

// Serve the file
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
?>

<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['articles']) || !is_array($data['articles'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid request format']);
    exit();
}

$articles = $data['articles'];

// Check if sort_order column exists, if not create it
$check_col = $conn->query("SHOW COLUMNS FROM articles LIKE 'sort_order'");
if ($check_col && $check_col->num_rows === 0) {
    // Column doesn't exist, add it
    $alter_sql = "ALTER TABLE articles ADD COLUMN sort_order INT DEFAULT NULL";
    if (!$conn->query($alter_sql)) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error creating sort_order column: ' . $conn->error]);
        exit();
    }
}

// Start transaction
$conn->begin_transaction();

try {
    // Update sort_order for each article based on its position
    foreach ($articles as $index => $article_id) {
        $article_id = intval($article_id);
        $sort_order = $index + 1; // 1-based ordering
        
        $update_sql = "UPDATE articles SET sort_order = $sort_order WHERE article_id = $article_id";
        if (!$conn->query($update_sql)) {
            throw new Exception('Database update failed: ' . $conn->error);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log the activity
    $admin_id = $_SESSION['admin_id'];
    $article_ids_str = implode(',', array_map('intval', $articles));
    $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, new_value) 
                VALUES ($admin_id, 'REORDER', 'Articles', 0, 'Order updated for articles: $article_ids_str')";
    $conn->query($log_sql);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Article order updated successfully']);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error updating article order: ' . $e->getMessage()]);
}

$conn->close();
?>

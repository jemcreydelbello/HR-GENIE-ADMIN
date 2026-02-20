<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $article_id = (int)$_GET['id'];
    
    // Get article title for logging
    $article_sql = "SELECT title FROM ARTICLES WHERE article_id = $article_id";
    $article_result = $conn->query($article_sql);
    $article_data = $article_result ? $article_result->fetch_assoc() : null;
    $article_title = $article_data ? $article_data['title'] : 'Unknown';
    
    // Delete from ARTICLE_KEYWORD table first (foreign key)
    $conn->query("DELETE FROM ARTICLE_KEYWORD WHERE article_id = $article_id");
    
    // Delete from ARTICLE_TAGS table if it exists
    $conn->query("DELETE FROM ARTICLE_TAGS WHERE article_id = $article_id");
    
    // Delete the article
    $sql = "DELETE FROM ARTICLES WHERE article_id = $article_id";
    
    if ($conn->query($sql)) {
        // Log the activity
        $admin_id = $_SESSION['admin_id'];
        $article_title_escaped = $conn->real_escape_string($article_title);
        $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, old_value) 
                    VALUES ($admin_id, 'DELETE', 'Article', $article_id, '$article_title_escaped')";
        $conn->query($log_sql);
        
        echo json_encode(['success' => true, 'message' => 'Article deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Article ID is required']);
}

$conn->close();
?>

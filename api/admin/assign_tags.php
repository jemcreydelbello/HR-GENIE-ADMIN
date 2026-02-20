<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include 'db.php';

try {
    $article_id = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
    $tag_ids = isset($_POST['tag_ids']) ? $_POST['tag_ids'] : '';
    
    if ($article_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid article ID']);
        exit();
    }
    
    // Convert tag_ids from comma-separated string to array
    $tags = [];
    if (!empty($tag_ids)) {
        $tags = array_filter(array_map('intval', explode(',', $tag_ids)));
    }
    
    // Validate max 3 tags
    if (count($tags) > 3) {
        echo json_encode(['success' => false, 'message' => 'Maximum 3 tags allowed per article']);
        exit();
    }
    
    // First, check if article exists
    $check_sql = "SELECT article_id FROM ARTICLES WHERE article_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param('i', $article_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Article not found']);
        exit();
    }
    
    // Delete existing tags for this article
    $delete_sql = "DELETE FROM article_tags WHERE article_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param('i', $article_id);
    $stmt->execute();
    
    // Insert new tags for this article
    if (!empty($tags)) {
        foreach ($tags as $tag_id) {
            $insert_sql = "INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param('ii', $article_id, $tag_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error assigning tags: " . $conn->error);
            }
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Tags assigned successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>

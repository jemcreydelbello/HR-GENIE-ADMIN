<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include 'db.php';

try {
    $tag_id = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;
    
    if ($tag_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid tag ID']);
        exit();
    }
    
    // Get tag name for logging
    $get_sql = "SELECT tag_name FROM tags WHERE tag_id = ? LIMIT 1";
    $stmt = $conn->prepare($get_sql);
    $stmt->bind_param('i', $tag_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Tag not found']);
        exit();
    }
    
    $row = $result->fetch_assoc();
    $tag_name = $row['tag_name'];
    
    // Check if this tag is used by any article
    $check_sql = "SELECT COUNT(*) as count FROM article_tags WHERE tag_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param('i', $tag_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    
    if ($check_row['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete tag that is assigned to articles']);
        exit();
    }
    
    // Delete tag from tags table (article_tags will cascade delete via foreign key)
    $delete_sql = "DELETE FROM tags WHERE tag_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param('i', $tag_id);
    
    if ($stmt->execute()) {
        // Log the activity
        $admin_id = $_SESSION['admin_id'];
        $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, new_value) 
                    VALUES (?, 'DELETE', 'Tag', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param('is', $admin_id, $tag_name);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Tag deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting tag: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>

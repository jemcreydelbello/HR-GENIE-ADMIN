<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include 'db.php';

try {
    // Get all tags with their usage count from normalized tags table
    $sql = "SELECT 
            t.tag_id,
            t.tag_name,
            COUNT(DISTINCT at.article_id) as usage_count
            FROM tags t
            LEFT JOIN article_tags at ON t.tag_id = at.tag_id
            GROUP BY t.tag_id, t.tag_name
            ORDER BY t.tag_name ASC";
    
    $result = $conn->query($sql);
    $tags = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tags[] = [
                'tag_id' => (int)$row['tag_id'],
                'tag_name' => $row['tag_name'],
                'usage_count' => (int)$row['usage_count']
            ];
        }
    }
    
    echo json_encode(['success' => true, 'tags' => $tags]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>

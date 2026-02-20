<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// Fetch categories with their feedback counts
$categories_sql = "
    SELECT 
        c.category_id,
        c.category_name,
        COALESCE(SUM(CASE WHEN af.is_helpful = 1 THEN 1 ELSE 0 END), 0) as likes,
        COALESCE(SUM(CASE WHEN af.is_helpful = 0 THEN 1 ELSE 0 END), 0) as dislikes
    FROM categories c
    LEFT JOIN subcategories s ON c.category_id = s.category_id
    LEFT JOIN articles a ON s.subcategory_id = a.subcategory_id
    LEFT JOIN article_feedback af ON a.article_id = af.article_id
    GROUP BY c.category_id, c.category_name
    ORDER BY c.category_name ASC
";

$result = $conn->query($categories_sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'category_id' => $row['category_id'],
        'category_name' => $row['category_name'],
        'likes' => intval($row['likes']),
        'dislikes' => intval($row['dislikes'])
    ];
}

echo json_encode([
    'success' => true,
    'data' => $categories
]);
?>

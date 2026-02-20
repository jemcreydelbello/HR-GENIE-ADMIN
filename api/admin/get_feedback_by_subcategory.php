<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['category_id'])) {
    echo json_encode(['success' => false, 'message' => 'Category ID required']);
    exit;
}

$category_id = intval($_GET['category_id']);

// Fetch subcategories with their feedback counts for the selected category
$subcategories_sql = "
    SELECT 
        s.subcategory_id,
        s.subcategory_name,
        COALESCE(SUM(CASE WHEN af.is_helpful = 1 THEN 1 ELSE 0 END), 0) as likes,
        COALESCE(SUM(CASE WHEN af.is_helpful = 0 THEN 1 ELSE 0 END), 0) as dislikes
    FROM subcategories s
    LEFT JOIN articles a ON s.subcategory_id = a.subcategory_id
    LEFT JOIN article_feedback af ON a.article_id = af.article_id
    WHERE s.category_id = ?
    GROUP BY s.subcategory_id, s.subcategory_name
    ORDER BY s.subcategory_name ASC
";

$stmt = $conn->prepare($subcategories_sql);
$stmt->bind_param('i', $category_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$subcategories = [];
while ($row = $result->fetch_assoc()) {
    $subcategories[] = [
        'subcategory_id' => $row['subcategory_id'],
        'subcategory_name' => $row['subcategory_name'],
        'likes' => intval($row['likes']),
        'dislikes' => intval($row['dislikes'])
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'data' => $subcategories
]);
?>

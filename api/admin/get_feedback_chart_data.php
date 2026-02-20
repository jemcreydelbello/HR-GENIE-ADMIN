<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['subcategory_id'])) {
    echo json_encode(['success' => false, 'message' => 'Subcategory ID required']);
    exit;
}

$subcategory_id = intval($_GET['subcategory_id']);

// Fetch articles and their feedback for the selected subcategory
$articles_sql = "
    SELECT 
        a.article_id,
        a.title,
        COALESCE(SUM(CASE WHEN af.is_helpful = 1 THEN 1 ELSE 0 END), 0) as likes,
        COALESCE(SUM(CASE WHEN af.is_helpful = 0 THEN 1 ELSE 0 END), 0) as dislikes
    FROM articles a
    LEFT JOIN article_feedback af ON a.article_id = af.article_id
    WHERE a.subcategory_id = ?
    GROUP BY a.article_id, a.title
    ORDER BY a.title ASC
";

$articles_stmt = $conn->prepare($articles_sql);
$articles_stmt->bind_param('i', $subcategory_id);
$articles_stmt->execute();
$articles_result = $articles_stmt->get_result();

$articles = [];
while ($art_row = $articles_result->fetch_assoc()) {
    $articles[] = [
        'article_id' => $art_row['article_id'],
        'title' => $art_row['title'],
        'likes' => intval($art_row['likes']),
        'dislikes' => intval($art_row['dislikes'])
    ];
}
$articles_stmt->close();

echo json_encode([
    'success' => true,
    'data' => $articles
]);
?>

<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include 'db.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';

if (empty($search) || strlen($search) < 1) {
    echo json_encode(['articles' => []]);
    exit();
}

$search_escaped = $conn->real_escape_string($search);

// Search across all articles in database (not just current page)
$sql = "SELECT a.article_id, a.title, a.article_type, s.subcategory_name, c.category_name 
        FROM ARTICLES a 
        LEFT JOIN subcategories s ON a.subcategory_id = s.subcategory_id 
        LEFT JOIN categories c ON s.category_id = c.category_id 
        WHERE a.title LIKE '%$search_escaped%' 
           OR a.content LIKE '%$search_escaped%'
           OR s.subcategory_name LIKE '%$search_escaped%'
           OR c.category_name LIKE '%$search_escaped%'
           OR a.article_type LIKE '%$search_escaped%'
        ORDER BY a.title ASC
        LIMIT 20";

$result = $conn->query($sql);
$articles = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $articles[] = [
            'article_id' => $row['article_id'],
            'title' => $row['title'],
            'type' => $row['article_type'],
            'category' => $row['category_name'],
            'subcategory' => $row['subcategory_name']
        ];
    }
}

echo json_encode(['articles' => $articles]);
$conn->close();
?>

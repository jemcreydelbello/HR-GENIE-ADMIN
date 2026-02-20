<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// Handle looking up category for a subcategory name
if (isset($_GET['get_category_for_subcategory'])) {
    $subcategory_name = $_GET['get_category_for_subcategory'];
    
    $query_sql = "
        SELECT s.category_id
        FROM subcategories s
        WHERE s.subcategory_name = ?
        LIMIT 1
    ";
    
    $query_stmt = $conn->prepare($query_sql);
    if (!$query_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $query_stmt->bind_param('s', $subcategory_name);
    $query_stmt->execute();
    $query_result = $query_stmt->get_result();
    
    if ($query_result->num_rows > 0) {
        $row = $query_result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'category_id' => intval($row['category_id'])
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Subcategory not found']);
    }
    
    $query_stmt->close();
    exit;
}

// Original functionality: fetch subcategories for a category
if (!isset($_GET['category_id'])) {
    echo json_encode(['success' => false, 'message' => 'Category ID required']);
    exit;
}

$category_id = intval($_GET['category_id']);

// Fetch all subcategories for the selected category
$subcategories_sql = "
    SELECT subcategory_id, subcategory_name
    FROM subcategories
    WHERE category_id = ?
    ORDER BY subcategory_name ASC
";

$subcategories_stmt = $conn->prepare($subcategories_sql);
$subcategories_stmt->bind_param('i', $category_id);
$subcategories_stmt->execute();
$subcategories_result = $subcategories_stmt->get_result();

$subcategories = [];
while ($row = $subcategories_result->fetch_assoc()) {
    $subcategories[] = $row;
}
$subcategories_stmt->close();

echo json_encode([
    'success' => true,
    'data' => $subcategories
]);
?>

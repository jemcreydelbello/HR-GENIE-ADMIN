<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include 'db.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$subcategories = [];

if (!empty($search) && $category_id > 0) {
    $search_escaped = $conn->real_escape_string($search);
    
    // Search for subcategories that match the search term
    $sql = "SELECT subcategory_id, subcategory_name, description_ 
            FROM subcategories 
            WHERE category_id = $category_id 
            AND (subcategory_name LIKE '%$search_escaped%' OR description_ LIKE '%$search_escaped%')
            ORDER BY subcategory_name ASC
            LIMIT 20";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $subcategories[] = [
                'subcategory_id' => $row['subcategory_id'],
                'subcategory_name' => $row['subcategory_name'],
                'description' => substr($row['description_'], 0, 100)
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode(['subcategories' => $subcategories]);
$conn->close();
?>

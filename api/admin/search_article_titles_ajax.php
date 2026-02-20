<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include 'db.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$titles = [];

if (!empty($search) && strlen($search) > 0) {
    $search_escaped = $conn->real_escape_string($search);
    
    // Search for article titles that match the search term
    $sql = "SELECT DISTINCT title 
            FROM articles 
            WHERE title LIKE '%$search_escaped%'
            ORDER BY title ASC
            LIMIT 15";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $titles[] = $row['title'];
        }
    }
}

header('Content-Type: application/json');
echo json_encode(['titles' => $titles]);
$conn->close();
?>

<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : 'all';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Calculate date range
$today = date('Y-m-d');
$startDate = '2020-01-01';

switch($dateRange) {
    case 'today':
        $startDate = $today;
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        break;
    case 'month':
        $startDate = date('Y-m-01');
        break;
    case 'all':
    default:
        $startDate = '2020-01-01';
        break;
}

if ($type === 'tickets') {
    // Get tickets data with date and status filters
    $sql = "SELECT 
                status_,
                COUNT(*) as count
            FROM TICKETS 
            WHERE DATE(created_at) >= '$startDate' AND DATE(created_at) <= '$today'";
    
    if ($status !== 'all') {
        if ($status === 'done') {
            $sql .= " AND status_ = 'Done'";
        } elseif ($status === 'pending') {
            $sql .= " AND status_ != 'Done' AND status_ != 'In Progress'";
        } elseif ($status === 'progress') {
            $sql .= " AND status_ = 'In Progress'";
        }
    }
    
    $sql .= " GROUP BY status_ ORDER BY status_";
    
    $result = $conn->query($sql);
    
    $doneCount = 0;
    $progressCount = 0;
    $pendingCount = 0;
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['status_'] === 'Done') {
                $doneCount = $row['count'];
            } elseif ($row['status_'] === 'In Progress') {
                $progressCount = $row['count'];
            } else {
                $pendingCount += $row['count'];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'done_counts' => [$doneCount],
        'progress_counts' => [$progressCount],
        'pending_counts' => [$pendingCount]
    ]);

} elseif ($type === 'articles') {
    // Get articles data with date, category, and publish status filters
    $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
    $publishStatus = isset($_GET['publish_status']) ? $_GET['publish_status'] : '';
    
    $sql = "SELECT 
                COALESCE(c.category_name, 'Uncategorized') as category,
                COUNT(*) as count
            FROM ARTICLES a
            LEFT JOIN subcategories s ON a.subcategory_id = s.subcategory_id
            LEFT JOIN categories c ON s.category_id = c.category_id
            WHERE DATE(a.created_at) >= '$startDate' AND DATE(a.created_at) <= '$today'";
    
    if ($categoryId > 0) {
        $sql .= " AND c.category_id = $categoryId";
    }
    
    if ($publishStatus === 'published') {
        $sql .= " AND a.status = 'Published'";
    } elseif ($publishStatus === 'unpublished') {
        $sql .= " AND a.status = 'Publish'";
    }
    
    $sql .= " GROUP BY c.category_id, c.category_name ORDER BY count DESC";
    
    $result = $conn->query($sql);
    
    $labels = [];
    $counts = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['category'];
            $counts[] = (int)$row['count'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'counts' => $counts
    ]);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
}

$conn->close();
?>

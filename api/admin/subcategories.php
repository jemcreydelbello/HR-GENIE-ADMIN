<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
include 'db.php';

// Get category_id from URL
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Verify category exists
if ($category_id === 0) {
    header('Location: category.php?status=error&message=' . urlencode('No category selected'));
    exit();
}

$category_check = $conn->query("SELECT category_id, category_name FROM CATEGORIES WHERE category_id = $category_id");
if (!$category_check || $category_check->num_rows === 0) {
    header('Location: category.php?status=error&message=' . urlencode('Category not found'));
    exit();
}
$current_category = $category_check->fetch_assoc();

$message = '';
$message_type = '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_desc';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Pagination settings
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Handle POST requests (Add/Edit/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $subcategory_name = $_POST['subcategory_name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if (!empty($subcategory_name)) {
            $subcategory_name = $conn->real_escape_string($subcategory_name);
            $description = $conn->real_escape_string($description);
            $created_by = $_SESSION['admin_id'];
            
            // Check if subcategory with same name already exists in this category
            $check_sql = "SELECT subcategory_id FROM subcategories WHERE category_id = $category_id AND subcategory_name = '$subcategory_name'";
            $check_result = $conn->query($check_sql);
            
            if ($check_result && $check_result->num_rows > 0) {
                header("Location: subcategories.php?category_id=$category_id&status=error&message=" . urlencode('Subcategory with this name already exists!'));
                exit();
            }
            
            $sql = "INSERT INTO subcategories (category_id, subcategory_name, description_, created_by) 
                    VALUES ($category_id, '$subcategory_name', '$description', $created_by)";
            
            if ($conn->query($sql)) {
                $subcategory_id = $conn->insert_id;
                
                // Log the activity
                $admin_id = $_SESSION['admin_id'];
                $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, new_value) 
                            VALUES ($admin_id, 'CREATE', 'Subcategory', $subcategory_id, '$subcategory_name')";
                $conn->query($log_sql);
                
                header("Location: subcategories.php?category_id=$category_id&status=success&message=" . urlencode('Subcategory added successfully!'));
                exit();
            } else {
                header("Location: subcategories.php?category_id=$category_id&status=error&message=" . urlencode('Error adding subcategory: ' . $conn->error));
                exit();
            }
        } else {
            $message = 'Please fill in the subcategory name.';
            $message_type = 'error';
        }
    } elseif ($action === 'edit') {
        $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
        $subcategory_name = $_POST['subcategory_name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if ($subcategory_id > 0 && !empty($subcategory_name)) {
            $subcategory_name = $conn->real_escape_string($subcategory_name);
            $description = $conn->real_escape_string($description);
            
            // Get old values for audit trail
            $old_data_sql = "SELECT subcategory_name, description_ FROM subcategories WHERE subcategory_id = $subcategory_id";
            $old_data_result = $conn->query($old_data_sql);
            $old_data = $old_data_result ? $old_data_result->fetch_assoc() : null;
            
            $sql = "UPDATE subcategories SET 
                    subcategory_name = '$subcategory_name',
                    description_ = '$description'
                    WHERE subcategory_id = $subcategory_id";
            
            if ($conn->query($sql)) {
                // Log the activity
                $admin_id = $_SESSION['admin_id'];
                $old_value = $old_data ? "Name: " . $old_data['subcategory_name'] : "Unknown";
                $new_value = "Name: " . $subcategory_name;
                $old_value_escaped = $conn->real_escape_string($old_value);
                $new_value_escaped = $conn->real_escape_string($new_value);
                $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, old_value, new_value) 
                            VALUES ($admin_id, 'UPDATE', 'Subcategory', $subcategory_id, '$old_value_escaped', '$new_value_escaped')";
                $conn->query($log_sql);
                
                header("Location: subcategories.php?category_id=$category_id&status=success&message=" . urlencode('Subcategory updated successfully!'));
                exit();
            } else {
                header("Location: subcategories.php?category_id=$category_id&status=error&message=" . urlencode('Error updating subcategory: ' . $conn->error));
                exit();
            }
        } else {
            $message = 'Invalid subcategory or missing data.';
            $message_type = 'error';
        }
    } elseif ($action === 'delete') {
        $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
        
        if ($subcategory_id > 0) {
            // Get subcategory name for logging
            $sub_data_sql = "SELECT subcategory_name FROM subcategories WHERE subcategory_id = $subcategory_id";
            $sub_data_result = $conn->query($sub_data_sql);
            $sub_data = $sub_data_result ? $sub_data_result->fetch_assoc() : null;
            $sub_name = $sub_data ? $sub_data['subcategory_name'] : 'Unknown';
            
            $sql = "DELETE FROM subcategories WHERE subcategory_id = $subcategory_id";
            
            if ($conn->query($sql)) {
                // Log the activity
                $admin_id = $_SESSION['admin_id'];
                $sub_name_escaped = $conn->real_escape_string($sub_name);
                $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, old_value) 
                            VALUES ($admin_id, 'DELETE', 'Subcategory', $subcategory_id, '$sub_name_escaped')";
                $conn->query($log_sql);
                
                header("Location: subcategories.php?category_id=$category_id&status=success&message=" . urlencode('Subcategory deleted successfully!'));
                exit();
            } else {
                header("Location: subcategories.php?category_id=$category_id&status=error&message=" . urlencode('Error deleting subcategory: ' . $conn->error));
                exit();
            }
        }
    }
}

// Build search query
$search_condition = '';
if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $search_condition = " AND (s.subcategory_name LIKE '%$search_escaped%' OR s.description_ LIKE '%$search_escaped%')";
}

// Build filter query
$filter_condition = '';
$having_condition = '';
if ($filter === 'has_articles') {
    $having_condition = " HAVING article_count > 0";
} elseif ($filter === 'no_articles') {
    $having_condition = " HAVING article_count = 0";
}

// Build sort query
$sort_sql = '';
switch ($sort_by) {
    case 'name_asc':
        $sort_sql = 'ORDER BY s.subcategory_name ASC';
        break;
    case 'name_desc':
        $sort_sql = 'ORDER BY s.subcategory_name DESC';
        break;
    case 'date_asc':
        $sort_sql = 'ORDER BY s.created_at ASC';
        break;
    case 'date_desc':
    default:
        $sort_sql = 'ORDER BY s.created_at DESC';
        break;
}

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM (
                    SELECT s.subcategory_id, COUNT(a.article_id) as article_count
                    FROM subcategories s
                    LEFT JOIN ARTICLES a ON a.category = s.subcategory_name
                    WHERE s.category_id = $category_id $search_condition
                    GROUP BY s.subcategory_id
                    $having_condition
                ) as filtered_subcategories";
$count_result = $conn->query($count_sql);
$total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $per_page);

// Fetch all subcategories with pagination
$subcategories_sql = "SELECT 
                        s.subcategory_id,
                        s.category_id,
                        s.subcategory_name,
                        s.description_,
                        s.created_at,
                        c.category_name,
                        COUNT(a.article_id) as article_count
                    FROM subcategories s
                    JOIN CATEGORIES c ON s.category_id = c.category_id
                    LEFT JOIN ARTICLES a ON a.category = s.subcategory_name
                    WHERE s.category_id = $category_id $search_condition
                    GROUP BY s.subcategory_id, s.subcategory_name, s.description_, s.created_at, c.category_name
                    $having_condition
                    $sort_sql
                    LIMIT $offset, $per_page";

$subcategories_result = $conn->query($subcategories_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Genie | Subcategories</title>
    <link rel="icon" type="image/jpeg" href="assets/img/intellismart.jpg">
    <link rel="stylesheet" href="styles.css">
    <style>
        .notification-toast {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            max-width: 400px;
            word-wrap: break-word;
        }

        .notification-toast.show {
            opacity: 1;
        }

        .notification-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .notification-error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #E5E7EB;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            z-index: 100;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .search-suggestions.active {
            display: block;
        }

        .suggestion-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #F3F4F6;
            transition: background-color 0.2s;
        }

        .suggestion-item:hover {
            background-color: #F9FAFB;
        }

        .suggestion-item.no-results {
            color: #9CA3AF;
            cursor: default;
        }

        .suggestion-item.no-results:hover {
            background-color: white;
        }

        .search-container {
            position: relative;
        }

        /* Description Preview Modal Styles */
        .description-modal-overlay-sub {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .description-modal-overlay-sub.active {
            display: flex;
        }

        .description-modal-sub {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .description-modal-header-sub {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #E5E7EB;
        }

        .description-modal-header-sub h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #1F2937;
        }

        .description-modal-close-sub {
            background: none;
            border: none;
            cursor: pointer;
            color: #9CA3AF;
            font-size: 1.5rem;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .description-modal-close-sub:hover {
            color: #1F2937;
        }

        .description-modal-content-sub {
            color: #4B5563;
            line-height: 1.6;
            word-wrap: break-word;
            white-space: pre-wrap;
        }

        .see-more-link-sub {
            color: #3B82F6;
            font-weight: 500;
            cursor: pointer;
        }

        .see-more-link-sub:hover {
            color: #1D4ED8;
            text-decoration: underline;
        }

        .search-container input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .search-container input:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-container {
            flex: 1;
            min-width: 250px;
        }

        .filter-group {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }

        .filter-group select {
            padding: 0.5rem 2.5rem 0.5rem 0.75rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 0.875rem;
            background-color: white;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23374151' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            cursor: pointer;
            transition: border-color 0.2s;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .filter-group select:hover {
            border-color: #9CA3AF;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
.filter-group select {
            padding: 0.625rem 2.5rem 0.625rem 1rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            background-color: #FFFFFF;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23374151' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .filter-group select:hover {
            border-color: #9CA3AF;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .filter-group select:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1), 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: #EFF6FF;
            color: #0369A1;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .filter-badge button {
            background: none;
            border: none;
            color: #0369A1;
            cursor: pointer;
            padding: 0;
            font-weight: bold;
            font-size: 1rem;
        }

        .filter-badge button:hover {
            color: #0284C7;
        }

        .clear-filters-btn {
            padding: 0.5rem 1rem;
            background-color: #F3F4F6;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .clear-filters-btn:hover {
            background-color: #E5E7EB;
        }

        .results-info {
            color: #6B7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        /* Pagination Styles */
        .table-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            background: #F9FAFB;
            border-top: 1px solid #E5E7EB;
        }

        .table-footer-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: #6B7280;
        }

        .per-page-select {
            padding: 0.375rem 0.75rem;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            font-size: 0.875rem;
            background: #FFFFFF;
            cursor: pointer;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pagination-btn {
            min-width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.5rem;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            background: #FFFFFF;
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .pagination-btn:hover:not(.disabled):not(.active) {
            background: #F9FAFB;
            border-color: #D1D5DB;
        }

        .pagination-btn.active {
            background: #3B82F6;
            color: #FFFFFF;
            border-color: #3B82F6;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Category Container Styles */
        .category-explorer {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .categories-list {
            background-color: white;
        }

        .category-row-wrapper {
            background-color: white;
            border-radius: 6px;
            margin-bottom: 1rem;
            overflow: hidden;
            border: 1px solid #E5E7EB;
            transition: all 0.2s ease;
        }

        .category-row-wrapper:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: #D1D5DB;
        }

        .category-row {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            background-color: white;
        }

        .table-footer {
            background-color: white;
        }

        /* Tags Management Section */
        .tags-management-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-top: 2rem;
        }

        .tags-header {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #E5E7EB;
        }

        .tags-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1F2937;
            margin: 0 0 0.5rem 0;
        }

        .tags-header p {
            font-size: 0.875rem;
            color: #6B7280;
            margin: 0;
        }

        .tags-container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .tag-input-group {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .tag-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .tag-input:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .tag-btn-add {
            padding: 0.75rem 1.5rem;
            background: #3B82F6;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tag-btn-add:hover {
            background: #2563EB;
            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
        }

        .tag-btn-add:active {
            transform: scale(0.98);
        }

        .tags-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        .tag-card {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .tag-card:hover {
            background: #F3F4F6;
            border-color: #D1D5DB;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .tag-name {
            font-weight: 600;
            color: #1F2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tag-badge {
            background: #DBEAFE;
            color: #1E40AF;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .tag-actions {
            display: flex;
            gap: 0.5rem;
        }

        .tag-btn-delete {
            padding: 0.5rem 0.75rem;
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FECACA;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tag-btn-delete:hover {
            background: #FECACA;
        }

        .tags-empty {
            text-align: center;
            padding: 2rem;
            color: #9CA3AF;
        }

        .tags-empty svg {
            width: 48px;
            height: 48px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .tags-list {
                grid-template-columns: 1fr;
            }

            .tag-input-group {
                flex-direction: column;
            }

            .tag-btn-add {
                width: 100%;
            }
        }
    </style>
    <script src="modal.js"></script>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>

        <main class="content">
            <div class="content-header">
                <h1>Subcategories (<?= htmlspecialchars($current_category['category_name']) ?>)</h1>
                <p>Manage the Article Subcategories for FAQ</p>
            </div>

            <!-- Notification Toast Messages -->
            <?php if (isset($_GET['status'])): ?>
                <?php $status = $_GET['status']; ?>
                <?php $msg = isset($_GET['message']) ? urldecode($_GET['message']) : 'Operation completed'; ?>
                <div class="notification-toast notification-<?= $status === 'success' ? 'success' : 'error' ?> show" id="notificationToast">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <form method="GET" class="filters" id="filterForm">
                <input type="hidden" name="category_id" value="<?= $category_id ?>">
                
                <div class="search-container">
                    <input type="text" id="subcategorySearchInput" placeholder="Search subcategories..." value="">
                    <div id="searchSuggestions" class="search-suggestions"></div>
                </div>
                
                <div class="filter-group">
                    <label for="sortBy">Sort by:</label>
                    <select id="sortBy" name="sort_by" onchange="document.getElementById('filterForm').submit()">
                        <option value="date_desc" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                        <option value="date_asc" <?= $sort_by === 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filterSelect">Filter:</label>
                    <select id="filterSelect" name="filter" onchange="document.getElementById('filterForm').submit()">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All set Articles</option>
                        <option value="has_articles" <?= $filter === 'has_articles' ? 'selected' : '' ?>>With Articles</option>
                        <option value="no_articles" <?= $filter === 'no_articles' ? 'selected' : '' ?>>Without Articles</option>
                    </select>
                </div>

                <button type="button" class="new-article-btn" onclick="openAddSubcategoryModal()">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span>New Subcategory</span>
                </button>
            </form>

            <div class="category-explorer">
                <div class="results-info">
                    Showing <span id="subcategoryCount"><?= $subcategories_result && $subcategories_result->num_rows > 0 ? $subcategories_result->num_rows : 0 ?></span> 
                    <span id="subcategoryLabel">subcategories</span>
                </div>

                <?php if ($subcategories_result && $subcategories_result->num_rows > 0): ?>
                    <div class="categories-list" id="subcategoriesList">
                        <?php while($row = $subcategories_result->fetch_assoc()): ?>
                        <div class="category-row-wrapper" style="cursor: pointer;" onclick="window.location.href='articles.php?filter_main_category=<?= urlencode($row['category_id']) ?>&filter_category=<?= urlencode($row['subcategory_name']) ?>';">
                            <div class="category-row">
                                <div class="category-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#62605d" fill-rule="evenodd" d="M2.07 5.258C2 5.626 2 6.068 2 6.95V14c0 3.771 0 5.657 1.172 6.828S6.229 22 10 22h4c3.771 0 5.657 0 6.828-1.172S22 17.771 22 14v-2.202c0-2.632 0-3.949-.77-4.804a3 3 0 0 0-.224-.225C20.151 6 18.834 6 16.202 6h-.374c-1.153 0-1.73 0-2.268-.153a4 4 0 0 1-.848-.352C12.224 5.224 11.816 4.815 11 4l-.55-.55c-.274-.274-.41-.41-.554-.53a4 4 0 0 0-2.18-.903C7.53 2 7.336 2 6.95 2c-.883 0-1.324 0-1.692.07A4 4 0 0 0 2.07 5.257M12.25 10a.75.75 0 0 1 .75-.75h5a.75.75 0 0 1 0 1.5h-5a.75.75 0 0 1-.75-.75" clip-rule="evenodd"/></svg>
                                </div>
                                <div class="category-info">
                                    <h3><?= htmlspecialchars($row['subcategory_name']) ?></h3>
                                    <p class="subcategory-description" data-full-description="<?= htmlspecialchars($row['description_']) ?>">
                                        <?= htmlspecialchars(substr($row['description_'], 0, 120)) ?>
                                        <?php if (strlen($row['description_']) > 120): ?>
                                            <span class="see-more-link-sub" onclick="showFullSubcategoryDescription(event, '<?= htmlspecialchars($row['subcategory_name']) ?>', '<?= htmlspecialchars($row['description_']) ?>')" style="cursor: pointer; color: #3B82F6; font-weight: 500; margin-left: 0.5rem;">See more...</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="category-meta">
                                    <span class="article-count"><?= $row['article_count'] ?> article<?= $row['article_count'] > 1 ? 's' : '' ?></span>
                                </div>
                                <div class="category-actions" onclick="event.stopPropagation();">
                                    <button type="button" class="btn-icon-sm" onclick="editSubcategory(event, <?= $row['subcategory_id'] ?>, '<?= htmlspecialchars(addslashes($row['subcategory_name'])) ?>', '<?= htmlspecialchars(addslashes($row['description_'])) ?>')" title="Edit">
                                        <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                                            <path d="M11.3333 2.00001C11.5084 1.82491 11.7163 1.68602 11.9447 1.59129C12.1731 1.49657 12.4173 1.44775 12.6667 1.44775C12.916 1.44775 13.1602 1.49657 13.3886 1.59129C13.617 1.68602 13.8249 1.82491 14 2.00001C14.1751 2.17511 14.314 2.38305 14.4087 2.61143C14.5034 2.83981 14.5522 3.08405 14.5522 3.33334C14.5522 3.58263 14.5034 3.82687 14.4087 4.05525C14.314 4.28363 14.1751 4.49157 14 4.66667L5.00001 13.6667L1.33334 14.6667L2.33334 11L11.3333 2.00001Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="btn-icon-sm btn-danger" onclick="deleteSubcategory(event, <?= $row['subcategory_id'] ?>, '<?= htmlspecialchars(addslashes($row['subcategory_name'])) ?>')" title="Delete">
                                        <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                                            <path d="M4 6H16M15 6V16C15 17.1046 14.1046 18 13 18H7C5.89543 18 5 17.1046 5 16V6M15 6V5C15 3.89543 14.1046 3 13 3H7C5.89543 3 5 3.89543 5 5V6M8 9V14M12 9V14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                    <!-- Table Footer with Pagination -->
                    <div class="table-footer">
                        <div class="table-footer-left">
                            <span>Showing data</span>
                            <select class="per-page-select" onchange="changePerPage(this.value)">
                                <option value="5" <?php echo $per_page == 5 ? 'selected' : ''; ?>>5</option>
                            </select>
                            <span>out of <?php echo $total_records; ?> entries found</span>
                        </div>
                        <div class="pagination">
                            <a href="?category_id=<?= $category_id ?>&page=<?php echo max(1, $page - 1); ?>&per_page=<?php echo $per_page; ?>" 
                               class="pagination-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                &lt;
                            </a>
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<a href="?category_id=' . $category_id . '&page=1&per_page=' . $per_page . '" class="pagination-btn">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="pagination-btn disabled">...</span>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active = ($i == $page) ? 'active' : '';
                                echo '<a href="?category_id=' . $category_id . '&page=' . $i . '&per_page=' . $per_page . '" class="pagination-btn ' . $active . '">' . $i . '</a>';
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="pagination-btn disabled">...</span>';
                                }
                                echo '<a href="?category_id=' . $category_id . '&page=' . $total_pages . '&per_page=' . $per_page . '" class="pagination-btn">' . $total_pages . '</a>';
                            }
                            ?>
                            <a href="?category_id=<?= $category_id ?>&page=<?php echo min($total_pages, $page + 1); ?>&per_page=<?php echo $per_page; ?>" 
                               class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                &gt;
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <p>No subcategories found for "<?= htmlspecialchars($current_category['category_name']) ?>"</p>
                        <a href="#" onclick="openAddSubcategoryModal(); return false;">Create one now</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Subcategory Modal -->
    <div id="subcategoryModalOverlay" class="modal-overlay" onclick="closeSubcategoryModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2 id="subcategoryModalTitle">New Subcategory</h2>
                <button class="modal-close" onclick="closeSubcategoryModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" id="subcategoryAction" name="action" value="add">
                <input type="hidden" id="subcategoryId" name="subcategory_id" value="">
                
                <div class="form-group">
                    <label for="subcategoryName">Subcategory Name</label>
                    <input type="text" id="subcategoryName" name="subcategory_name" placeholder="Enter Subcategory Name" required>
                </div>
                
                <div class="form-group">
                    <label for="subcategoryDescription">Description</label>
                    <textarea id="subcategoryDescription" name="description" placeholder="Enter subcategory description..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeSubcategoryModal()">Cancel</button>
                    <button type="submit" class="btn-create" id="subcategorySubmitBtn">Create Subcategory</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="subcategoryDeleteConfirmModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 400px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); animation: slideUp 0.3s ease;">
            <div style="margin-bottom: 1.5rem;">
                <div style="width: 60px; height: 60px; background: #FEE2E2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin: 0 0 0.5rem 0;">Delete Subcategory?</h3>
                <p id="subcategoryDeleteMessage" style="font-size: 0.875rem; color: #6B7280; margin: 0;">Are you sure you want to delete this subcategory? This action cannot be undone.</p>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button id="subcategoryDeleteCancel" type="button" style="padding: 0.75rem 1.5rem; background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                    Cancel
                </button>
                <button id="subcategoryDeleteConfirm" type="button" style="padding: 0.75rem 1.5rem; background: #DC2626; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <script>
        // Set category context when this page loads
        document.addEventListener('DOMContentLoaded', function() {
            const currentCategoryName = '<?= htmlspecialchars(addslashes($current_category['category_name'])) ?>';
            setCategoryContext(currentCategoryName);
            
            const notification = document.getElementById('notificationToast');
            if (notification) {
                setTimeout(function() {
                    notification.classList.remove('show');
                }, 5000);
            }
        });

        let currentCategoryId = <?= $category_id ?>;

        function openAddSubcategoryModal() {
            document.getElementById('subcategoryAction').value = 'add';
            document.getElementById('subcategoryId').value = '';
            document.getElementById('subcategoryName').value = '';
            document.getElementById('subcategoryDescription').value = '';
            document.getElementById('subcategoryModalTitle').textContent = 'New Subcategory';
            document.getElementById('subcategorySubmitBtn').textContent = 'Create Subcategory';
            document.getElementById('subcategoryModalOverlay').classList.add('active');
        }

        function closeSubcategoryModal(event) {
            if (event && event.target.id !== 'subcategoryModalOverlay') return;
            document.getElementById('subcategoryModalOverlay').classList.remove('active');
        }

        function editSubcategory(event, subcategoryId, name, description) {
            event.stopPropagation();
            document.getElementById('subcategoryAction').value = 'edit';
            document.getElementById('subcategoryId').value = subcategoryId;
            document.getElementById('subcategoryName').value = name;
            document.getElementById('subcategoryDescription').value = description;
            document.getElementById('subcategoryModalTitle').textContent = 'Edit Subcategory';
            document.getElementById('subcategorySubmitBtn').textContent = 'Update Subcategory';
            document.getElementById('subcategoryModalOverlay').classList.add('active');
        }

        function deleteSubcategory(event, subcategoryId, name) {
            event.stopPropagation();
            document.getElementById('subcategoryDeleteMessage').textContent = `Are you sure you want to delete "${name}"? This action cannot be undone.`;
            
            const modal = document.getElementById('subcategoryDeleteConfirmModal');
            modal.style.display = 'flex';
            
            document.getElementById('subcategoryDeleteCancel').onclick = () => {
                modal.style.display = 'none';
            };
            
            document.getElementById('subcategoryDeleteConfirm').onclick = () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="subcategory_id" value="${subcategoryId}">
                `;
                document.body.appendChild(form);
                form.submit();
            };
        }

        function changePerPage(perPage) {
            window.location.href = `?category_id=${currentCategoryId}&per_page=${perPage}&page=1`;
        }

        // Subcategory search function with live suggestions
        const subcategorySearchInput = document.getElementById('subcategorySearchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');
        const subcategoriesList = document.getElementById('subcategoriesList');
        const subcategoryCount = document.getElementById('subcategoryCount');
        const subcategoryLabel = document.getElementById('subcategoryLabel');

        if (subcategorySearchInput && subcategoriesList) {
            const subcatCards = subcategoriesList.querySelectorAll('.category-row-wrapper');

            // Live search with suggestions
            subcategorySearchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                let visibleCount = 0;

                if (query.length > 0) {
                    // Fetch suggestions from AJAX endpoint
                    fetch(`search_subcategories_ajax.php?search=${encodeURIComponent(query)}&category_id=<?= $category_id ?>`)
                        .then(response => response.json())
                        .then(data => {
                            const suggestions = [];
                            const uniqueNames = new Set();

                            if (data.subcategories && data.subcategories.length > 0) {
                                data.subcategories.forEach(subcat => {
                                    if (!uniqueNames.has(subcat.subcategory_name)) {
                                        uniqueNames.add(subcat.subcategory_name);
                                        suggestions.push(subcat.subcategory_name);
                                    }
                                });
                            }

                            // Show suggestions dropdown
                            if (suggestions.length > 0) {
                                searchSuggestions.innerHTML = suggestions.map(suggestion => 
                                    `<div class="suggestion-item" onclick="filterBySubcategoryName('${suggestion.replace(/'/g, "\\'")}')">${suggestion}</div>`
                                ).join('');
                                searchSuggestions.classList.add('active');
                            } else {
                                searchSuggestions.innerHTML = '<div class="suggestion-item no-results">No subcategories found</div>';
                                searchSuggestions.classList.add('active');
                            }
                        })
                        .catch(error => {
                            console.error('Search error:', error);
                            searchSuggestions.classList.remove('active');
                        });
                } else {
                    searchSuggestions.classList.remove('active');
                }

                // Also do client-side filtering for currently visible cards
                subcatCards.forEach(card => {
                    const title = card.querySelector('h3') ? card.querySelector('h3').textContent.toLowerCase() : '';
                    const description = card.querySelector('p') ? card.querySelector('p').textContent.toLowerCase() : '';
                    
                    const matches = query === '' || title.includes(query) || description.includes(query);
                    
                    if (matches) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Update subcategory count and label
                subcategoryCount.textContent = visibleCount;
                subcategoryLabel.textContent = visibleCount === 1 ? 'subcategory' : 'subcategories';
            });

            // Hide suggestions when clicking elsewhere
            document.addEventListener('click', function(e) {
                if (e.target !== subcategorySearchInput && e.target !== searchSuggestions) {
                    searchSuggestions.classList.remove('active');
                }
            });
        }

        // Function to filter by subcategory name
        function filterBySubcategoryName(name) {
            const subcategorySearchInput = document.getElementById('subcategorySearchInput');
            subcategorySearchInput.value = name;
            
            // Trigger input event to update results
            const event = new Event('input', { bubbles: true });
            subcategorySearchInput.dispatchEvent(event);
        }

        // Close modal when clicking outside
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeSubcategoryModal();
                document.getElementById('subcategoryDeleteConfirmModal').style.display = 'none';
                closeFullSubcategoryDescription();
            }
        });

        // Description Preview Modal Functions
        function showFullSubcategoryDescription(event, subcategoryName, description) {
            event.stopPropagation();
            const overlay = document.getElementById('descriptionModalOverlaySub');
            const modal = overlay.querySelector('.description-modal-sub');
            const titleEl = modal.querySelector('.description-modal-header-sub h2');
            const contentEl = modal.querySelector('.description-modal-content-sub');
            
            titleEl.textContent = subcategoryName;
            contentEl.textContent = description;
            overlay.classList.add('active');
        }

        function closeFullSubcategoryDescription() {
            const overlay = document.getElementById('descriptionModalOverlaySub');
            overlay.classList.remove('active');
        }

        // Close modal when clicking on overlay
        const descriptionOverlaySub = document.getElementById('descriptionModalOverlaySub');
        if (descriptionOverlaySub) {
            descriptionOverlaySub.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeFullSubcategoryDescription();
                }
            });
        }
    </script>

    <!-- Description Preview Modal for Subcategories -->
    <div id="descriptionModalOverlaySub" class="description-modal-overlay-sub">
        <div class="description-modal-sub">
            <div class="description-modal-header-sub">
                <h2></h2>
                <button class="description-modal-close-sub" onclick="closeFullSubcategoryDescription()">×</button>
            </div>
            <div class="description-modal-content-sub"></div>
        </div>
    </div>
</body>
</html>

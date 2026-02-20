<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
include 'db.php';

// Handle AJAX request to get subcategories for a category
if (isset($_GET['action']) && $_GET['action'] == 'get_subcategories' && isset($_GET['category_id'])) {
    header('Content-Type: application/json');
    $category_id = (int)$_GET['category_id'];
    
    // Get subcategories for this category
    $sql = "SELECT subcategory_id, subcategory_name, description_, created_at 
            FROM subcategories 
            WHERE category_id = $category_id
            ORDER BY created_at DESC";
    
    $result = $conn->query($sql);
    $subcategories = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $subcategories[] = [
                'subcategory_id' => $row['subcategory_id'],
                'subcategory_name' => $row['subcategory_name'],
                'description' => $row['description_'],
                'created_date' => date('M d, Y', strtotime($row['created_at']))
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'subcategories' => $subcategories
    ]);
    exit();
}

// Handle AJAX request to get articles for a category
if (isset($_GET['action']) && $_GET['action'] == 'get_articles' && isset($_GET['category_id'])) {
    header('Content-Type: application/json');
    $category_id = (int)$_GET['category_id'];
    
    // Get category info
    $cat_sql = "SELECT category_id, category_name, description_ FROM CATEGORIES WHERE category_id = $category_id";
    $cat_result = $conn->query($cat_sql);
    
    if ($cat_result && $cat_result->num_rows > 0) {
        $category = $cat_result->fetch_assoc();
        
        // Get articles in this category by category name
        $category_name_escaped = $conn->real_escape_string($category['category_name']);
        $art_sql = "SELECT article_id, title, author, admin_id, content, article_date, created_at 
                    FROM ARTICLES 
                    WHERE category = '$category_name_escaped'
                    ORDER BY created_at DESC";
        
        $articles = [];
        $art_result = $conn->query($art_sql);
        
        if ($art_result) {
            while ($article = $art_result->fetch_assoc()) {
                // Detect article type from content
                $content = $article['content'];
                $type = 'standard';
                
                if (strpos($content, 'Q:') === 0 && strpos($content, 'A:') !== false) {
                    $type = 'simple_question';
                } elseif (strpos($content, 'Step ') !== false) {
                    $type = 'step_by_step';
                }
                
                $articles[] = [
                    'article_id' => $article['article_id'],
                    'title' => $article['title'],
                    'author' => $article['author'],
                    'type' => ucwords(str_replace('_', ' ', $type)),
                    'date' => date('M d, Y', strtotime($article['created_at']))
                ];
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit();
        }
        
        echo json_encode([
            'success' => true,
            'category_id' => $category['category_id'],
            'category_name' => $category['category_name'],
            'description' => $category['description_'],
            'articles' => $articles
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
    }
    exit();
}

$message = '';
$message_type = '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_desc';
$filter_articles = isset($_GET['filter_articles']) ? $_GET['filter_articles'] : 'all';

// Pagination settings
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Handle POST requests (Add/Edit/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $category_name = $_POST['category_name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if (!empty($category_name)) {
            $category_name = $conn->real_escape_string($category_name);
            $description = $conn->real_escape_string($description);
            $created_by = $_SESSION['admin_id']; // Current logged-in admin
            
            // Check if category with same name already exists
            $check_sql = "SELECT category_id FROM CATEGORIES WHERE category_name = '$category_name'";
            $check_result = $conn->query($check_sql);
            
            if ($check_result && $check_result->num_rows > 0) {
                // Category already exists
                header("Location: category.php?status=error&message=" . urlencode('Category with this name already exists!'));
                exit();
            }
            
            // Insert category
            $sql = "INSERT INTO CATEGORIES (category_name, description_, created_by) 
                    VALUES ('$category_name', '$description', $created_by)";
            
            if ($conn->query($sql)) {
                $category_id = $conn->insert_id;
                
                // Log the activity
                $admin_id = $_SESSION['admin_id'];
                $category_name_escaped = $conn->real_escape_string($category_name);
                $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, new_value) 
                            VALUES ($admin_id, 'CREATE', 'Category', $category_id, '$category_name_escaped')";
                $conn->query($log_sql);
                
                header("Location: category.php?status=success&message=" . urlencode('Category added successfully!'));
                exit();
            } else {
                header("Location: category.php?status=error&message=" . urlencode('Error adding category: ' . $conn->error));
                exit();
            }
        } else {
            $message = 'Please fill in the category name.';
            $message_type = 'error';
        }
    } elseif ($action === 'edit') {
        $category_id = intval($_POST['category_id'] ?? 0);
        $category_name = $_POST['category_name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if ($category_id > 0 && !empty($category_name)) {
            $category_name = $conn->real_escape_string($category_name);
            $description = $conn->real_escape_string($description);
            
            // Get old values for audit trail
            $old_data_sql = "SELECT category_name, description_ FROM CATEGORIES WHERE category_id = $category_id";
            $old_data_result = $conn->query($old_data_sql);
            $old_data = $old_data_result ? $old_data_result->fetch_assoc() : null;
            
            // Update category
            $sql = "UPDATE CATEGORIES SET 
                    category_name = '$category_name',
                    description_ = '$description'
                    WHERE category_id = $category_id";
            
            if ($conn->query($sql)) {
                // Log the activity
                $admin_id = $_SESSION['admin_id'];
                $old_value = $old_data ? "Name: " . $old_data['category_name'] : "Unknown";
                $new_value = "Name: " . $category_name;
                $old_value_escaped = $conn->real_escape_string($old_value);
                $new_value_escaped = $conn->real_escape_string($new_value);
                $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, old_value, new_value) 
                            VALUES ($admin_id, 'UPDATE', 'Category', $category_id, '$old_value_escaped', '$new_value_escaped')";
                $conn->query($log_sql);
                
                header("Location: category.php?status=success&message=" . urlencode('Category updated successfully!'));
                exit();
            } else {
                header("Location: category.php?status=error&message=" . urlencode('Error updating category: ' . $conn->error));
                exit();
            }
        } else {
            $message = 'Invalid category or missing data.';
            $message_type = 'error';
        }
    } elseif ($action === 'delete') {
        $category_id = intval($_POST['category_id'] ?? 0);
        
        if ($category_id > 0) {
            // Get category name for logging
            $cat_data_sql = "SELECT category_name FROM CATEGORIES WHERE category_id = $category_id";
            $cat_data_result = $conn->query($cat_data_sql);
            $cat_data = $cat_data_result ? $cat_data_result->fetch_assoc() : null;
            $cat_name = $cat_data ? $cat_data['category_name'] : 'Unknown';
            
            $sql = "DELETE FROM CATEGORIES WHERE category_id = $category_id";
            
            if ($conn->query($sql)) {
                // Log the activity
                $admin_id = $_SESSION['admin_id'];
                $cat_name_escaped = $conn->real_escape_string($cat_name);
                $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, old_value) 
                            VALUES ($admin_id, 'DELETE', 'Category', $category_id, '$cat_name_escaped')";
                $conn->query($log_sql);
                
                header("Location: category.php?status=success&message=" . urlencode('Category deleted successfully!'));
                exit();
            } else {
                header("Location: category.php?status=error&message=" . urlencode('Error deleting category: ' . $conn->error));
                exit();
            }
        }
    } elseif ($action === 'delete_subcategory') {
        $category_id = intval($_POST['category_id'] ?? 0);
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
                
                header("Location: category.php?status=success&message=" . urlencode('Subcategory deleted successfully!'));
                exit();
            } else {
                header("Location: category.php?status=error&message=" . urlencode('Error deleting subcategory: ' . $conn->error));
                exit();
            }
        }
    }
}

// Fetch all categories with subcategory count (with optional search filter)
$categories_sql = "SELECT 
                    c.category_id,
                    c.category_name,
                    c.description_,
                    c.created_at,
                    COUNT(s.subcategory_id) as article_count
                FROM CATEGORIES c
                LEFT JOIN subcategories s ON c.category_id = s.category_id
                WHERE 1=1";

if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $categories_sql .= " AND (c.category_name LIKE '%$search_escaped%' OR c.description_ LIKE '%$search_escaped%')";
}

$categories_sql .= " GROUP BY c.category_id, c.category_name, c.description_, c.created_at";

// Apply filter for articles count
if ($filter_articles === 'with_articles') {
    $categories_sql .= " HAVING article_count > 0";
} elseif ($filter_articles === 'without_articles') {
    $categories_sql .= " HAVING article_count = 0";
}

// Apply sorting
switch($sort_by) {
    case 'name_asc':
        $categories_sql .= " ORDER BY c.category_name ASC";
        break;
    case 'name_desc':
        $categories_sql .= " ORDER BY c.category_name DESC";
        break;
    case 'articles_high':
        $categories_sql .= " ORDER BY article_count DESC, c.category_name ASC";
        break;
    case 'articles_low':
        $categories_sql .= " ORDER BY article_count ASC, c.category_name ASC";
        break;
    case 'date_oldest':
        $categories_sql .= " ORDER BY c.created_at ASC";
        break;
    case 'date_desc':
    default:
        $categories_sql .= " ORDER BY c.created_at DESC";
        break;
}

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM ($categories_sql) as count_table";
$count_result = $conn->query($count_sql);
$total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $per_page);

// Add pagination to query
$categories_sql .= " LIMIT $per_page OFFSET $offset";

$categories_result = $conn->query($categories_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Genie | Categories</title>
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
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #E5E7EB;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-footer-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: #6B7280;
        }

        .per-page-select {
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

        .per-page-select:hover {
            border-color: #9CA3AF;
        }

        .per-page-select:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .pagination-btn {
            padding: 0.5rem 0.75rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            background-color: white;
            color: #374151;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .pagination-btn:hover:not(.disabled):not(.active) {
            background-color: #F3F4F6;
            border-color: #9CA3AF;
        }

        .pagination-btn.active {
            background-color: #3B82F6;
            color: white;
            border-color: #3B82F6;
        }

        .pagination-btn.disabled {
            color: #D1D5DB;
            cursor: not-allowed;
            background-color: #F9FAFB;
            border-color: #E5E7EB;
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
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <main class="content">
            <div class="content-header">
                <h1>Categories</h1>
                <p>Manage the Article Categories for FAQ</p>
            </div>

            <div class="filters">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search categories..." value="<?= htmlspecialchars($search) ?>">
                    <div id="searchSuggestions" class="search-suggestions"></div>
                </div>
                
                <div class="filter-group">
                    <label for="sortBy">Sort by:</label>
                    <select id="sortBy" onchange="applyFilters()">
                        <option value="date_desc" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Newest</option>
                        <option value="date_oldest" <?= $sort_by === 'date_oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                        <option value="articles_high" <?= $sort_by === 'articles_high' ? 'selected' : '' ?>>Most Articles</option>
                        <option value="articles_low" <?= $sort_by === 'articles_low' ? 'selected' : '' ?>>Least Articles</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filterArticles">Filter:</label>
                    <select id="filterArticles" onchange="applyFilters()">
                        <option value="all" <?= $filter_articles === 'all' ? 'selected' : '' ?>>All Subcategories</option>
                        <option value="with_articles" <?= $filter_articles === 'with_articles' ? 'selected' : '' ?>>With Subcategory</option>
                        <option value="without_articles" <?= $filter_articles === 'without_articles' ? 'selected' : '' ?>>Without Subcategory</option>
                    </select>
                </div>

                <button type="button" class="new-article-btn" onclick="openAddCategoryModal()">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span>New Category</span>
                </button>
            </div>

            <div class="category-explorer">
                <?php 
                    $total_categories = $categories_result ? $categories_result->num_rows : 0;
                    if ($search || $sort_by !== 'date_desc' || $filter_articles !== 'all'):
                ?>
                <div class="results-info">
                    Showing <?= $total_categories ?> 
                    <?= $total_categories === 1 ? 'category' : 'categories' ?>
                    <?php if ($search): ?>
                        matching "<?= htmlspecialchars($search) ?>"
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="categories-list" id="categoriesList">
                    <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                        <?php while($row = $categories_result->fetch_assoc()): ?>
                        <div class="category-row-wrapper">
                            <div class="category-row">
                                <a href="#" onclick="navigateToSubcategories(<?= $row['category_id'] ?>, '<?= htmlspecialchars(addslashes($row['category_name'])) ?>'); return false;" style="display: flex; align-items: center; gap: 1rem; flex: 1; text-decoration: none; color: inherit; cursor: pointer;">
                                    <div class="category-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#62605d" fill-rule="evenodd" d="M2.07 5.258C2 5.626 2 6.068 2 6.95V14c0 3.771 0 5.657 1.172 6.828S6.229 22 10 22h4c3.771 0 5.657 0 6.828-1.172S22 17.771 22 14v-2.202c0-2.632 0-3.949-.77-4.804a3 3 0 0 0-.224-.225C20.151 6 18.834 6 16.202 6h-.374c-1.153 0-1.73 0-2.268-.153a4 4 0 0 1-.848-.352C12.224 5.224 11.816 4.815 11 4l-.55-.55c-.274-.274-.41-.41-.554-.53a4 4 0 0 0-2.18-.903C7.53 2 7.336 2 6.95 2c-.883 0-1.324 0-1.692.07A4 4 0 0 0 2.07 5.257M12.25 10a.75.75 0 0 1 .75-.75h5a.75.75 0 0 1 0 1.5h-5a.75.75 0 0 1-.75-.75" clip-rule="evenodd"/></svg>
                                    </div>
                                    <div class="category-info">
                                        <h3><?= htmlspecialchars($row['category_name']) ?></h3>
                                        <p class="category-description" data-full-description="<?= htmlspecialchars($row['description_']) ?>">
                                            <?= htmlspecialchars(substr($row['description_'], 0, 120)) ?>
                                            <?php if (strlen($row['description_']) > 120): ?>
                                                <span class="see-more-link" onclick="showFullDescription(event, '<?= htmlspecialchars($row['category_name']) ?>', '<?= htmlspecialchars($row['description_']) ?>')" style="cursor: pointer; color: #3B82F6; font-weight: 500; margin-left: 0.5rem;">See more...</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="category-meta">
                                        <span class="article-count"><?= $row['article_count'] ?> subcategor<?= $row['article_count'] <= 1 ? 'y' : 'ies' ?></span>
                                    </div>
                                </a>
                                <div class="category-actions">
                                    <button type="button" class="btn-icon-sm" onclick="editCategory(event, <?= $row['category_id'] ?>, '<?= htmlspecialchars(addslashes($row['category_name'])) ?>', '<?= htmlspecialchars(addslashes($row['description_'])) ?>', '<?= htmlspecialchars($row['category_image'] ?? '') ?>')" title="Edit">
                                        <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                                            <path d="M11.3333 2.00001C11.5084 1.82491 11.7163 1.68602 11.9447 1.59129C12.1731 1.49657 12.4173 1.44775 12.6667 1.44775C12.916 1.44775 13.1602 1.49657 13.3886 1.59129C13.617 1.68602 13.8249 1.82491 14 2.00001C14.1751 2.17511 14.314 2.38305 14.4087 2.61143C14.5034 2.83981 14.5522 3.08405 14.5522 3.33334C14.5522 3.58263 14.5034 3.82687 14.4087 4.05525C14.314 4.28363 14.1751 4.49157 14 4.66667L5.00001 13.6667L1.33334 14.6667L2.33334 11L11.3333 2.00001Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="btn-icon-sm btn-danger" onclick="deleteCategory(event, <?= $row['category_id'] ?>, '<?= htmlspecialchars(addslashes($row['category_name'])) ?>')" title="Delete">
                                        <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                                            <path d="M4 6H16M15 6V16C15 17.1046 14.1046 18 13 18H7C5.89543 18 5 17.1046 5 16V6M15 6V5C15 3.89543 14.1046 3 13 3H7C5.89543 3 5 3.89543 5 5V6M8 9V14M12 9V14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 3rem;">
                            <p style="color: #9CA3AF; margin-bottom: 1rem;">No categories found.</p>
                            <a href="#" onclick="openAddCategoryModal(); return false;" style="color: #3B82F6; font-weight: 500;">Create one now</a>
                        </div>
                    <?php endif; ?>
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
                        <a href="?page=<?php echo max(1, $page - 1); ?>&per_page=<?php echo $per_page; ?>" 
                           class="pagination-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
                            &lt;
                        </a>
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<a href="?page=1&per_page=' . $per_page . '" class="pagination-btn">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="pagination-btn disabled">...</span>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active = ($i == $page) ? 'active' : '';
                            echo '<a href="?page=' . $i . '&per_page=' . $per_page . '" class="pagination-btn ' . $active . '">' . $i . '</a>';
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="pagination-btn disabled">...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . '&per_page=' . $per_page . '" class="pagination-btn">' . $total_pages . '</a>';
                        }
                        ?>
                        <a href="?page=<?php echo min($total_pages, $page + 1); ?>&per_page=<?php echo $per_page; ?>" 
                           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            &gt;
                        </a>
                    </div>
                </div>

                <!-- Subcategories Section -->
                <div id="subcategoriesSection" style="margin-top: 3rem; display: none;">
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin-bottom: 1.5rem;">Subcategories</h2>
                    
                    <div class="filter-bar">
                        <div class="search-container" style="flex: 1; min-width: 250px; position: relative;">
                            <form method="GET" style="display: flex; gap: 0.5rem; width: 100%;">
                                <input type="hidden" name="category_id" id="subcatCategoryId" value="">
                                <input type="text" id="subcategorySearch" name="subcat_search" placeholder="Search subcategories..." style="flex: 1; padding: 0.75rem 1rem; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 0.875rem;">
                                <select name="subcat_sort_by" id="subcatSortBy" style="padding: 0.625rem 2.5rem 0.625rem 1rem; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 0.875rem; background-color: white;">
                                    <option value="date_desc">Newest First</option>
                                    <option value="date_asc">Oldest First</option>
                                    <option value="name_asc">Name (A-Z)</option>
                                    <option value="name_desc">Name (Z-A)</option>
                                </select>
                            </form>
                        </div>

                        <button type="button" class="new-article-btn" id="addSubcategoryBtn" style="cursor: pointer;">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <span>New Subcategory</span>
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="subcategoriesTable">
                            <thead>
                                <tr>
                                    <th>Subcategory Name</th>
                                    <th>Description</th>
                                    <th>Created Date</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="subcategoriesTableBody">
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 2rem; color: #9CA3AF;">Loading subcategories...</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <!-- Subcategories Table Footer with Pagination -->
                        <div class="table-footer" id="subcategoriesFooter" style="display: none;">
                            <div class="table-footer-left">
                                <span>Showing data</span>
                                <select class="per-page-select" id="subcatPerPage" onchange="changeSubcatPerPage(this.value)">
                                    <option value="15">15</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <span>out of <span id="subcatTotalRecords">0</span> entries found</span>
                            </div>
                            <div class="pagination" id="subcategoriesPagination"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Category Modal Overlay -->
    <div id="categoryModalOverlay" class="modal-overlay" onclick="closeCategoryModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2 id="categoryModalTitle">New Category</h2>
                <button class="modal-close" onclick="closeCategoryModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <form method="POST" class="modal-form" enctype="multipart/form-data">
                <input type="hidden" id="categoryAction" name="action" value="add">
                <input type="hidden" id="categoryId" name="category_id" value="">
                
                <div class="form-group">
                    <label for="categoryName">Category Name</label>
                    <input type="text" id="categoryName" name="category_name" placeholder="Enter Category Name" required>
                </div>
                
                <div class="form-group">
                    <label for="categoryDescription">Description</label>
                    <textarea id="categoryDescription" name="description" rows="6" placeholder="Enter category description..."></textarea>
                </div>
                

                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeCategoryModal()">Cancel</button>
                    <button type="submit" class="btn-create" id="categorySubmitBtn">Create Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="categoryDeleteConfirmModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 400px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); animation: slideUp 0.3s ease;">
            <div style="margin-bottom: 1.5rem;">
                <div style="width: 60px; height: 60px; background: #FEE2E2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin: 0 0 0.5rem 0;">Delete Category?</h3>
                <p id="categoryDeleteMessage" style="font-size: 0.875rem; color: #6B7280; margin: 0;">Are you sure you want to delete this category? This action cannot be undone.</p>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button id="categoryDeleteCancel" type="button" style="padding: 0.75rem 1.5rem; background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                    Cancel
                </button>
                <button id="categoryDeleteConfirm" type="button" style="padding: 0.75rem 1.5rem; background: #DC2626; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Subcategory Modal -->
    <div id="subcategoryModalOverlay" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;" onclick="closeSubcategoryModal(event)">
        <div class="modal-content" style="background: white; border-radius: 12px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); width: 100%; max-width: 500px; animation: slideUp 0.3s ease;" onclick="event.stopPropagation()">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 2rem; border-bottom: 1px solid #E5E7EB;">
                <h2 id="subcategoryModalTitle" style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;">New Subcategory</h2>
                <button class="modal-close" onclick="closeSubcategoryModal()" style="background: none; border: none; cursor: pointer; color: #6B7280; padding: 0; display: flex; align-items: center; justify-content: center;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <form id="subcategoryForm" method="POST" class="modal-form" style="padding: 2rem;">
                <input type="hidden" id="subcategoryAction" name="action" value="add">
                <input type="hidden" id="subcategoryId" name="subcategory_id" value="">
                <input type="hidden" id="subcatCategoryIdHidden" name="category_id" value="">
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="subcategoryName" style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">Subcategory Name</label>
                    <input type="text" id="subcategoryName" name="subcategory_name" placeholder="Enter Subcategory Name" required style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 0.875rem; font-family: inherit; transition: all 0.2s;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="subcategoryDescription" style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">Description</label>
                    <textarea id="subcategoryDescription" name="description" placeholder="Enter subcategory description..." style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 0.875rem; font-family: inherit; transition: all 0.2s; resize: vertical; min-height: 120px;"></textarea>
                </div>
                
                <div class="form-actions" style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn-cancel" onclick="closeSubcategoryModal()" style="padding: 0.75rem 1.5rem; background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">Cancel</button>
                    <button type="submit" class="btn-create" id="subcategorySubmitBtn" style="padding: 0.75rem 1.5rem; background: #3B82F6; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">Create Subcategory</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Subcategory Delete Confirmation Modal -->
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

    <style>
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

        /* Description Preview Modal Styles */
        .description-modal-overlay {
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

        .description-modal-overlay.active {
            display: flex;
        }

        .description-modal {
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

        .description-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #E5E7EB;
        }

        .description-modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #1F2937;
        }

        .description-modal-close {
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

        .description-modal-close:hover {
            color: #1F2937;
        }

        .description-modal-content {
            color: #4B5563;
            line-height: 1.6;
            word-wrap: break-word;
            white-space: pre-wrap;
        }

        .see-more-link {
            color: #3B82F6;
            font-weight: 500;
            cursor: pointer;
        }

        .see-more-link:hover {
            color: #1D4ED8;
            text-decoration: underline;
        }
    </style>

    <script src="modal.js"></script>
    <script>
        // Function to navigate to subcategories and set category context
        function navigateToSubcategories(categoryId, categoryName) {
            setCategoryContext(categoryName);
            window.location.href = 'subcategories.php?category_id=' + categoryId;
        }

        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');

        // Apply filters and redirect
        function applyFilters() {
            const search = searchInput.value.trim();
            const sortBy = document.getElementById('sortBy').value;
            const filterArticles = document.getElementById('filterArticles').value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (sortBy !== 'date_desc') params.append('sort_by', sortBy);
            if (filterArticles !== 'all') params.append('filter_articles', filterArticles);

            const url = 'category.php' + (params.toString() ? '?' + params.toString() : '');
            window.location.href = url;
        }

        // Live search functionality
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const suggestions = [];

            if (query.length > 0) {
                // Filter category rows
                document.querySelectorAll('.category-row-wrapper').forEach(wrapper => {
                    const categoryRow = wrapper.querySelector('.category-row');
                    const categoryName = categoryRow.querySelector('h3').textContent.toLowerCase();
                    const description = categoryRow.querySelector('p').textContent.toLowerCase();
                    
                    if (categoryName.includes(query) || description.includes(query)) {
                        wrapper.style.display = '';
                        if (categoryName.includes(query)) {
                            suggestions.push(categoryRow.querySelector('h3').textContent);
                        }
                    } else {
                        wrapper.style.display = 'none';
                    }
                });

                // Show suggestions dropdown
                if (suggestions.length > 0) {
                    searchSuggestions.innerHTML = suggestions.map(suggestion => 
                        `<div class="suggestion-item" onclick="filterByCategory('${suggestion}')">${suggestion}</div>`
                    ).join('');
                    searchSuggestions.classList.add('active');
                } else {
                    searchSuggestions.innerHTML = '<div class="suggestion-item no-results">No categories found</div>';
                    searchSuggestions.classList.add('active');
                }
            } else {
                // Show all wrappers if search is empty
                document.querySelectorAll('.category-row-wrapper').forEach(wrapper => {
                    wrapper.style.display = '';
                });
                searchSuggestions.classList.remove('active');
            }
        });

        // Click on suggestion to filter
        function filterByCategory(categoryName) {
            searchInput.value = categoryName;
            applyFilters();
        }

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== searchInput && e.target.parentElement !== searchSuggestions) {
                searchSuggestions.classList.remove('active');
            }
        });

        // Edit category
        function editCategory(event, categoryId, categoryName, description, categoryImage) {
            event.stopPropagation();
            openEditCategoryModal(categoryId, categoryName, description, categoryImage);
        }

        // Delete category
        function deleteCategory(event, categoryId, categoryName) {
            event.stopPropagation();
            const modal = document.getElementById('categoryDeleteConfirmModal');
            document.getElementById('categoryDeleteMessage').textContent = `Are you sure you want to delete "${categoryName}"? This action cannot be undone.`;
            modal.style.display = 'flex';
            
            // Handle cancel button
            const cancelBtn = document.getElementById('categoryDeleteCancel');
            cancelBtn.onclick = function() {
                modal.style.display = 'none';
            };
            
            // Handle confirm delete button
            const confirmBtn = document.getElementById('categoryDeleteConfirm');
            confirmBtn.onclick = function() {
                modal.style.display = 'none';
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" value="${categoryId}">
                `;
                document.body.appendChild(form);
                form.submit();
            };
            
            // Close modal on clicking outside
            modal.onclick = function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            };
        }

        function openAddCategoryModal() {
            document.getElementById('categoryModalTitle').textContent = 'New Category';
            document.getElementById('categorySubmitBtn').textContent = 'Create Category';
            document.getElementById('categoryAction').value = 'add';
            document.getElementById('categoryId').value = '';
            document.getElementById('categoryName').value = '';
            document.getElementById('categoryDescription').value = '';
            document.getElementById('categoryModalOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function openEditCategoryModal(categoryId, categoryName, description, categoryImage) {
            document.getElementById('categoryModalTitle').textContent = 'Edit Category';
            document.getElementById('categorySubmitBtn').textContent = 'Update Category';
            document.getElementById('categoryAction').value = 'edit';
            document.getElementById('categoryId').value = categoryId;
            document.getElementById('categoryName').value = categoryName;
            document.getElementById('categoryDescription').value = description;
            
            // Clear previous file input
            document.getElementById('categoryFile').value = '';
            
            // Display current image if exists
            const imagePreviewDiv = document.getElementById('categoryImagePreview');
            if (categoryImage && categoryImage.length > 0) {
                const fileName = categoryImage.split('/').pop();
                const isImage = /\.(jpg|jpeg|png|gif|bmp|webp)$/i.test(fileName);
                
                if (isImage) {
                    imagePreviewDiv.innerHTML = `
                        <img src="${categoryImage}" style="max-width: 100%; height: auto; max-height: 200px; border-radius: 6px; border: 1px solid #E5E7EB; margin-bottom: 0.75rem; cursor: pointer;" onclick="window.open('${categoryImage}', '_blank')" title="Click to view full size">
                        <div style="font-size: 0.75rem; color: #9CA3AF; margin-top: 0.5rem;">Upload a new file to replace it</div>
                    `;
                } else {
                    imagePreviewDiv.innerHTML = `
                        <div style="font-size: 0.75rem; color: #6B7280; margin-bottom: 0.5rem;"><strong>Current file:</strong></div>
                        <a href="${categoryImage}" target="_blank" style="color: #3B82F6; text-decoration: underline; font-size: 0.875rem;">
                            ${fileName}
                        </a>
                        <div style="font-size: 0.75rem; color: #9CA3AF; margin-top: 0.5rem;">Upload a new file to replace it</div>
                    `;
                }
            } else {
                imagePreviewDiv.innerHTML = '<div style="font-size: 0.75rem; color: #9CA3AF;">No file uploaded yet</div>';
            }
            
            document.getElementById('categoryModalOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeCategoryModal(event) {
            if (event && event.target.id !== 'categoryModalOverlay') {
                return;
            }
            document.getElementById('categoryModalOverlay').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modals on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCategoryModal();
            }
        });

        // Show notification if status parameter exists
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification-toast notification-${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            // Hide and remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Check for status parameter on page load
        window.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            const status = params.get('status');
            const message = params.get('message');

            if (status && message) {
                showNotification(decodeURIComponent(message), status);
                // Clean up URL
                window.history.replaceState({}, document.title, 'category.php');
            }
        });

        // Change per-page items
        function changePerPage(value) {
            const params = new URLSearchParams(window.location.search);
            params.set('per_page', value);
            params.set('page', '1'); // Reset to first page
            window.location.search = params.toString();
        }

        // Subcategory Management Functions
        let currentCategoryId = null;
        let currentSubcategoryPage = 1;
        let currentSubcategoryPerPage = 15;

        function loadSubcategories(categoryId) {
            currentCategoryId = categoryId;
            const subcatSection = document.getElementById('subcategoriesSection');
            const subcatTableBody = document.getElementById('subcategoriesTableBody');
            
            if (!subcatSection) {
                console.error('subcategoriesSection element not found');
                return;
            }
            
            // Show loading state
            subcatSection.style.display = 'block';
            subcatTableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem; color: #9CA3AF;">Loading subcategories...</td></tr>';

            // Scroll to subcategories section
            subcatSection.scrollIntoView({ behavior: 'smooth', block: 'start' });

            // Fetch subcategories via AJAX
            const url = `category.php?action=get_subcategories&category_id=${categoryId}`;
            console.log('Fetching from:', url);
            
            fetch(url)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data);
                    if (data.success && data.subcategories.length > 0) {
                        displaySubcategories(data.subcategories);
                    } else {
                        subcatTableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem; color: #9CA3AF;">No subcategories found. Click "New Subcategory" to create one.</td></tr>';
                        document.getElementById('subcategoriesFooter').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    subcatTableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem; color: #E11D48;">Error loading subcategories. Please make sure the subcategories table exists in your database.</td></tr>';
                });
        }

        function displaySubcategories(subcategories) {
            const subcatTableBody = document.getElementById('subcategoriesTableBody');
            
            if (subcategories.length === 0) {
                subcatTableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem; color: #9CA3AF;">No subcategories found.</td></tr>';
                return;
            }

            let html = '';
            subcategories.forEach(subcat => {
                html += `
                    <tr>
                        <td class="name-column" style="font-weight: 600; color: #1F2937;">${escapeHtml(subcat.subcategory_name)}</td>
                        <td style="font-size: 0.875rem; color: #6B7280;">${escapeHtml(subcat.description.substring(0, 50))}${subcat.description.length > 50 ? '...' : ''}</td>
                        <td style="font-size: 0.875rem; color: #6B7280;">${subcat.created_date}</td>
                        <td style="text-align: right;">
                            <div class="actions" style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                                <button type="button" class="btn-icon-sm" onclick="editSubcategory(event, ${subcat.subcategory_id}, '${escapeJs(subcat.subcategory_name)}', '${escapeJs(subcat.description)}')" title="Edit" style="padding: 0.5rem; background: #F3F4F6; border: 1px solid #D1D5DB; border-radius: 6px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; color: #374151;">
                                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                                        <path d="M11.3333 2.00001C11.5084 1.82491 11.7163 1.68602 11.9447 1.59129C12.1731 1.49657 12.4173 1.44775 12.6667 1.44775C12.916 1.44775 13.1602 1.49657 13.3886 1.59129C13.617 1.68602 13.8249 1.82491 14 2.00001C14.1751 2.17511 14.314 2.38305 14.4087 2.61143C14.5034 2.83981 14.5522 3.08405 14.5522 3.33334C14.5522 3.58263 14.5034 3.82687 14.4087 4.05525C14.314 4.28363 14.1751 4.49157 14 4.66667L5.00001 13.6667L1.33334 14.6667L2.33334 11L11.3333 2.00001Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                                <button type="button" class="btn-icon-sm btn-danger" onclick="deleteSubcategory(event, ${subcat.subcategory_id}, '${escapeJs(subcat.subcategory_name)}')" title="Delete" style="padding: 0.5rem; background: #F3F4F6; border: 1px solid #D1D5DB; border-radius: 6px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; color: #374151;">
                                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                                        <path d="M4 6H16M15 6V16C15 17.1046 14.1046 18 13 18H7C5.89543 18 5 17.1046 5 16V6M15 6V5C15 3.89543 14.1046 3 13 3H7C5.89543 3 5 3.89543 5 5V6M8 9V14M12 9V14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            subcatTableBody.innerHTML = html;
        }

        function openSubcategoryModal() {
            document.getElementById('subcatCategoryIdHidden').value = currentCategoryId;
            document.getElementById('subcategoryAction').value = 'add';
            document.getElementById('subcategoryId').value = '';
            document.getElementById('subcategoryName').value = '';
            document.getElementById('subcategoryDescription').value = '';
            document.getElementById('subcategoryModalTitle').textContent = 'New Subcategory';
            document.getElementById('subcategorySubmitBtn').textContent = 'Create Subcategory';
            document.getElementById('subcategoryModalOverlay').style.display = 'flex';
        }

        function closeSubcategoryModal(event) {
            if (event && event.target.id !== 'subcategoryModalOverlay') return;
            document.getElementById('subcategoryModalOverlay').style.display = 'none';
        }

        function editSubcategory(event, subcategoryId, name, description) {
            event.stopPropagation();
            document.getElementById('subcatCategoryIdHidden').value = currentCategoryId;
            document.getElementById('subcategoryAction').value = 'edit';
            document.getElementById('subcategoryId').value = subcategoryId;
            document.getElementById('subcategoryName').value = name;
            document.getElementById('subcategoryDescription').value = description;
            document.getElementById('subcategoryModalTitle').textContent = 'Edit Subcategory';
            document.getElementById('subcategorySubmitBtn').textContent = 'Update Subcategory';
            document.getElementById('subcategoryModalOverlay').style.display = 'flex';
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
                    <input type="hidden" name="action" value="delete_subcategory">
                    <input type="hidden" name="category_id" value="${currentCategoryId}">
                    <input type="hidden" name="subcategory_id" value="${subcategoryId}">
                `;
                document.body.appendChild(form);
                form.submit();
            };
        }

        function changeSubcatPerPage(perPage) {
            currentSubcategoryPerPage = perPage;
            loadSubcategories(currentCategoryId);
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        function escapeJs(text) {
            return text.replace(/'/g, "\\'").replace(/"/g, '&quot;').replace(/\n/g, '\\n');
        }

        // Add event listener to Add Subcategory button
        document.addEventListener('DOMContentLoaded', function() {
            const addSubcatBtn = document.getElementById('addSubcategoryBtn');
            if (addSubcatBtn) {
                addSubcatBtn.addEventListener('click', openSubcategoryModal);
            }

            const subcatForm = document.getElementById('subcategoryForm');
            if (subcatForm) {
                subcatForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    this.submit();
                });
            }
        });

        // Tags management moved to tags.php

        // Description Preview Modal Functions
        function showFullDescription(event, categoryName, description) {
            event.stopPropagation();
            const overlay = document.getElementById('descriptionModalOverlay');
            const modal = overlay.querySelector('.description-modal');
            const titleEl = modal.querySelector('.description-modal-header h2');
            const contentEl = modal.querySelector('.description-modal-content');
            
            titleEl.textContent = categoryName;
            contentEl.textContent = description;
            overlay.classList.add('active');
        }

        function closeDescriptionModal() {
            const overlay = document.getElementById('descriptionModalOverlay');
            overlay.classList.remove('active');
        }

        // Close modal when clicking on overlay
        document.getElementById('descriptionModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDescriptionModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDescriptionModal();
            }
        });
    </script>

    <!-- Description Preview Modal -->
    <div id="descriptionModalOverlay" class="description-modal-overlay">
        <div class="description-modal">
            <div class="description-modal-header">
                <h2></h2>
                <button class="description-modal-close" onclick="closeDescriptionModal()">×</button>
            </div>
            <div class="description-modal-content"></div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>
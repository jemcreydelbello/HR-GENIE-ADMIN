<?php
include 'db.php';
session_start();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Capture pagination parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 5;
$filter_main_category = isset($_GET['filter_main_category']) ? $_GET['filter_main_category'] : '';
$filter_category = isset($_GET['filter_category']) ? $_GET['filter_category'] : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_desc';

// Check if came from category or subcategory page
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$source_page = 'articles.php'; // default
$source_params = '';

if (strpos($referrer, 'category_page.php') !== false) {
    $url_parts = parse_url($referrer);
    parse_str($url_parts['query'] ?? '', $query_params);
    $cat_id = $query_params['cat_id'] ?? '';
    if ($cat_id) {
        $source_page = 'client/category_page.php';
        $source_params = '?cat_id=' . urlencode($cat_id);
    }
} elseif (strpos($referrer, 'subcategory_page.php') !== false) {
    $url_parts = parse_url($referrer);
    parse_str($url_parts['query'] ?? '', $query_params);
    $subcat_id = $query_params['subcat_id'] ?? '';
    if ($subcat_id) {
        $source_page = 'client/subcategory_page.php';
        $source_params = '?subcat_id=' . urlencode($subcat_id);
    }
}

if ($id > 0) {
    // Get article info before deleting for logging
    $article_sql = "SELECT title FROM ARTICLES WHERE article_id = $id";
    $article_result = $conn->query($article_sql);
    $admin_id = $_SESSION['admin_id'] ?? 1;
    $article_title = 'Unknown';
    if ($article_result && $article_result->num_rows > 0) {
        $article = $article_result->fetch_assoc();
        $article_title = $article['title'];
    }
    
    // Delete dependent records first to avoid foreign key constraint errors
    $conn->query("DELETE FROM article_feedback WHERE article_id = $id");
    $conn->query("DELETE FROM article_steps WHERE article_id = $id");
    $conn->query("DELETE FROM article_qa WHERE article_id = $id");
    $conn->query("DELETE FROM article_standard WHERE article_id = $id");
    
    // Now delete the main article
    $sql = "DELETE FROM ARTICLES WHERE article_id = $id";
    
    if ($conn->query($sql)) {
        // Log activity with old_value
        $article_title_escaped = $conn->real_escape_string($article_title);
        $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, old_value) VALUES ($admin_id, 'DELETE', 'Article', $id, '$article_title_escaped')";
        $conn->query($log_sql);
        
        // Build redirect URL - redirect to source page if from category/subcategory, otherwise to articles.php
        if (!empty($source_params)) {
            $redirect_url = $source_page . $source_params;
        } else {
            $redirect_url = 'articles.php?status=success&message=' . urlencode('Article deleted successfully!')
                . '&page=' . $page
                . '&per_page=' . $per_page
                . '&sort_by=' . urlencode($sort_by);
            
            if (!empty($filter_main_category)) {
                $redirect_url .= '&filter_main_category=' . urlencode($filter_main_category);
            }
            if (!empty($filter_category)) {
                $redirect_url .= '&filter_category=' . urlencode($filter_category);
            }
            if (!empty($filter_type)) {
                $redirect_url .= '&filter_type=' . urlencode($filter_type);
            }
            if (!empty($search)) {
                $redirect_url .= '&search=' . urlencode($search);
            }
        }
        
        header('Location: ' . $redirect_url);
    } else {
        // Build redirect URL with pagination parameters for error case too
        if (!empty($source_params)) {
            $redirect_url = $source_page . $source_params;
        } else {
            $redirect_url = 'articles.php?status=error&message=' . urlencode('Error deleting article: ' . $conn->error)
                . '&page=' . $page
                . '&per_page=' . $per_page
                . '&sort_by=' . urlencode($sort_by);
            
            if (!empty($filter_main_category)) {
                $redirect_url .= '&filter_main_category=' . urlencode($filter_main_category);
            }
            if (!empty($filter_category)) {
                $redirect_url .= '&filter_category=' . urlencode($filter_category);
            }
            if (!empty($filter_type)) {
                $redirect_url .= '&filter_type=' . urlencode($filter_type);
            }
            if (!empty($search)) {
                $redirect_url .= '&search=' . urlencode($search);
            }
        }
        
        header('Location: ' . $redirect_url);
    }
} else {
    header('Location: articles.php');
}

exit();
?>

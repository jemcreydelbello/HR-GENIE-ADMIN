<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
include 'db.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_main_category = isset($_GET['filter_main_category']) ? $_GET['filter_main_category'] : '';
$filter_category = isset($_GET['filter_category']) ? $_GET['filter_category'] : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_desc';
$auto_open_article_id = isset($_GET['article_id']) ? (int)$_GET['article_id'] : null;

// Pagination settings
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Fetch all main categories from database
$main_categories_sql = "SELECT category_id, category_name FROM categories ORDER BY category_name ASC";
$main_categories_result = $conn->query($main_categories_sql);
$main_categories = [];
if ($main_categories_result && $main_categories_result->num_rows > 0) {
    while ($cat_row = $main_categories_result->fetch_assoc()) {
        $main_categories[$cat_row['category_id']] = $cat_row['category_name'];
    }
}

// Fetch all subcategories from database
$categories_sql = "SELECT DISTINCT subcategory_name FROM subcategories ORDER BY subcategory_name ASC";
$categories_result = $conn->query($categories_sql);
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($cat_row = $categories_result->fetch_assoc()) {
        $categories[] = $cat_row['subcategory_name'];
    }
}

// Build SQL query
$sql = "SELECT a.*, s.subcategory_name, c.category_name FROM ARTICLES a LEFT JOIN subcategories s ON a.subcategory_id = s.subcategory_id LEFT JOIN categories c ON s.category_id = c.category_id WHERE 1=1";

if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $sql .= " AND (a.title LIKE '%$search_escaped%' OR a.content LIKE '%$search_escaped%')";
}

if (!empty($filter_main_category)) {
    $filter_main_category_escaped = $conn->real_escape_string($filter_main_category);
    $sql .= " AND c.category_id='$filter_main_category_escaped'";
}

if (!empty($filter_category)) {
    $filter_category_escaped = $conn->real_escape_string($filter_category);
    $sql .= " AND s.subcategory_name='$filter_category_escaped'";
}

// Apply sorting
// If both filters are applied, use sort_order first (from drag-and-drop), then date
if (!empty($filter_main_category) && !empty($filter_category)) {
    $sql .= " ORDER BY COALESCE(a.sort_order, 999999), a.article_date DESC";
} else {
    switch($sort_by) {
        case 'title_asc':
            $sql .= " ORDER BY a.title ASC";
            break;
        case 'title_desc':
            $sql .= " ORDER BY a.title DESC";
            break;
        case 'category_asc':
            $sql .= " ORDER BY s.subcategory_name ASC, a.title ASC";
            break;
        case 'date_oldest':
            $sql .= " ORDER BY a.article_date ASC";
            break;
        case 'date_desc':
        default:
            $sql .= " ORDER BY a.article_date DESC";
            break;
    }
}

// Count total records before pagination
$count_sql = "SELECT COUNT(DISTINCT a.article_id) as total FROM ARTICLES a LEFT JOIN subcategories s ON a.subcategory_id = s.subcategory_id LEFT JOIN categories c ON s.category_id = c.category_id WHERE 1=1";
if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $count_sql .= " AND (a.title LIKE '%$search_escaped%' OR a.content LIKE '%$search_escaped%')";
}
if (!empty($filter_main_category)) {
    $filter_main_category_escaped = $conn->real_escape_string($filter_main_category);
    $count_sql .= " AND c.category_id='$filter_main_category_escaped'";
}
if (!empty($filter_category)) {
    $filter_category_escaped = $conn->real_escape_string($filter_category);
    $count_sql .= " AND s.subcategory_name='$filter_category_escaped'";
}
$count_result = $conn->query($count_sql);
$total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $per_page);

// Adjust page if it exceeds total pages (e.g., after deletion)
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

// Add pagination to query
$sql .= " LIMIT $per_page OFFSET $offset";

$result = $conn->query($sql);

// Get message from URL parameter
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$msg_type = isset($_GET['msg_type']) ? $_GET['msg_type'] : 'success';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Genie | Articles</title>
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

        /* Table Container Styles */
        .table-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
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

        /* Inline Article Form Styles */
        #articleFormContainer {
            transition: all 0.3s ease;
        }

        #articleFormContainer .step-item {
            margin-bottom: 2rem;
            padding: 1rem;
            background: #F9FAFB;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
        }

        #articleFormContainer .step-item .step-title {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border: 1px solid #E5E7EB;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        #articleFormContainer .step-item .step-description {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #E5E7EB;
            border-radius: 4px;
            resize: vertical;
            font-size: 0.875rem;
            font-family: inherit;
        }

        .btn-remove-step {
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FECACA;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-remove-step:hover {
            background: #FECACA;
        }

        /* Tags Selector Styles */
        #availableTagsContainer {
            max-height: 200px;
            overflow-y: auto;
        }

        .tag-checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: white;
            border: 1px solid #D1D5DB;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .tag-checkbox-wrapper:hover {
            background: #F3F4F6;
            border-color: #9CA3AF;
        }

        .tag-checkbox-wrapper input[type="checkbox"] {
            cursor: pointer;
            width: 16px;
            height: 16px;
        }

        .tag-checkbox-wrapper input[type="checkbox"]:checked + span {
            color: #059669;
            font-weight: 600;
        }

        .tag-badge-item {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.8rem;
            background: #DBEAFE;
            color: #1E40AF;
            border-radius: 4px;
            font-size: 0.875rem;
            white-space: nowrap;
        }

        .tag-badge-item button {
            background: none;
            border: none;
            color: #1E40AF;
            cursor: pointer;
            font-size: 1.2rem;
            line-height: 1;
            padding: 0;
            display: flex;
            align-items: center;
        }

        .tag-badge-item button:hover {
            color: #1E3A8A;
        }

        /* Tag Search Input */
        #tagSearchInput {
            transition: border-color 0.2s;
        }

        #tagSearchInput:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .tag-checkbox-wrapper.hidden {
            display: none !important;
        }

        .tag-search-no-results {
            grid-column: 1 / -1;
            color: #9CA3AF;
            text-align: center;
            padding: 1rem;
            font-size: 0.875rem;
        }

        /* Search Suggestions Dropdown */
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: #FFFFFF;
            border: 1px solid #D1D5DB;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 10;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .search-suggestions.active {
            display: block;
        }

        .search-suggestions .suggestion-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            cursor: pointer;
            transition: background-color 0.2s;
            color: #374151;
            font-size: 0.875rem;
        }

        .search-suggestions .suggestion-item:hover {
            background-color: #F9FAFB;
        }

        .search-suggestions .suggestion-item:last-child {
            border-bottom: none;
        }

        /* Status Badge Styles */
        .status-badge-draft {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            background: #FEF08A;
            color: #854D0E;
            border-radius: 4px;
            font-size: 0.8125rem;
            font-weight: 600;
            border: 1px solid #FDE047;
        }

        .status-badge-published {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            background: #D1FAE5;
            color: #065F46;
            border-radius: 4px;
            font-size: 0.8125rem;
            font-weight: 600;
            border: 1px solid #A7F3D0;
        }

        /* Status Dropdown Styling */
        .status-dropdown {
            padding: 0.55rem 2.2rem 0.55rem 0.875rem;
            background: #F3F4F6 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236B7280' d='M1 1l5 5 5-5'/%3E%3C/svg%3E") no-repeat right 0.75rem center;
            background-size: 12px;
            color: #1F2937;
            border: 1.5px solid #D1D5DB;
            border-radius: 8px;
            font-size: 0.8125rem;
            font-weight: 500;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            min-width: 130px;
            max-width: 140px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .status-dropdown:hover {
            border-color: #9CA3AF;
            background-color: #FFFFFF;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }

        .status-dropdown:focus {
            outline: none;
            border-color: #3B82F6;
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1), inset 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .status-dropdown option {
            background: #FFFFFF;
            color: #1F2937;
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        /* Title Suggestions Styles */
        .title-suggestions {
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

        .title-suggestions.active {
            display: block;
        }

        .title-suggestion-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #F3F4F6;
            transition: background-color 0.2s;
            font-size: 0.875rem;
            color: #1F2937;
        }

        .title-suggestion-item:hover {
            background-color: #F9FAFB;
        }

        .title-suggestion-item.no-results {
            color: #9CA3AF;
            cursor: default;
            padding: 1rem;
            text-align: center;
        }

        .title-suggestion-item.no-results:hover {
            background-color: white;
        }

        .title-suggestion-item .suggestion-title {
            font-weight: 500;
            color: #1F2937;
        }

        .title-suggestion-item .suggestion-highlight {
            background-color: #FEF08A;
            font-weight: 600;
        }

        /* Drag and Drop Styles */
        .draggable-row {
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
        }

        .draggable-row:hover {
            background-color: #F3F4F6;
        }

        .draggable-row.dragging {
            opacity: 0.5;
            background-color: #DBEAFE;
        }

        .draggable-row.drag-over {
            background-color: #E0E7FF;
            box-shadow: inset 0 -3px 0 0 #3B82F6;
        }

        .drag-handle {
            user-select: none;
            transition: color 0.2s ease;
        }

        .draggable-row:hover .drag-handle {
            color: #3B82F6;
        }

        .draggable-row.dragging .drag-handle {
            cursor: grabbing;
        }
    </style>
    <!-- Quill Rich Text Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>

        <main class="content">
            <div id="contentHeader" class="content-header">
                <h1>Articles</h1>
                <p>Manage the Article for actionable support for FAQ</p>
            </div>

            <div id="filtersSection" class="filters">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search articles..." value="<?= htmlspecialchars($search) ?>">
                    <div id="searchSuggestions" class="search-suggestions"></div>
                </div>
                
                <div class="filter-group">
                    <label for="filterMainCategory">Category:</label>
                    <select id="filterMainCategory">
                        <option value="">All Categories</option>
                        <?php foreach ($main_categories as $cat_id => $cat_name): ?>
                            <option value="<?= htmlspecialchars($cat_id) ?>" <?= $filter_main_category === (string)$cat_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filterCategory">Subcategory:</label>
                    <select id="filterCategory" onchange="applyFilters()" data-selected-value="<?= htmlspecialchars($filter_category) ?>">
                        <option value="">All Subcategories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>" <?= $filter_category === $category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="sortBy">Sort by:</label>
                    <select id="sortBy" onchange="applyFilters()">
                        <option value="date_desc" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Newest</option>
                        <option value="date_oldest" <?= $sort_by === 'date_oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="title_asc" <?= $sort_by === 'title_asc' ? 'selected' : '' ?>>Title (A-Z)</option>
                        <option value="title_desc" <?= $sort_by === 'title_desc' ? 'selected' : '' ?>>Title (Z-A)</option>
                        <option value="category_asc" <?= $sort_by === 'category_asc' ? 'selected' : '' ?>>Category (A-Z)</option>
                    </select>
                </div>
                
                <button type="button" class="new-article-btn" onclick="toggleCreateForm()">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span>New Article</span>
                </button>
            </div>

            <!-- Article Form Container (Inline) -->
            <div id="articleFormContainer" style="display: none; background-color: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 2rem; margin-bottom: 2rem; width: 100%; max-width: 1500px; margin-left: auto; margin-right: auto; min-height: 95vh; overflow-y: auto; transition: all 0.3s ease;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid #E5E7EB;">
                    <h2 id="modalTitle" style="font-size: 1.75rem; font-weight: 700; color: #1F2937; margin: 0;">New Article</h2>
                    <button type="button" onclick="toggleCreateForm()" style="background: none; border: none; cursor: pointer; padding: 0.5rem; display: flex; align-items: center; justify-content: center;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <form id="articleModalForm" enctype="multipart/form-data" style="display: flex; flex-direction: column; height: 100%;">
                    <input type="hidden" id="article_id" name="id">
                    
                    <!-- Two Column Layout - Responsive Grid -->
                    <div class="form-row" id="twoColumnLayout" style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem; align-items: start; width: 100%;">
                        <!-- Left Column: Title and Type-Specific Fields -->
                        <div class="form-column-left" style="flex: 1;">
                            <div class="form-group" style="position: relative;">
                                <label for="modal_title">Title</label>
                                <input type="text" id="modal_title" name="title" placeholder="Enter Article Title" required>
                                <div id="titleSuggestions" class="title-suggestions"></div>
                            </div>

                            <!-- Simple Question Fields -->
                            <div id="simpleQuestionFields" class="article-type-fields" style="display: none; padding: 1.5rem; background: #F9FAFB; border-radius: 8px; border: 1px solid #E5E7EB; min-height: 600px; overflow-y: auto;">
                                <div class="form-group">
                                    <label for="modal_question">Question</label>
                                    <div id="questionEditor" style="background: white; border: 1px solid #E5E7EB; border-radius: 4px; min-height: 190px; max-height: 400px;"></div>
                                    <textarea id="modal_question" name="question" style="display: none;"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="modal_answer" style="margin-top: 1.5rem; display: block;">Answer</label>
                                    <div id="answerEditor" style="background: white; border: 1px solid #E5E7EB; border-radius: 4px; min-height: 190px; max-height: 500px;"></div>
                                    <textarea id="modal_answer" name="answer" style="display: none;"></textarea>
                                </div>
                                <div id="qaCurrentImageSection" style="display: none; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #E5E7EB;">
                                    <label>Current Image</label>
                                    <div style="margin-top: 1rem; padding: 1rem; background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; text-align: center;">
                                        <img id="qaImageDisplay" src="" alt="QA Image" style="width: 100%; height: auto; border-radius: 4px; object-fit: contain; display: block; max-height: 300px;">
                                    </div>
                                </div>
                            </div>

                            <!-- Step-by-Step Fields -->
                            <div id="stepByStepFields" class="article-type-fields" style="display: none; padding: 1.5rem; background: #F9FAFB; border-radius: 8px; border: 1px solid #E5E7EB;">
                                <div class="form-group">
                                    <label for="modal_introduction">Introduction</label>
                                    <div id="introductionEditor" style="background: white; border: 1px solid #E5E7EB; border-radius: 4px; min-height: 120px; max-height: 200px; margin-bottom: 1.5rem;"></div>
                                    <textarea id="modal_introduction" name="introduction" style="display: none;"></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Steps</label>
                                    <div id="stepsContainer" style="display: flex; flex-direction: column; gap: 1rem;">
                                        <div class="step-item" data-step="1">
                                            <input type="text" placeholder="Step 1 Title" class="step-title" name="step_1_title" style="width: 100%; padding: 0.5rem; margin-bottom: 0.5rem; border: 1px solid #E5E7EB; border-radius: 4px; font-size: 0.875rem;">
                                            <div class="step-editor" data-step="1" style="background: white; border: 1px solid #E5E7EB; border-radius: 4px; min-height: 100px; max-height: 200px; margin-bottom: 0.5rem;"></div>
                                            <textarea placeholder="Step 1 Description" class="step-description" name="step_1_description" style="display: none;"></textarea>
                                            <div style="margin-top: 1rem; padding: 1rem; background: #F3F4F6; border-radius: 6px;">
                                                <label for="step_1_file" style="display: block; font-weight: 500; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">Upload File (Optional)</label>
                                                <input type="file" id="step_1_file" class="step-file" name="step_1_file" accept="image/*,.pdf,.doc,.docx" style="width: 100%; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 4px; background: white; cursor: pointer; font-size: 0.875rem;">
                                                <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: #6B7280;">Supported: JPG, PNG, GIF, PDF, DOC, DOCX (Max 5MB)</p>
                                            </div>
                                        </div>
                                        
                                        <!-- Add Step Button (inside steps container to stay at bottom) -->
                                        <div style="margin-top: 0.5rem;">
                                            <button type="button" class="btn-secondary" onclick="addStep()">Add Step</button>
                                        </div>
                                    </div>
                                    
                                    <!-- Current Image Display -->
                                    <div id="currentImageSection" style="display: none; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #E5E7EB;">
                                        <label>Current Image</label>
                                        <div style="margin-top: 1rem; padding: 1rem; background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; text-align: center;">
                                            <img id="articleImageDisplay" src="" alt="Article Image" style="width: 100%; height: auto; border-radius: 4px; object-fit: contain; display: block;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Standard Fields -->
                            <div id="standardFields" class="article-type-fields" style="display: none; padding: 1.5rem; background: #F9FAFB; border-radius: 8px; border: 1px solid #E5E7EB;">
                                <div class="form-group">
                                    <label for="modal_description">Description</label>
                                    <div id="descriptionEditor" style="background: white; border: 1px solid #E5E7EB; border-radius: 4px; min-height: 200px; overflow-y: auto;"></div>
                                    <textarea id="modal_description" name="description" style="display: none;"></textarea>
                                </div>
                                <div style="margin-top: 1rem; padding: 1rem; background: #F3F4F6; border-radius: 6px;">
                                    <label for="modal_standard_image" style="display: block; font-weight: 500; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">Upload Image (Optional)</label>
                                    <input type="file" id="modal_standard_image" name="standard_image" accept="image/*" style="width: 100%; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 4px; background: white; cursor: pointer; font-size: 0.875rem;">
                                    <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: #6B7280;">Supported: JPG, PNG, GIF (Max 5MB)</p>
                                </div>
                                <div id="standardCurrentImageSection" style="display: none; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #E5E7EB;">
                                    <label>Current Image</label>
                                    <div style="margin-top: 1rem; padding: 1rem; background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; text-align: center;">
                                        <img id="standardImageDisplay" src="" alt="Standard Image" style="width: 100%; height: auto; border-radius: 4px; object-fit: contain; display: block; max-height: 300px;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Article Type, Category, Author -->
                        <div class="form-column-right" style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%;">
                            <div class="form-group">
                                <label for="modal_type">Article Type</label>
                                <select id="modal_type" name="type" required>
                                    <option value="">Select Article Type</option>
                                    <option value="simple_question">Simple Question</option>
                                    <option value="step_by_step">Step-by-Step</option>
                                    <option value="standard">Standard</option>
                                </select>
                            </div>

                            <div id="categoryDropdownGroup" class="form-group">
                                <label for="modal_category_id">Category</label>
                                <select id="modal_category_id" name="category_id" data-category-id="" required>
                                    <option value="">Select Category</option>
                                    <?php 
                                    // Get category list with IDs
                                    $cat_sql = "SELECT category_id, category_name FROM categories ORDER BY category_name ASC";
                                    $cat_result = $conn->query($cat_sql);
                                    if ($cat_result && $cat_result->num_rows > 0) {
                                        while ($cat_row = $cat_result->fetch_assoc()) {
                                            echo '<option value="' . htmlspecialchars($cat_row['category_name']) . '" data-cat-id="' . intval($cat_row['category_id']) . '">' . htmlspecialchars($cat_row['category_name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="modal_category">Subcategory</label>
                                <select id="modal_category" name="category" data-category-id="" required>
                                    <option value="">Select Subcategory</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="modal_tags">Tags (Max 3)</label>
                                <div id="tagsSelector" style="display: flex; flex-direction: column; gap: 0.75rem;">
                                    <input type="text" id="tagSearchInput" placeholder="Search tags..." style="width: 100%; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 4px; font-size: 0.875rem;">
                                    <div id="availableTagsContainer" style="display: flex; flex-wrap: wrap; gap: 0.5rem; max-height: 150px; overflow-y: auto; padding: 0.5rem; border: 1px solid #E5E7EB; border-radius: 4px; background: #F9FAFB;">
                                        <!-- Tags will be loaded here via AJAX -->
                                    </div>
                                    <input type="hidden" id="selected_tags" name="tags" value="">
                                    <div id="selectedTagsDisplay" style="display: flex; flex-wrap: wrap; gap: 0.5rem; min-height: 32px; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 4px; background: white; font-size: 0.875rem;">
                                        <span style="color: #9CA3AF; align-self: center;">No tags selected</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="modal_status">Status</label>
                                <select id="modal_status" name="status" required>
                                    <option value="Publish" selected>Publish (Draft - Hidden)</option>
                                    <option value="Published">Published (Live)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Action Buttons -->
                    <div id="formButtons" style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #E5E7EB;">
                        <button type="button" class="btn-cancel" onclick="toggleCreateForm()" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Cancel</button>
                        <button type="button" class="btn-create" id="createAddNewBtn" onclick="submitAndCreateNew()" style="padding: 0.5rem 1rem; font-size: 0.875rem;" title="Create article and immediately add another">Create & Add New</button>
                        <button type="submit" class="btn-create" id="modalSubmitBtn" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Create Article</button>
                    </div>
                </form>
            </div>

            <div id="tableContainer" class="table-container">
                <?php 
                    $total_articles = $result ? $result->num_rows : 0;
                    if ($search || $filter_main_category || $filter_category || $sort_by !== 'date_desc'):
                ?>
                <div class="results-info">
                    Showing <?= $total_articles ?> 
                    <?= $total_articles === 1 ? 'article' : 'articles' ?>
                    <?php if ($search): ?>
                        matching "<?= htmlspecialchars($search) ?>"
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">
                                <?php if (!empty($filter_main_category) && !empty($filter_category)): ?>
                                    <span title="Drag rows to reorder">⋮⋮</span>
                                <?php endif; ?>
                            </th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Subcategory</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="articlesTable">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php 
                            $counter = 1;
                            while($row = $result->fetch_assoc()): 
                                // Detect article type from content
                                $content = $row['content'];
                                if (strpos($content, 'Q: ') === 0 && strpos($content, 'A: ') !== false) {
                                    $article_type = 'Simple Question';
                                } else if (strpos($content, 'Step ') !== false) {
                                    $article_type = 'Step-by-Step';
                                } else {
                                    $article_type = 'Standard';
                                }
                                
                                // Format date
                                $article_date = !empty($row['article_date']) ? date('M d, Y', strtotime($row['article_date'])) : date('M d, Y');
                                
                                // Get status
                                $status = $row['status'] ?? 'Publish';
                                $status_badge_class = $status === 'Published' ? 'status-badge-published' : 'status-badge-draft';
                                
                                // Determine if this row is draggable (requires both category and subcategory filters)
                                $is_draggable = (!empty($filter_main_category) && !empty($filter_category)) ? 'true' : 'false';
                                $draggable_class = (!empty($filter_main_category) && !empty($filter_category)) ? 'draggable-row' : '';
                                $row_style = (!empty($filter_main_category) && !empty($filter_category)) ? 'cursor: grab;' : 'cursor: pointer;';
                            ?>
                            <tr class="article-row <?= $draggable_class ?>" draggable="<?= $is_draggable ?>" data-title="<?= htmlspecialchars(strtolower($row['title'])) ?>" data-type="<?= htmlspecialchars($article_type) ?>" data-article-id="<?= htmlspecialchars($row['article_id']) ?>" data-image="<?= htmlspecialchars($row['article_image'] ?? '') ?>" style="<?= $row_style ?>">
                                <!-- Drag Handle -->
                                <td style="text-align: center; padding: 0.75rem; user-select: none;" onclick="event.stopPropagation()">
                                    <?php if (!empty($filter_main_category) && !empty($filter_category)): ?>
                                        <span class="drag-handle" style="cursor: grab; font-size: 1.2em; color: #9CA3AF; display: flex; justify-content: center;">⋮⋮</span>
                                    <?php endif; ?>
                                </td>
                                <td onclick="openArticlePreviewDirect(<?= $row['article_id'] ?>)"><strong><?php 
                                    $displayTitle = htmlspecialchars($row['title']);
                                    echo strlen($displayTitle) > 50 ? substr($displayTitle, 0, 50) . '...' : $displayTitle;
                                ?></strong></td>
                                <td onclick="openArticlePreviewDirect(<?= $row['article_id'] ?>)"><span class="type-badge"><?= htmlspecialchars($article_type) ?></span></td>
                                <td onclick="openArticlePreviewDirect(<?= $row['article_id'] ?>)"><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                                <td onclick="openArticlePreviewDirect(<?= $row['article_id'] ?>)"><?= htmlspecialchars($row['subcategory_name'] ?? 'Uncategorized') ?></td>
                                <td onclick="openArticlePreviewDirect(<?= $row['article_id'] ?>)"><?= htmlspecialchars($article_date) ?></td>
                                <td onclick="event.stopPropagation()">
                                    <select class="status-dropdown" data-article-id="<?= $row['article_id'] ?>" onchange="updateArticleStatus(this)">
                                        <option value="Publish" <?= $status === 'Publish' ? 'selected' : '' ?>>Publish</option>
                                        <option value="Published" <?= $status === 'Published' ? 'selected' : '' ?>>Published</option>
                                    </select>
                                </td>
                            </tr>
                            <?php 
                            $counter++;
                            endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">
                                    No articles found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Table Footer with Pagination -->
                <div class="table-footer">
                    <div class="table-footer-left">
                        <span>Showing data</span>
                        <select class="per-page-select" onchange="changePerPage(this.value)">
                            <option value="5" <?php echo $per_page == 5 ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                            <?php if (!empty($filter_main_category) && !empty($filter_category)): ?>
                                <option value="<?php echo $total_records + 1000; ?>" <?php echo $per_page >= $total_records ? 'selected' : ''; ?>>All (for drag-drop)</option>
                            <?php endif; ?>
                        </select>
                        <span>out of <?php echo $total_records; ?> entries found</span>
                    </div>
                    <?php if ($per_page < $total_records): ?>
                    <div class="pagination">
                        <a href="?page=<?php echo max(1, $page - 1); ?>&per_page=<?php echo $per_page; ?>&filter_main_category=<?php echo urlencode($filter_main_category); ?>&filter_category=<?php echo urlencode($filter_category); ?>&filter_type=<?php echo urlencode($filter_type); ?>&search=<?php echo urlencode($search); ?>&sort_by=<?php echo urlencode($sort_by); ?>" 
                           class="pagination-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
                            &lt;
                        </a>
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<a href="?page=1&per_page=' . $per_page . '&filter_main_category=' . urlencode($filter_main_category) . '&filter_category=' . urlencode($filter_category) . '&filter_type=' . urlencode($filter_type) . '&search=' . urlencode($search) . '&sort_by=' . urlencode($sort_by) . '" class="pagination-btn">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="pagination-btn disabled">...</span>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active = ($i == $page) ? 'active' : '';
                            echo '<a href="?page=' . $i . '&per_page=' . $per_page . '&filter_main_category=' . urlencode($filter_main_category) . '&filter_category=' . urlencode($filter_category) . '&filter_type=' . urlencode($filter_type) . '&search=' . urlencode($search) . '&sort_by=' . urlencode($sort_by) . '" class="pagination-btn ' . $active . '">' . $i . '</a>';
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="pagination-btn disabled">...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . '&per_page=' . $per_page . '&filter_main_category=' . urlencode($filter_main_category) . '&filter_category=' . urlencode($filter_category) . '&filter_type=' . urlencode($filter_type) . '&search=' . urlencode($search) . '&sort_by=' . urlencode($sort_by) . '" class="pagination-btn">' . $total_pages . '</a>';
                        }
                        ?>
                        <a href="?page=<?php echo min($total_pages, $page + 1); ?>&per_page=<?php echo $per_page; ?>&filter_main_category=<?php echo urlencode($filter_main_category); ?>&filter_category=<?php echo urlencode($filter_category); ?>&filter_type=<?php echo urlencode($filter_type); ?>&search=<?php echo urlencode($search); ?>&sort_by=<?php echo urlencode($sort_by); ?>" 
                           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            &gt;
                        </a>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; color: #6B7280; font-size: 0.875rem; padding: 1rem;">
                        Showing all <?php echo $total_records; ?> articles - Drag and drop to reorder
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>




    <!-- Article Details Modal -->
    <div id="detailsModalOverlay" class="modal-overlay" onclick="closeDetailsModal()">
        <div class="modal-content" onclick="event.stopPropagation()" style="max-width: 800px; max-height: 90vh; overflow-y: auto; padding: 0; position: relative;">
            <div style="position: absolute; top: 1.5rem; right: 1.5rem; z-index: 10; display: flex; gap: 1rem; align-items: center;">
                <!-- 3-Dots Menu Button -->
                <div style="position: relative;">
                    <button id="articleMenuBtn" style="background: rgba(255, 255, 255, 0.2); color: white; border: none; padding: 0.5rem; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; transition: background-color 0.2s;" 
                            onclick="toggleArticleMenu(event)">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 8c1.1 0 2-0.9 2-2s-0.9-2-2-2-2 0.9-2 2 0.9 2 2 2zm0 2c-1.1 0-2 0.9-2 2s0.9 2 2 2 2-0.9 2-2-0.9-2-2-2zm0 6c-1.1 0-2 0.9-2 2s0.9 2 2 2 2-0.9 2-2-0.9-2-2-2z"/>
                        </svg>
                    </button>
                    <!-- Dropdown Menu -->
                    <div id="articleMenuDropdown" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #E5E7EB; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); width: 200px; z-index: 11;">
                        <button onclick="openArticlePreview()" style="width: 100%; padding: 1rem; text-align: left; border: none; background: none; cursor: pointer; color: #374151; font-size: 0.875rem; transition: background-color 0.2s; border-bottom: 1px solid #E5E7EB;"
                                onmouseover="this.style.backgroundColor='#F3F4F6'" 
                                onmouseout="this.style.backgroundColor='transparent'">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 0.75rem; vertical-align: middle;">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            Preview
                        </button>
                    </div>
                </div>
            </div>

            <!-- Article Header with Title -->
            <div style="background: url('assets/img/article-bg.png') center/cover; color: white; padding: 2rem;">
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <div>
                        <h1 id="detailsTitle" style="font-size: 2rem; font-weight: 700; margin: 0 0 1rem 0; line-height: 1.3;"></h1>
                        
                        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; font-size: 0.875rem; opacity: 0.95;">
                            <span id="detailsCategory-header" style="display: flex; align-items: center; gap: 0.375rem; background: rgba(255,255,255,0.2); padding: 0.375rem 0.75rem; border-radius: 20px;">
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                                    <path d="M10.5 1.5H2C0.9 1.5 0 2.4 0 3.5V16.5C0 17.6 0.9 18.5 2 18.5H18C19.1 18.5 20 17.6 20 16.5V8H10.5V1.5Z" fill="currentColor"/>
                                </svg>
                                <span id="detailsCategory-text"></span>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Tags below category -->
                    <div id="detailsTags-container" style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                        <!-- Tags will be populated here -->
                    </div>
                </div>
            </div>

            <!-- Article Meta Bar -->
            <div style="background: #F3F4F6; border-bottom: 1px solid #E5E7EB; padding: 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; gap: 2rem; align-items: center; flex-wrap: wrap;">
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 600; color: #6B7280; text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Type</span>
                        <span id="detailsType-text" style="font-weight: 600; color: #374151;"></span>
                    </div>
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 600; color: #6B7280; text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Published Date</span>
                        <span id="detailsDate-text" style="font-weight: 600; color: #374151;"></span>
                    </div>
                </div>
            </div>

            <!-- Article Content -->
            <div style="padding: 3rem 2rem;">
                <!-- Standard Article View -->
                <div id="standardContent">
                    <div style="display: flex; flex-direction: column; gap: 2rem;">
                        <!-- Image Section -->
                        <div id="standardImageSection" style="display: none; text-align: center;">
                            <img id="detailsContent-image" src="" alt="Article Image" style="width: 50%; height: auto; border-radius: 8px; object-fit: contain; max-height: 300px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); margin: 0 auto;">
                        </div>
                        <!-- Description Section -->
                        <div>
        
                            <div id="detailsContent-text" style="font-size: 1rem; line-height: 1.8; color: #374151; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;"></div>
                        </div>
                    </div>
                </div>

                <!-- Simple Question Article View -->
                <div id="simpleQuestionContent" style="display: none;">
                    <div>
                        <!-- Question and Answer Sections -->
                        <div style="display: flex; flex-direction: column; gap: 2rem;">
                            <!-- Question Section -->
                            <div style="padding: 2rem; background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%); border-left: 5px solid #3B82F6; border-radius: 8px; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.08);">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="color: #3B82F6; flex-shrink: 0;">
                                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                        <path d="M12 17V11M12 7H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                    <h3 style="font-size: 1.125rem; font-weight: 700; color: #1E40AF; margin: 0;">Question</h3>
                                </div>
                                <p id="simpleQuestionText" style="font-size: 1.05rem; line-height: 1.9; color: #1F2937; margin: 0; white-space: pre-wrap;"></p>
                            </div>

                            <!-- Answer Section -->
                            <div style="padding: 2rem; background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%); border-left: 5px solid #16A34A; border-radius: 8px; box-shadow: 0 2px 8px rgba(22, 163, 74, 0.08);">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="color: #16A34A; flex-shrink: 0;">
                                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                        <path d="M8 12L11 15L16 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <h3 style="font-size: 1.125rem; font-weight: 700; color: #166534; margin: 0;">Answer</h3>
                                </div>
                                <p id="simpleAnswerText" style="font-size: 1.05rem; line-height: 1.9; color: #1F2937; margin: 0; white-space: pre-wrap;"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 1rem; padding: 1.5rem 2rem; border-top: 1px solid #E5E7EB; background: #F9FAFB; justify-content: flex-end;">
                <button type="button" class="btn-cancel" onclick="closeDetailsModal()">Close</button>
                <button type="button" class="btn-create" onclick="editArticleFromDetails()" style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                        <path d="M2.5 17.5H5.75L15.8167 7.43333C16.0833 7.16667 16.2222 6.8125 16.2222 6.45833C16.2222 6.10417 16.0833 5.75 15.8167 5.48333L13.3667 2.98333C13.1 2.71667 12.75 2.57812 12.3917 2.57812C12.0333 2.57812 11.6792 2.71667 11.4125 2.98333L1.34583 13.05V16.3C1.34583 16.75 1.71667 17.1208 2.16667 17.1208L2.5 17.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11.25 6.875L13.125 8.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Edit
                </button>
                <button id="deleteBtn" type="button" style="padding: 0.75rem 1.5rem; display: flex; align-items: center; gap: 0.5rem; background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; border-radius: 8px; font-weight: 600; font-size: 0.875rem; cursor: pointer; transition: background 0.2s, box-shadow 0.2s; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);" onmouseover="this.style.background='#FECACA'; this.style.boxShadow='0 4px 12px rgba(0, 0, 0, 0.15)';" onmouseout="this.style.background='#FEE2E2'; this.style.boxShadow='0 1px 3px rgba(0, 0, 0, 0.1)';">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                        <path d="M2.5 5H17.5M8.33337 9.16667V14.1667M11.6667 9.16667V14.1667M3.33337 5L4.16671 15.8333C4.25004 17.0667 5.33337 18 6.58337 18H13.4167C14.6667 18 15.75 17.0667 15.8334 15.8333L16.6667 5M7.08337 5V3.66667C7.08337 3.29167 7.375 3 7.75004 3H12.25C12.625 3 12.9167 3.29167 12.9167 3.66667V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmationOverlay" class="modal-overlay" style="display: none; z-index: 9999;">
        <div class="modal-content" onclick="event.stopPropagation()" style="background: white; border-radius: 12px; padding: 2rem; max-width: 400px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); animation: slideUp 0.3s ease;">
            <div style="margin-bottom: 1.5rem;">
                <div style="width: 60px; height: 60px; background: #FEE2E2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin: 0 0 0.5rem 0;">Delete Article?</h3>
                <p style="font-size: 0.875rem; color: #6B7280; margin: 0;">Are you sure you want to delete this article? This action cannot be undone.</p>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button id="deleteConfirmCancel" type="button" style="padding: 0.75rem 1.5rem; background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                    Cancel
                </button>
                <button id="deleteConfirmYes" type="button" style="padding: 0.75rem 1.5rem; background: #DC2626; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Article Preview Modal (Client-Side View) -->
    <div id="previewModalOverlay" class="modal-overlay" style="display: none; background: rgba(0, 0, 0, 0.5);">
        <div class="modal-content" onclick="event.stopPropagation()" style="max-width: 90%; max-height: 90vh; width: 90vw; height: 90vh; padding: 0; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column;">
            <!-- Preview Header -->
            <div style="background: linear-gradient(135deg, #3B82F6 0%, #1E40AF 100%); color: white; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; gap: 1rem;">
                <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700;">Client-Side Preview</h2>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <!-- Edit Button -->
                    <button type="button" onclick="editArticleFromPreview()" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: rgba(255, 255, 255, 0.2); color: white; border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 8px; font-weight: 600; font-size: 0.875rem; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="white" stroke-width="1.5">
                            <path d="M2.5 17.5H5.75L15.8167 7.43333C16.0833 7.16667 16.2222 6.8125 16.2222 6.45833C16.2222 6.10417 16.0833 5.75 15.8167 5.48333L13.3667 2.98333C13.1 2.71667 12.75 2.57812 12.3917 2.57812C12.0333 2.57812 11.6792 2.71667 11.4125 2.98333L1.34583 13.05V16.3C1.34583 16.75 1.71667 17.1208 2.16667 17.1208L2.5 17.5Z" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M11.25 6.875L13.125 8.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Edit
                    </button>

                    <!-- Delete Button -->
                    <button type="button" onclick="deleteArticleFromPreview()" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: linear-gradient(90deg, #3B82F6 0%, #F97316 100%); color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 0.875rem; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);" onmouseover="this.style.boxShadow='0 4px 12px rgba(0, 0, 0, 0.25)'" onmouseout="this.style.boxShadow='0 2px 8px rgba(0, 0, 0, 0.15)'">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="white" stroke-width="1.5">
                            <path d="M2.5 5H17.5M8.33337 9.16667V14.1667M11.6667 9.16667V14.1667M3.33337 5L4.16671 15.8333C4.25004 17.0667 5.33337 18 6.58337 18H13.4167C14.6667 18 15.75 17.0667 15.8334 15.8333L16.6667 5M7.08337 5V3.66667C7.08337 3.29167 7.375 3 7.75004 3H12.25C12.625 3 12.9167 3.29167 12.9167 3.66667V5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Delete
                    </button>

                    <!-- Close Button -->
                    <button onclick="closePreviewModal()" style="background: rgba(255, 255, 255, 0.2); color: white; border: none; cursor: pointer; padding: 0.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; transition: background-color 0.2s;"
                            onmouseover="this.style.backgroundColor='rgba(255, 255, 255, 0.3)'"
                            onmouseout="this.style.backgroundColor='rgba(255, 255, 255, 0.2)'">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6L6 18M6 6L18 18"/>
                        </svg>
                    </button>
                </div>
            </div>
            <!-- Preview Content (iframe) -->
            <iframe id="previewIframe" style="flex: 1; border: none; width: 100%; height: 100%;"></iframe>
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

        .status-dropdown {
            background: linear-gradient(90deg, #3B82F6 0%, #F97316 100%);
            color: white;
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>

    <script src="modal.js"></script>
    <script>
        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');
        const articlesTable = document.getElementById('articlesTable');
        const articleRows = document.querySelectorAll('.article-row');

        // Apply filters and redirect
        function applyFilters() {
            const search = searchInput.value.trim();
            const filterMainCategory = document.getElementById('filterMainCategory').value;
            const filterCategory = document.getElementById('filterCategory').value;
            const sortBy = document.getElementById('sortBy').value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (filterMainCategory) params.append('filter_main_category', filterMainCategory);
            if (filterCategory) params.append('filter_category', filterCategory);
            if (sortBy !== 'date_desc') params.append('sort_by', sortBy);

            const url = 'articles.php' + (params.toString() ? '?' + params.toString() : '');
            window.location.href = url;
        }

        // Handle category filter changes - dynamically update subcategories
        const filterMainCategory = document.getElementById('filterMainCategory');
        const filterCategory = document.getElementById('filterCategory');

        if (filterMainCategory) {
            filterMainCategory.addEventListener('change', function() {
                const categoryId = this.value;
                
                if (categoryId) {
                    // Fetch subcategories for this category
                    fetch(`get_subcategories.php?category_id=${encodeURIComponent(categoryId)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.data) {
                                // Clear current subcategories
                                filterCategory.innerHTML = '<option value="">All Subcategories</option>';
                                
                                // Add new subcategories
                                data.data.forEach(sub => {
                                    const option = document.createElement('option');
                                    option.value = sub.subcategory_name;
                                    option.textContent = sub.subcategory_name;
                                    filterCategory.appendChild(option);
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching subcategories:', error);
                        });
                } else {
                    // If no category selected, load all subcategories
                    loadAllSubcategories();
                }
            });
        }

        // Load all subcategories (when category is cleared)
        function loadAllSubcategories() {
            // Get all unique subcategories from the table rows
            const allRows = document.querySelectorAll('.article-row');
            const subcategories = new Set();
            
            allRows.forEach(row => {
                const subcattext = row.cells[4]?.textContent.trim();
                if (subcattext && subcattext !== '') {
                    subcategories.add(subcattext);
                }
            });

            // Update dropdown
            filterCategory.innerHTML = '<option value="">All Subcategories</option>';
            Array.from(subcategories).sort().forEach(subcat => {
                const option = document.createElement('option');
                option.value = subcat;
                option.textContent = subcat;
                filterCategory.appendChild(option);
            });
        }

        // Live search functionality - search across all pages
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();

            if (query.length > 0) {
                // Fetch matching articles from all pages via AJAX
                fetch(`search_articles_ajax.php?search=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        const suggestions = [];
                        const uniqueTitles = new Set();

                        // Build suggestions from all matching articles (across all pages)
                        if (data.articles && data.articles.length > 0) {
                            data.articles.forEach(article => {
                                // Create unique suggestion entries
                                if (!uniqueTitles.has(article.title)) {
                                    uniqueTitles.add(article.title);
                                    suggestions.push(article.title);
                                }
                            });
                        }

                        // Show suggestions dropdown
                        if (suggestions.length > 0) {
                            searchSuggestions.innerHTML = suggestions.map(suggestion => 
                                `<div class="suggestion-item" onclick="filterByTitle('${suggestion.replace(/'/g, "\\'")}')">${suggestion}</div>`
                            ).join('');
                            searchSuggestions.classList.add('active');
                        } else {
                            searchSuggestions.innerHTML = '<div class="suggestion-item no-results">No articles found</div>';
                            searchSuggestions.classList.add('active');
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        searchSuggestions.classList.remove('active');
                    });
            } else {
                // Hide suggestions if search is empty
                searchSuggestions.classList.remove('active');
            }
        });

        // Click on suggestion to filter
        function filterByTitle(title) {
            searchInput.value = title;
            applyFilters();
        }

        // Title input suggestions for duplicate detection
        const modalTitleInput = document.getElementById('modal_title');
        const titleSuggestions = document.getElementById('titleSuggestions');

        if (modalTitleInput && titleSuggestions) {
            modalTitleInput.addEventListener('input', function() {
                const query = this.value.trim();

                if (query.length > 2) {
                    // Fetch matching article titles from AJAX endpoint
                    fetch(`search_article_titles_ajax.php?search=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.titles && data.titles.length > 0) {
                                titleSuggestions.innerHTML = data.titles.map(title => {
                                    // Highlight the matching part
                                    const regex = new RegExp(`(${query})`, 'gi');
                                    const highlightedTitle = title.replace(regex, '<span class="suggestion-highlight">$1</span>');
                                    return `<div class="title-suggestion-item" onclick="setModalTitle('${title.replace(/'/g, "\\'")}')" title="${title}"><span class="suggestion-title">${highlightedTitle}</span></div>`;
                                }).join('');
                                titleSuggestions.classList.add('active');
                            } else {
                                titleSuggestions.innerHTML = '<div class="title-suggestion-item no-results">No existing articles with this title</div>';
                                titleSuggestions.classList.add('active');
                            }
                        })
                        .catch(error => {
                            console.error('Title search error:', error);
                            titleSuggestions.classList.remove('active');
                        });
                } else if (query.length === 0) {
                    titleSuggestions.classList.remove('active');
                }
            });

            // Function to set modal title when suggestion is clicked
            window.setModalTitle = function(title) {
                modalTitleInput.value = title;
                titleSuggestions.classList.remove('active');
            };

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (e.target !== modalTitleInput && !titleSuggestions.contains(e.target)) {
                    titleSuggestions.classList.remove('active');
                }
            });
        }

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== searchInput && e.target.parentElement !== searchSuggestions) {
                searchSuggestions.classList.remove('active');
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
                window.history.replaceState({}, document.title, 'articles.php');
            }
        });

        // Change per-page items
        function changePerPage(value) {
            const params = new URLSearchParams(window.location.search);
            params.set('per_page', value);
            params.set('page', '1'); // Reset to first page
            window.location.search = params.toString();
        }


        // Parse Simple Question format
        function parseSimpleQuestion(content) {
            const questionMatch = content.match(/Q:\s*([\s\S]*?)(?=A:|$)/i);
            const answerMatch = content.match(/A:\s*([\s\S]*?)$/i);

            return {
                question: questionMatch ? questionMatch[1].trim() : '',
                answer: answerMatch ? answerMatch[1].trim() : ''
            };
        }

        // Update details modal for Simple Question format
        function displaySimpleQuestionFormat(content, articleType, imageUrl) {
            const standardContent = document.getElementById('standardContent');
            const simpleQuestionContent = document.getElementById('simpleQuestionContent');
            const imageSection = document.getElementById('standardImageSection');
            const imageElement = document.getElementById('detailsContent-image');

            if (articleType === 'Simple Question') {
                const parsed = parseSimpleQuestion(content);
                
                // Strip HTML tags for clean text display
                const stripHtml = (html) => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    let text = tempDiv.textContent || tempDiv.innerText || '';
                    text = text.replace(/<[^>]*>/g, '').trim();
                    return text;
                };
                
                const cleanQuestion = stripHtml(parsed.question);
                const cleanAnswer = stripHtml(parsed.answer);
                
                document.getElementById('simpleQuestionText').textContent = cleanQuestion;
                document.getElementById('simpleAnswerText').textContent = cleanAnswer;
                
                standardContent.style.display = 'none';
                simpleQuestionContent.style.display = 'block';
            } else {
                document.getElementById('detailsContent-text').innerHTML = content;
                
                // Display image if available
                if (imageUrl && imageUrl.trim() !== '') {
                    imageSection.style.display = 'block';
                    imageElement.src = imageUrl;
                    imageElement.alt = 'Article Image';
                } else {
                    imageSection.style.display = 'none';
                }
                
                standardContent.style.display = 'block';
                simpleQuestionContent.style.display = 'none';
            }
        }

        // Parse Step-by-Step format
        function parseStepByStep(content) {
            const stepsRegex = /Step\s+(\d+):\s*([\s\S]*?)(?=Step\s+\d+:|$)/gi;
            const steps = [];
            let match;
            
            while ((match = stepsRegex.exec(content)) !== null) {
                steps.push({
                    number: match[1],
                    content: match[2].trim()
                });
            }
            
            return { steps };
        }

        // Display Step-by-Step format
        function displayStepByStepFormat(article) {
            const standardContent = document.getElementById('standardContent');
            const simpleQuestionContent = document.getElementById('simpleQuestionContent');
            
            const content = article.content || '';
            const parsed = parseStepByStep(content);
            
            let html = '';
            
            // Add introduction section if available
            if (article.introduction && article.introduction.trim() !== '') {
                html += `
                    <div style="margin-bottom: 2rem;">
                        <h3 style="font-size: 1rem; font-weight: 700; color: #374151; margin: 0 0 0.5rem 0;">Introduction</h3>
                        <div style="font-size: 0.95rem; line-height: 1.8; color: #4B5563;">
                            ${article.introduction}
                        </div>
                    </div>
                `;
            }
            
            // Steps section
            if (parsed.steps.length > 0) {
                html += `<div>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #1F2937; margin: 0 0 1.5rem 0;">Steps</h3>
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">`;
                
                parsed.steps.forEach((step, index) => {
                    html += `
                        <div style="display: flex; gap: 1.25rem;">
                            <div style="display: flex; align-items: center; justify-content: center; min-width: 40px; width: 40px; height: 40px; background: #3B82F6; color: white; border-radius: 50%; font-weight: 700; font-size: 1rem;">
                                ${step.number}
                            </div>
                            <div style="flex: 1; padding: 1rem; background: #F9FAFB; border-radius: 8px; border-left: 3px solid #3B82F6;">
                                <p style="font-size: 1rem; line-height: 1.8; color: #374151; margin: 0; white-space: pre-wrap;">${step.content}</p>
                            </div>
                        </div>
                    `;
                });
                
                html += `</div></div>`;
            }
            
            document.getElementById('detailsContent-text').innerHTML = html;
            standardContent.style.display = 'block';
            simpleQuestionContent.style.display = 'none';
        }

        // Tag search functionality
        function initTagSearch() {
            const searchInput = document.getElementById('tagSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', filterTags);
            }
        }

        function filterTags() {
            const searchInput = document.getElementById('tagSearchInput');
            const searchTerm = searchInput.value.toLowerCase().trim();
            const tagWrappers = document.querySelectorAll('#availableTagsContainer .tag-checkbox-wrapper');
            let visibleCount = 0;

            tagWrappers.forEach(wrapper => {
                const tagName = wrapper.querySelector('span').textContent.toLowerCase();
                if (tagName.includes(searchTerm)) {
                    wrapper.classList.remove('hidden');
                    visibleCount++;
                } else {
                    wrapper.classList.add('hidden');
                }
            });

            // Show "no results" message if no tags match
            let noResultsMsg = document.getElementById('tagsNoResults');
            if (visibleCount === 0 && tagWrappers.length > 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'tagsNoResults';
                    noResultsMsg.className = 'tag-search-no-results';
                    noResultsMsg.textContent = 'No tags found matching your search';
                    document.getElementById('availableTagsContainer').appendChild(noResultsMsg);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        }

        // Update loadAvailableTags to initialize search
        const originalLoadAvailableTags = loadAvailableTags;
        function loadAvailableTags() {
            fetch('get_tags.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('availableTagsContainer');
                    if (data.tags && data.tags.length > 0) {
                        container.innerHTML = data.tags.map(tag => `
                            <label class="tag-checkbox-wrapper" title="${tag.tag_name}">
                                <input type="checkbox" value="${tag.tag_id}" data-tag-name="${tag.tag_name}" onchange="updateSelectedTags()">
                                <span>${tag.tag_name}</span>
                            </label>
                        `).join('');
                        initTagSearch();
                    } else {
                        container.innerHTML = '<span style="color: #9CA3AF; padding: 0.5rem;">No tags available. Create tags in the Category page first.</span>';
                    }
                })
                .catch(error => {
                    console.error('Error loading tags:', error);
                });
        }

        // Auto-open details modal if article_id parameter is present
        window.addEventListener('DOMContentLoaded', function() {
            const articleId = new URLSearchParams(window.location.search).get('article_id');
            if (articleId) {
                showArticleDetails(parseInt(articleId), { stopPropagation: function() {} });
            }
        });

        // Initialize Quill Editors - Make it global
        window.quillEditors = {
            description: null,
            question: null,
            answer: null,
            introduction: null,
            steps: {}
        };
        
        // Configure Quill to allow iframe tags for embedded content
        var Embed = Quill.import('blots/embed');
        class IframeBlot extends Embed {
            static create(value) {
                let node = super.create();
                node.setAttribute('src', value);
                node.setAttribute('frameborder', '0');
                node.setAttribute('allowfullscreen', 'true');
                node.setAttribute('style', 'width: 100%; height: 500px; border: 1px solid #E5E7EB; border-radius: 4px;');
                return node;
            }
            static value(node) {
                return node.getAttribute('src');
            }
        }
        IframeBlot.blotName = 'iframe';
        IframeBlot.tagName = 'iframe';
        Quill.register(IframeBlot);
        
        function initializeQuillEditor() {
            const editorConfig = {
                theme: 'snow',
                modules: {
                    clipboard: {
                        matchVisual: false
                    },
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'font': [] }, { 'size': ['small', false, 'large', 'huge'] }],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'header': 1 }, { 'header': 2 }],
                        ['blockquote', 'code-block'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'script': 'sub'}, { 'script': 'super' }],
                        [{ 'align': [] }],
                        ['link', 'video'],
                        ['clean']
                    ]
                }
            };

            // Helper function to check if Quill is already initialized
            function hasQuill(element) {
                return element && element.querySelector('.ql-toolbar') !== null;
            }

            // Initialize Description Editor
            const descriptionEditor = document.getElementById('descriptionEditor');
            if (descriptionEditor && !hasQuill(descriptionEditor.parentElement)) {
                if (!window.quillEditors.description) {
                    window.quillEditors.description = new Quill('#descriptionEditor', {
                        ...editorConfig,
                        placeholder: 'Enter your Description here...'
                    });
                    window.quillEditors.description.on('text-change', function() {
                        document.getElementById('modal_description').value = window.quillEditors.description.root.innerHTML;
                    });
                }
            }

            // Initialize Question Editor
            const questionEditor = document.getElementById('questionEditor');
            if (questionEditor && !hasQuill(questionEditor.parentElement)) {
                if (!window.quillEditors.question) {
                    window.quillEditors.question = new Quill('#questionEditor', {
                        ...editorConfig,
                        placeholder: 'Enter the question...'
                    });
                    window.quillEditors.question.on('text-change', function() {
                        const textarea = document.getElementById('modal_question');
                        if (textarea) {
                            textarea.value = window.quillEditors.question.root.innerHTML;
                            console.log('Question synced:', textarea.value.substring(0, 50));
                        }
                    });
                }
            }

            // Initialize Answer Editor
            const answerEditor = document.getElementById('answerEditor');
            if (answerEditor && !hasQuill(answerEditor.parentElement)) {
                if (!window.quillEditors.answer) {
                    window.quillEditors.answer = new Quill('#answerEditor', {
                        ...editorConfig,
                        placeholder: 'Enter the answer...'
                    });
                    window.quillEditors.answer.on('text-change', function() {
                        const textarea = document.getElementById('modal_answer');
                        if (textarea) {
                            textarea.value = window.quillEditors.answer.root.innerHTML;
                            console.log('Answer synced:', textarea.value.substring(0, 50));
                        }
                    });
                }
            }

            // Initialize Introduction Editor
            const introductionEditor = document.getElementById('introductionEditor');
            if (introductionEditor && !hasQuill(introductionEditor.parentElement)) {
                if (!window.quillEditors.introduction) {
                    window.quillEditors.introduction = new Quill('#introductionEditor', {
                        ...editorConfig,
                        placeholder: 'Enter introduction for the steps...'
                    });
                    window.quillEditors.introduction.on('text-change', function() {
                        const textarea = document.getElementById('modal_introduction');
                        if (textarea) {
                            textarea.value = window.quillEditors.introduction.root.innerHTML;
                        }
                    });
                }
            }

            // Initialize Step Editors
            document.querySelectorAll('.step-editor').forEach(function(editorDiv) {
                const stepNum = editorDiv.getAttribute('data-step');
                if (!hasQuill(editorDiv.parentElement) && !window.quillEditors.steps[stepNum]) {
                    window.quillEditors.steps[stepNum] = new Quill(editorDiv, {
                        ...editorConfig,
                        placeholder: 'Enter step description...'
                    });
                    window.quillEditors.steps[stepNum].on('text-change', function() {
                        document.querySelector(`textarea[name="step_${stepNum}_description"]`).value = window.quillEditors.steps[stepNum].root.innerHTML;
                    });
                }
            });
        }

        // Add custom paste handler to allow embedded content (YouTube, etc)
        function setupQuillPasteHandler(quillInstance) {
            if (!quillInstance) return;
            
            quillInstance.on('text-change', function(delta, oldDelta, source) {
                // Allow iframe tags for embedded content
                // This is handled by Quill's default behavior after we set clipboard module
            });
        }

        // Clear all editors when form is reset
        function clearQuillEditors() {
            Object.keys(window.quillEditors).forEach(function(key) {
                if (key === 'steps') {
                    Object.keys(window.quillEditors.steps).forEach(function(stepNum) {
                        if (window.quillEditors.steps[stepNum]) {
                            window.quillEditors.steps[stepNum].setContents([]);
                        }
                    });
                } else if (window.quillEditors[key]) {
                    window.quillEditors[key].setContents([]);
                }
            });
        }

        // Reinitialize Quill when form is shown
        const originalToggleCreateForm = toggleCreateForm;
        toggleCreateForm = function() {
            // Always clear context when opening the form fresh (unless it's from subcategories page)
            // The context will only persist if there's a filter_category parameter in the URL
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('filter_category')) {
                clearCategoryContext();
            }
            
            originalToggleCreateForm();
            const container = document.getElementById('articleFormContainer');
            if (container.style.display === 'block') {
                setTimeout(() => {
                    initializeQuillEditor();
                    clearQuillEditors();
                }, 100);
            }
        };

        // Also reinitialize on edit
        const originalOpenEditModal = openEditModal;
        openEditModal = function(articleId) {
            // Clear category context when editing (users should be free to change category)
            clearCategoryContext();
            
            originalOpenEditModal(articleId);
            setTimeout(() => {
                initializeQuillEditor();
            }, 200);
        };

        // Toggle Article Menu Dropdown
        function toggleArticleMenu(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('articleMenuDropdown');
            const isOpen = dropdown.style.display === 'block';
            dropdown.style.display = isOpen ? 'none' : 'block';
        }

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('articleMenuDropdown');
            const menuBtn = document.getElementById('articleMenuBtn');
            if (menuBtn && e.target !== menuBtn && !menuBtn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Open Article Preview Directly (without details modal)
        function openArticlePreviewDirect(articleId) {
            if (articleId) {
                // Load client-side preview in iframe with admin mode for unpublished articles
                const previewIframe = document.getElementById('previewIframe');
                previewIframe.src = `../client/view_article.php?id=${articleId}&preview_mode=admin`;
                
                // Store article ID in details modal for edit/delete functions
                const detailsModal = document.getElementById('detailsModalOverlay');
                detailsModal.setAttribute('data-article-id', articleId);
                
                // Show preview modal
                const previewModal = document.getElementById('previewModalOverlay');
                previewModal.style.display = 'flex';
            }
        }

        // Open Article Preview in Modal
        function openArticlePreview() {
            // Get article ID from the details modal
            const modalOverlay = document.getElementById('detailsModalOverlay');
            const articleId = modalOverlay.getAttribute('data-article-id');
            
            if (articleId) {
                // Close the menu
                const dropdown = document.getElementById('articleMenuDropdown');
                if (dropdown) {
                    dropdown.style.display = 'none';
                }
                // Load client-side preview in iframe with admin mode for unpublished articles
                const previewIframe = document.getElementById('previewIframe');
                previewIframe.src = `../client/view_article.php?id=${articleId}&preview_mode=admin`;
                
                // Show preview modal
                const previewModal = document.getElementById('previewModalOverlay');
                previewModal.style.display = 'flex';
            }
        }

        // Close Preview Modal
        function closePreviewModal() {
            const previewModal = document.getElementById('previewModalOverlay');
            previewModal.style.display = 'none';
            
            // Clear iframe source
            const previewIframe = document.getElementById('previewIframe');
            previewIframe.src = '';
        }

        // Edit Article from Preview
        function editArticleFromPreview() {
            const detailsModal = document.getElementById('detailsModalOverlay');
            const articleId = detailsModal.getAttribute('data-article-id');
            
            if (articleId) {
                // Close modals
                closePreviewModal();
                closeDetailsModal();
                
                // Open edit modal with article ID
                setTimeout(() => {
                    openEditModal(articleId);
                }, 300);
            }
        }

        // Delete Article from Preview
        function deleteArticleFromPreview() {
            const detailsModal = document.getElementById('detailsModalOverlay');
            const articleId = detailsModal.getAttribute('data-article-id');
            
            if (articleId) {
                // Show confirmation modal
                const deleteConfirmationOverlay = document.getElementById('deleteConfirmationOverlay');
                const deleteConfirmYes = document.getElementById('deleteConfirmYes');
                const deleteConfirmCancel = document.getElementById('deleteConfirmCancel');
                
                deleteConfirmationOverlay.style.display = 'flex';
                
                // Handle delete confirmation
                deleteConfirmYes.onclick = function() {
                    closePreviewModal();
                    closeDetailsModal();
                    
                    // Get current pagination parameters from URL
                    const urlParams = new URLSearchParams(window.location.search);
                    const currentPage = urlParams.get('page') || '1';
                    const perPage = urlParams.get('per_page') || '5';
                    const filterCategory = urlParams.get('filter_category') || '';
                    const filterType = urlParams.get('filter_type') || '';
                    const search = urlParams.get('search') || '';
                    const sortBy = urlParams.get('sort_by') || 'date_desc';
                    
                    // Build redirect URL with pagination parameters
                    let redirectUrl = `delete.php?id=${articleId}&page=${currentPage}&per_page=${perPage}&sort_by=${sortBy}`;
                    if (filterCategory) redirectUrl += `&filter_category=${encodeURIComponent(filterCategory)}`;
                    if (filterType) redirectUrl += `&filter_type=${encodeURIComponent(filterType)}`;
                    if (search) redirectUrl += `&search=${encodeURIComponent(search)}`;
                    
                    window.location.href = redirectUrl;
                };
                
                deleteConfirmCancel.onclick = function() {
                    deleteConfirmationOverlay.style.display = 'none';
                };
            }
        }

        // Close preview modal when clicking outside
        document.addEventListener('click', function(e) {
            const previewModal = document.getElementById('previewModalOverlay');
            if (e.target === previewModal) {
                closePreviewModal();
            }
        });

        // Update Article Status via AJAX
        function updateArticleStatus(selectElement) {
            const articleId = selectElement.getAttribute('data-article-id');
            const newStatus = selectElement.value;
            
            fetch('update_article_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    article_id: articleId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Article status updated successfully!', 'success');
                } else {
                    showNotification('Error updating article status: ' + data.message, 'error');
                    // Revert the dropdown to previous value
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating article status', 'error');
                location.reload();
            });
        }

        // Category/Subcategory Dropdown Logic
        
        // Define globally so modal.js can call it
        function initializeCategoryListener() {
            const categorySelect = document.getElementById('modal_category_id');
            const subcategorySelect = document.getElementById('modal_category');

            if (!categorySelect || !subcategorySelect) {
                console.error('Category or subcategory select elements not found');
                console.log('categorySelect:', categorySelect);
                console.log('subcategorySelect:', subcategorySelect);
                return;
            }

            console.log('Attaching category change listener');
            
            // Clone the category select to remove all old event listeners
            const newCategorySelect = categorySelect.cloneNode(true);
            categorySelect.parentNode.replaceChild(newCategorySelect, categorySelect);
            
            // Attach the new event listener to the cloned element
            newCategorySelect.addEventListener('change', function() {
                console.log('=== Category selection changed ===');
                console.log('Selected value:', this.value);
                console.log('Selected index:', this.selectedIndex);
                
                // Get the selected option
                const selectedOption = this.options[this.selectedIndex];
                console.log('Selected option object:', selectedOption);
                
                if (!selectedOption || selectedOption.value === '') {
                    console.log('No valid option selected');
                    subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                    return;
                }
                
                // Get category ID from data attribute
                const categoryId = selectedOption.getAttribute('data-cat-id');
                console.log('Extracted categoryId:', categoryId);
                
                if (!categoryId) {
                    console.warn('No category ID found in data-cat-id');
                    subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                    return;
                }

                // Fetch subcategories for selected category
                console.log('Fetching subcategories for categoryId:', categoryId);
                
                fetch(`get_subcategories.php?category_id=${encodeURIComponent(categoryId)}`)
                    .then(response => {
                        console.log('Fetch response status:', response.status);
                        if (!response.ok) {
                            throw new Error('HTTP error! status: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Subcategories fetched successfully:', data);
                        
                        // Clear existing options except the first placeholder
                        subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                        
                        if (data.success && Array.isArray(data.data) && data.data.length > 0) {
                            console.log('Adding ' + data.data.length + ' subcategories to dropdown');
                            
                            data.data.forEach((subcat, index) => {
                                const option = document.createElement('option');
                                option.value = subcat.subcategory_id;
                                option.textContent = subcat.subcategory_name;
                                subcategorySelect.appendChild(option);
                                console.log('Added subcategory ' + (index + 1) + ':', subcat.subcategory_name);
                            });
                            
                            console.log('Finished adding all subcategories');
                        } else {
                            console.warn('No subcategories found or invalid data format');
                            subcategorySelect.innerHTML = '<option value="">No subcategories available</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching subcategories:', error);
                        subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
                    });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('Articles page DOMContentLoaded - initializing category listener');
            
            // Check if coming from a category/subcategory page
            const urlParams = new URLSearchParams(window.location.search);
            const filterCategory = urlParams.get('filter_category');
            
            if (filterCategory) {
                console.log('Filter category from URL:', filterCategory);
                // Set context with the subcategory name from URL
                if (typeof setCategoryContext === 'function') {
                    setCategoryContext(filterCategory, '');
                }
                
                // IMPORTANT: We need to find which CATEGORY this SUBCATEGORY belongs to
                // Then set the category dropdown to that category
                // Then the change event will populate the subcategories dropdown
                // Then we can set the subcategory to filterCategory
                
                // Fetch the category for this subcategory via AJAX
                fetch(`get_subcategories.php?get_category_for_subcategory=${encodeURIComponent(filterCategory)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.category_id) {
                            console.log('Found category_id for subcategory:', data.category_id);
                            // Store for later use in toggleCreateForm
                            window.targetCategoryId = data.category_id;
                            window.targetSubcategoryName = filterCategory;
                        }
                    })
                    .catch(error => console.error('Error fetching category for subcategory:', error));
            } else {
                // Clear context if no filter_category parameter
                if (typeof clearCategoryContext === 'function') {
                    clearCategoryContext();
                }
            }
            
            // Initialize the category listener
            initializeCategoryListener();

            // Handle category context pre-population from category page
            const originalToggleCreateForm = toggleCreateForm;
            toggleCreateForm = function() {
                originalToggleCreateForm();
                
                // Get fresh references after DOM operations
                const categorySelect = document.getElementById('modal_category_id');
                const subcategorySelect = document.getElementById('modal_category');
                
                // If we have a pre-selected category from the URL filter
                if (window.targetCategoryId) {
                    console.log('Using targetCategoryId:', window.targetCategoryId);
                    console.log('Using targetSubcategoryName:', window.targetSubcategoryName);
                    
                    if (categorySelect) {
                        // Find the option with matching data-cat-id
                        const categoryOptions = Array.from(categorySelect.options);
                        const matchingOption = categoryOptions.find(opt => 
                            opt.getAttribute('data-cat-id') == window.targetCategoryId
                        );
                        
                        if (matchingOption) {
                            console.log('Found matching category option:', matchingOption.textContent);
                            categorySelect.value = matchingOption.value;
                            categorySelect.disabled = true;
                            categorySelect.style.opacity = '0.6';
                            categorySelect.style.pointerEvents = 'none';
                            
                            // Show note
                            const categoryGroup = categorySelect.closest('.form-group');
                            if (categoryGroup) {
                                const existingNote = categoryGroup.querySelector('.category-note');
                                if (!existingNote) {
                                    const note = document.createElement('small');
                                    note.className = 'category-note';
                                    note.style.display = 'block';
                                    note.style.marginTop = '0.5rem';
                                    note.style.color = '#6B7280';
                                    note.style.fontStyle = 'italic';
                                    note.textContent = `📌 Category pre-selected: ${matchingOption.textContent}`;
                                    categoryGroup.appendChild(note);
                                }
                            }
                            
                            // Trigger change to populate subcategories, then set subcategory
                            console.log('Dispatching change event');
                            categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
                            
                            // After a short delay, set the subcategory to the one from the URL
                            setTimeout(() => {
                                if (subcategorySelect && window.targetSubcategoryName) {
                                    const subOptions = Array.from(subcategorySelect.options);
                                    const matchingSubOption = subOptions.find(opt => 
                                        opt.textContent.trim() === window.targetSubcategoryName.trim()
                                    );
                                    if (matchingSubOption) {
                                        console.log('Setting subcategory to:', window.targetSubcategoryName);
                                        subcategorySelect.value = matchingSubOption.value;
                                    } else {
                                        console.warn('Could not find matching subcategory:', window.targetSubcategoryName);
                                    }
                                }
                            }, 500);
                        } else {
                            console.error('No matching category option found for categoryId:', window.targetCategoryId);
                        }
                    }
                } else if (getCategoryContext && currentCategory) {
                    // Legacy logic for other context scenarios
                    if (categorySelect && categorySelect.style.display !== 'none') {
                        console.log('Pre-populating category with:', currentCategory);
                        categorySelect.value = currentCategory;
                        categorySelect.disabled = true;
                        categorySelect.style.opacity = '0.6';
                        categorySelect.style.pointerEvents = 'none';
                        
                        // Show note
                        const categoryGroup = categorySelect.closest('.form-group');
                        if (categoryGroup) {
                            const existingNote = categoryGroup.querySelector('.category-note');
                            if (!existingNote) {
                                const note = document.createElement('small');
                                note.className = 'category-note';
                                note.style.display = 'block';
                                note.style.marginTop = '0.5rem';
                                note.style.color = '#6B7280';
                                note.style.fontStyle = 'italic';
                                note.textContent = `📌 Category pre-selected: ${currentCategory}`;
                                categoryGroup.appendChild(note);
                            }
                        }
                        
                        // Trigger change to populate subcategories
                        console.log('Dispatching change event to populate subcategories');
                        categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                } else {
                    // Re-enable category dropdown if it was disabled
                    if (categorySelect) {
                        console.log('Re-enabling category dropdown');
                        categorySelect.disabled = false;
                        categorySelect.style.opacity = '1';
                        categorySelect.style.pointerEvents = 'auto';
                        const note = categorySelect.closest('.form-group')?.querySelector('.category-note');
                        if (note) note.remove();
                    }
                }
            };
        });

        // ===== DRAG AND DROP FUNCTIONALITY =====
        let draggedElement = null;
        let draggedOverElement = null;

        // Initialize drag and drop for article rows
        function initializeDragAndDrop() {
            const rows = document.querySelectorAll('.draggable-row');
            
            rows.forEach(row => {
                // Drag start
                row.addEventListener('dragstart', function(e) {
                    draggedElement = this;
                    this.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', this.innerHTML);
                });

                // Drag end
                row.addEventListener('dragend', function(e) {
                    this.classList.remove('dragging');
                    document.querySelectorAll('.draggable-row').forEach(r => {
                        r.classList.remove('drag-over');
                    });
                });

                // Drag over
                row.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    
                    if (this !== draggedElement) {
                        this.classList.add('drag-over');
                    }
                });

                // Drag leave
                row.addEventListener('dragleave', function(e) {
                    this.classList.remove('drag-over');
                });

                // Drop
                row.addEventListener('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (this !== draggedElement) {
                        // Swap the rows in the DOM
                        const allRows = Array.from(document.querySelectorAll('.draggable-row'));
                        const draggedIndex = allRows.indexOf(draggedElement);
                        const targetIndex = allRows.indexOf(this);

                        if (draggedIndex < targetIndex) {
                            this.parentNode.insertBefore(draggedElement, this.nextSibling);
                        } else {
                            this.parentNode.insertBefore(draggedElement, this);
                        }

                        // Save the new order
                        saveArticleOrder();
                    }
                    
                    this.classList.remove('drag-over');
                });
            });
        }

        // Save the new article order
        function saveArticleOrder() {
            const rows = document.querySelectorAll('.draggable-row');
            const articleIds = Array.from(rows).map(row => row.getAttribute('data-article-id'));

            // Get current filter parameters from URL
            const urlParams = new URLSearchParams(window.location.search);
            const filterMainCategory = urlParams.get('filter_main_category') || '';
            const filterCategory = urlParams.get('filter_category') || '';

            // Only proceed if both filters are present
            if (!filterMainCategory || !filterCategory) {
                showNotification('Error: Both category and subcategory filters are required', 'error');
                console.error('Missing filter parameters:', { filterMainCategory, filterCategory });
                return;
            }

            console.log('Saving article order for:', { articleIds, filterMainCategory, filterCategory });

            fetch('update_article_order.php?filter_main_category=' + encodeURIComponent(filterMainCategory) + '&filter_category=' + encodeURIComponent(filterCategory), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    articles: articleIds
                })
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    showNotification('Article order saved successfully!', 'success');
                    // Reload page after 1 second to show the updated order
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    console.error('Save failed:', data.message);
                }
            })
            .catch(error => {
                console.error('Error saving article order:', error);
            });
        }

        // Initialize drag and drop when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeDragAndDrop();
            
            // If a category is pre-selected from URL, load its subcategories
            if (filterMainCategory && filterMainCategory.value) {
                const categoryId = filterMainCategory.value;
                const selectedSubcategory = filterCategory.dataset.selectedValue || '';
                
                fetch(`get_subcategories.php?category_id=${encodeURIComponent(categoryId)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            // Clear current subcategories (keep the "All" option)
                            const allOption = filterCategory.querySelector('option[value=""]');
                            filterCategory.innerHTML = '';
                            filterCategory.appendChild(allOption);
                            
                            // Add new subcategories
                            data.data.forEach(sub => {
                                const option = document.createElement('option');
                                option.value = sub.subcategory_name;
                                option.textContent = sub.subcategory_name;
                                
                                // If this subcategory is currently selected, mark it as selected
                                if (selectedSubcategory && sub.subcategory_name === selectedSubcategory) {
                                    option.selected = true;
                                }
                                filterCategory.appendChild(option);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching subcategories:', error);
                    });
            }
        });

        // Reinitialize after filters are applied (via page reload handled automatically)
        // Or if dynamic updates are needed, call initializeDragAndDrop() after table updates

    </script>
</body>
</html>

<?php $conn->close(); ?>
<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
include 'db.php';

// Fetch current user data (ADMIN)
$current_user = null;
$user_result = $conn->query("SELECT * FROM admins WHERE admin_id = " . $_SESSION['admin_id']);
$current_user = $user_result ? $user_result->fetch_assoc() : null; 

// Fetch dashboard statistics
$total_articles = $conn->query("SELECT COUNT(*) as count FROM ARTICLES")->fetch_assoc()['count'];
$total_categories = $conn->query("SELECT COUNT(*) as count FROM CATEGORIES")->fetch_assoc()['count'];
$pending_tickets = $conn->query("SELECT COUNT(*) as count FROM TICKETS WHERE status_ != 'Done' AND status_ != 'In Progress'")->fetch_assoc()['count'];

// Fetch feedback statistics
$total_likes = $conn->query("SELECT COUNT(*) as count FROM article_feedback WHERE is_helpful = 1")->fetch_assoc()['count'];
$total_dislikes = $conn->query("SELECT COUNT(*) as count FROM article_feedback WHERE is_helpful = 0")->fetch_assoc()['count'];

// Fetch tickets by status and category for chart
$tickets_by_category_status = $conn->query("
    SELECT
        'All Tickets' as category_name,
        status_,
        COUNT(*) as count
    FROM TICKETS
    GROUP BY status_
    ORDER BY status_
");

// Process the data into chart format
$tickets_chart_data = [];
$category_map = [];

while ($row = $tickets_by_category_status->fetch_assoc()) {
    $category = $row['category_name'];
    $status = $row['status_'];
    $count = (int)$row['count'];
    
    if (!isset($category_map[$category])) {
        $category_map[$category] = [
            'category' => $category,
            'done_count' => 0,
            'pending_count' => 0,
            'progress_count' => 0,
            'reviewed_count' => 0
        ];
    }
    
    // Map statuses to chart fields
    if ($status === 'Done') {
        $category_map[$category]['done_count'] = $count;
    } else if ($status === 'In Progress') {
        $category_map[$category]['progress_count'] = $count;
    } else {
        // All other statuses (Pending, To be Reviewed, etc.) go to pending
        $category_map[$category]['pending_count'] += $count;
    }
}

// Convert to array for JSON encoding
foreach ($category_map as $item) {
    $tickets_chart_data[] = $item;
}

// Fetch articles by category for donut chart
$articles_by_category = $conn->query("
    SELECT 
        COALESCE(c.category_name, 'Uncategorized') as category,
        COUNT(*) as count
    FROM ARTICLES a
    LEFT JOIN subcategories s ON a.subcategory_id = s.subcategory_id
    LEFT JOIN categories c ON s.category_id = c.category_id
    GROUP BY c.category_id, c.category_name
    ORDER BY count DESC
");

// Fetch recent articles
$recent_articles = $conn->query("
    SELECT a.article_id, a.title, a.content, COALESCE(c.category_name, 'Uncategorized') as category 
    FROM ARTICLES a
    LEFT JOIN subcategories s ON a.subcategory_id = s.subcategory_id
    LEFT JOIN categories c ON s.category_id = c.category_id
    ORDER BY a.created_at DESC 
    LIMIT 4
");

// Prepare data for charts
$articles_chart_data = [];
while ($row = $articles_by_category->fetch_assoc()) {
    $articles_chart_data[] = $row;
}

// Fetch all categories for feedback chart filter
$all_categories = $conn->query("
    SELECT category_id, category_name
    FROM CATEGORIES
    ORDER BY category_name ASC
");
$categories_list = [];
if ($all_categories) {
    while ($cat_row = $all_categories->fetch_assoc()) {
        $categories_list[] = $cat_row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Genie | Dashboard</title>
    <link rel="icon" type="image/jpeg" href="assets/img/intellismart.jpg">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <main class="content">
            <!-- Welcome Banner -->
            <div class="welcome-banner" img src="assets/img/welcome-bg.jpg" alt="Welcome Illustration" class="welcome-img">
                <div class="welcome-content">
                    <h2>Welcome, <?php echo htmlspecialchars($current_user['full_name'] ?? 'Admin User'); ?>!</h2>
                    <p>You're looking at Hrdotnet Genie, your new tool for work.</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="summary-section">
                <h3 class="section-title">Key Performance Indicator</h3>
                <div class="summary-cards">
                    <a href="articles.php" class="summary-card">
                        <div class="card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 20 20"><path fill="#f4f3f1" d="M13 0a2 2 0 0 1 2 2H6a2 2 0 0 0-2 2v12a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2z"/><path fill="#f4f3f1" d="M18 5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2zm-7 5H7V5h4zm5-4h-4V5h4zm0 2h-4V7h4zm0 2h-4V9h4zm0 2H7v-1h9zm0 2H7v-1h9zm0 2H7v-1h9z"/></svg>
                        </div>
                        <div class="card-info">
                            <div class="card-label">Total Articles</div>
                            <div class="card-value"><?php echo $total_articles; ?></div>
                        </div>
                    </a>
                    <a href="category.php" class="summary-card">
                        <div class="card-icon">
                           <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><path fill="#f4f3f1" fill-rule="evenodd" d="M2.07 5.258C2 5.626 2 6.068 2 6.95V14c0 3.771 0 5.657 1.172 6.828S6.229 22 10 22h4c3.771 0 5.657 0 6.828-1.172S22 17.771 22 14v-2.202c0-2.632 0-3.949-.77-4.804a3 3 0 0 0-.224-.225C20.151 6 18.834 6 16.202 6h-.374c-1.153 0-1.73 0-2.268-.153a4 4 0 0 1-.848-.352C12.224 5.224 11.816 4.815 11 4l-.55-.55c-.274-.274-.41-.41-.554-.53a4 4 0 0 0-2.18-.903C7.53 2 7.336 2 6.95 2c-.883 0-1.324 0-1.692.07A4 4 0 0 0 2.07 5.257M12.25 10a.75.75 0 0 1 .75-.75h5a.75.75 0 0 1 0 1.5h-5a.75.75 0 0 1-.75-.75" clip-rule="evenodd"/></svg>
                        </div>
                        <div class="card-info">
                            <div class="card-label">Total Category</div>
                            <div class="card-value"><?php echo $total_categories; ?></div>
                        </div>
                    </a>
                    <a href="tickets.php" class="summary-card">
                        <div class="card-icon">
                           <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><path fill="#f4f3f1" d="M15 16.69V13h1.5v2.82l2.44 1.41l-.75 1.3zM19.5 3.5L18 2l-1.5 1.5L15 2l-1.5 1.5L12 2l-1.5 1.5L9 2L7.5 3.5L6 2L4.5 3.5L3 2v20l1.5-1.5L6 22l1.5-1.5L9 22l1.58-1.58c.14.19.3.36.47.53A7.001 7.001 0 0 0 21 11.1V2zM11.1 11c-.6.57-1.07 1.25-1.43 2H6v-2zm-2.03 4c-.07.33-.07.66-.07 1s0 .67.07 1H6v-2zM18 9H6V7h12zm2.85 7c0 .64-.12 1.27-.35 1.86c-.26.58-.62 1.14-1.07 1.57c-.43.45-.99.81-1.57 1.07c-.59.23-1.22.35-1.86.35c-2.68 0-4.85-2.17-4.85-4.85c0-1.29.51-2.5 1.42-3.43c.93-.91 2.14-1.42 3.43-1.42c2.67 0 4.85 2.17 4.85 4.85"/></svg>
                        </div>
                        <div class="card-info">
                            <div class="card-label">Pending Tickets</div>
                            <div class="card-value"><?php echo $pending_tickets; ?></div>
                        </div>
                    </a>
                    <a href="#" onclick="document.getElementById('feedbackAnalysisSection').scrollIntoView({ behavior: 'smooth' }); return false;" class="summary-card" style="cursor: pointer;">
                        <div class="card-icon">
                           <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><path fill="#f4f3f1" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
                        </div>
                        <div class="card-info">
                            <div class="card-label">Feedback</div>
                            <div class="card-value" style="display: flex; gap: 1rem; align-items: center;">
                                <span style="color: #2bce98; display: flex; align-items: center; gap: 0.5rem;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 1024 1024"><path fill="currentColor" d="M885.9 533.7c16.8-22.2 26.1-49.4 26.1-77.7c0-44.9-25.1-87.4-65.5-111.1a67.67 67.67 0 0 0-34.3-9.3H572.4l6-122.9c1.4-29.7-9.1-57.9-29.5-79.4A106.62 106.62 0 0 0 471 99.9c-52 0-98 35-111.8 85.1l-85.9 311H144c-17.7 0-32 14.3-32 32v364c0 17.7 14.3 32 32 32h601.3c9.2 0 18.2-1.8 26.5-5.4c47.6-20.3 78.3-66.8 78.3-118.4c0-12.6-1.8-25-5.4-37c16.8-22.2 26.1-49.4 26.1-77.7c0-12.6-1.8-25-5.4-37c16.8-22.2 26.1-49.4 26.1-77.7c-.2-12.6-2-25.1-5.6-37.1M184 852V568h81v284zm636.4-353l-21.9 19l13.9 25.4a56.2 56.2 0 0 1 6.9 27.3c0 16.5-7.2 32.2-19.6 43l-21.9 19l13.9 25.4a56.2 56.2 0 0 1 6.9 27.3c0 16.5-7.2 32.2-19.6 43l-21.9 19l13.9 25.4a56.2 56.2 0 0 1 6.9 27.3c0 22.4-13.2 42.6-33.6 51.8H329V564.8l99.5-360.5a44.1 44.1 0 0 1 42.2-32.3c7.6 0 15.1 2.2 21.1 6.7c9.9 7.4 15.2 18.6 14.6 30.5l-9.6 198.4h314.4C829 418.5 840 436.9 840 456c0 16.5-7.2 32.1-19.6 43"/></svg>
                                    <?php echo $total_likes; ?>
                                </span>
                                <span style="color: #ea6060; display: flex; align-items: center; gap: 0.5rem;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 1024 1024"><path fill="currentColor" d="M885.9 490.3c3.6-12 5.4-24.4 5.4-37c0-28.3-9.3-55.5-26.1-77.7c3.6-12 5.4-24.4 5.4-37c0-28.3-9.3-55.5-26.1-77.7c3.6-12 5.4-24.4 5.4-37c0-51.6-30.7-98.1-78.3-118.4a66.1 66.1 0 0 0-26.5-5.4H144c-17.7 0-32 14.3-32 32v364c0 17.7 14.3 32 32 32h129.3l85.8 310.8C372.9 889 418.9 924 470.9 924c29.7 0 57.4-11.8 77.9-33.4c20.5-21.5 31-49.7 29.5-79.4l-6-122.9h239.9c12.1 0 23.9-3.2 34.3-9.3c40.4-23.5 65.5-66.1 65.5-111c0-28.3-9.3-55.5-26.1-77.7M184 456V172h81v284zm627.2 160.4H496.8l9.6 198.4c.6 11.9-4.7 23.1-14.6 30.5c-6.1 4.5-13.6 6.8-21.1 6.7a44.28 44.28 0 0 1-42.2-32.3L329 459.2V172h415.4a56.85 56.85 0 0 1 33.6 51.8c0 9.7-2.3 18.9-6.9 27.3l-13.9 25.4l21.9 19a56.76 56.76 0 0 1 19.6 43c0 9.7-2.3 18.9-6.9 27.3l-13.9 25.4l21.9 19a56.76 56.76 0 0 1 19.6 43c0 9.7-2.3 18.9-6.9 27.3l-14 25.5l21.9 19a56.76 56.76 0 0 1 19.6 43c0 19.1-11 37.5-28.8 48.4"/></svg>
                                    <?php echo $total_dislikes; ?>
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Dashboard Layout: Charts Left, Articles Right -->
            <div class="dashboard-layout-wrapper">
                <!-- Charts Section (Left) -->
                <div class="charts-section">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3>Pending Tickets</h3>
                            <span class="chart-badge" title="Total Pending Tickets"><?php echo $pending_tickets; ?></span>
                            <div class="chart-filters">
                                <select id="ticketsDateFilter" class="period-select" onchange="updateTicketsChart()">
                                    <option value="all">All Time</option>
                                    <option value="week">This Week</option>
                                    <option value="month">This Month</option>
                                    <option value="today">Today</option>
                                </select>
                                <select id="ticketsStatusFilter" class="period-select" onchange="updateTicketsChart()">
                                    <option value="all">All Status</option>
                                    <option value="done">Done</option>
                                    <option value="progress">In Progress</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                        </div>
                        <div style="height: 280px; position: relative; width: 100%;">
                            <canvas id="ticketsChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3>Articles Per Category</h3>
                            <span class="chart-badge" title="Total Articles"><?php echo $total_articles; ?></span>
                            <div class="chart-filters">
                                <select id="articlesDateFilter" class="period-select" onchange="updateArticlesChart()">
                                    <option value="all">All Time</option>
                                    <option value="week">This Week</option>
                                    <option value="month">This Month</option>
                                    <option value="today">Today</option>
                                </select>
                                <select id="articlesCategoryFilter" class="period-select" onchange="updateArticlesChart()">
                                    <option value="">All Categories</option>
                                    <?php 
                                    foreach ($categories_list as $cat) {
                                        echo '<option value="' . intval($cat['category_id']) . '">' . htmlspecialchars($cat['category_name']) . '</option>';
                                    }
                                    ?>
                                </select>
                                <select id="articlesPublishFilter" class="period-select" onchange="updateArticlesChart()">
                                    <option value="">All Articles</option>
                                    <option value="published">Published</option>
                                    <option value="unpublished">Unpublished</option>
                                </select>
                            </div>
                        </div>
                        <div style="height: 350px; position: relative; width: 100%;">
                            <canvas id="articlesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Articles Section (Right) -->
                <div class="recent-articles-section">
                <h3 class="section-title">Recent Articles</h3>
                <div class="articles-list">
                    <?php while ($article = $recent_articles->fetch_assoc()): ?>
                    <div class="article-item" onclick="openRecentArticlePreview(<?= $article['article_id'] ?>)" style="cursor: pointer;">
                        <div class="article-icon">
                             <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path fill="#f4f3f1" d="M13 0a2 2 0 0 1 2 2H6a2 2 0 0 0-2 2v12a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2z"/><path fill="#f4f3f1" d="M18 5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2zm-7 5H7V5h4zm5-4h-4V5h4zm0 2h-4V7h4zm0 2h-4V9h4zm0 2H7v-1h9zm0 2H7v-1h9zm0 2H7v-1h9z"/></svg>
                        </div>
                        <div class="article-content">
                            <h4><?php echo htmlspecialchars($article['title']); ?></h4>
                            <p style="font-size: 0.875rem; color: #6B7280; margin: 0.5rem 0 0 0; line-height: 1.4;">
                                <?php 
                                    $cleanContent = strip_tags($article['content']);
                                    $description = substr($cleanContent, 0, 50);
                                    if (strlen($cleanContent) > 50) {
                                        $description .= '...';
                                    }
                                    echo htmlspecialchars($description);
                                ?>
                            </p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            </div>

            <!-- Articles Feedback Analysis Section -->
            <div id="feedbackAnalysisSection" style="margin-top: 2rem;">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Feedback Analysis</h3>
                        <div class="chart-filters" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label for="feedbackCategoryFilter" style="display: block; font-size: 0.875rem; font-weight: 500; color: #6B7280; margin-bottom: 0.5rem;">Category</label>
                                <select id="feedbackCategoryFilter" class="period-select" onchange="updateFeedbackSubcategories()">
                                    <option value="">Select Category</option>
                                    <?php 
                                    foreach ($categories_list as $cat) {
                                        echo '<option value="' . $cat['category_id'] . '">' . htmlspecialchars($cat['category_name']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label for="feedbackSubcategoryFilter" style="display: block; font-size: 0.875rem; font-weight: 500; color: #6B7280; margin-bottom: 0.5rem;">Subcategory</label>
                                <select id="feedbackSubcategoryFilter" class="period-select" onchange="updateFeedbackChart()" disabled>
                                    <option value="">Select Subcategory</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem;">
                        <!-- Feedback Chart (Left) -->
                        <div style="position: relative; height: 350px;">
                            <canvas id="feedbackChart"></canvas>
                        </div>

                        <!-- Subcategory Articles List (Right) -->
                        <div id="subcategoryArticlesList" style="overflow-y: auto; max-height: 320px; border-left: 1px solid #E5E7EB; padding-left: 1.5rem;">
                            <div style="padding: 2rem 0; text-align: center; color: #9CA3AF; font-size: 0.875rem;">
                                Select a subcategory to view articles
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Overlay -->
    <div id="modalOverlay" class="modal-overlay" onclick="closeModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2 id="modalTitle">New Article</h2>
                <button class="modal-close" onclick="closeModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <form id="articleModalForm" class="modal-form">
                <input type="hidden" id="article_id" name="id">
                
                <!-- Two Column Layout -->
                <div class="form-row" id="twoColumnLayout">
                    <!-- Left Column: Title and Type-Specific Fields -->
                    <div class="form-column-left">
                        <div class="form-group">
                            <label for="modal_title">Title</label>
                            <input type="text" id="modal_title" name="title" placeholder="Enter Article Title" required>
                        </div>

                        <!-- Simple Question Fields -->
                        <div id="simpleQuestionFields" class="article-type-fields" style="display: none;">
                            <div class="form-group">
                                <label for="modal_question">Question</label>
                                <textarea id="modal_question" name="question" rows="4" placeholder="Enter the question..."></textarea>
                            </div>
                            <div class="form-group">
                                <label for="modal_answer">Answer</label>
                                <textarea id="modal_answer" name="answer" rows="6" placeholder="Enter the answer..."></textarea>
                            </div>
                        </div>

                        <!-- Step-by-Step Fields -->
                        <div id="stepByStepFields" class="article-type-fields" style="display: none;">
                            <div class="form-group">
                                <label for="modal_introduction">Introduction</label>
                                <textarea id="modal_introduction" name="introduction" rows="4" placeholder="Enter the introduction..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Steps</label>
                                <div id="stepsContainer">
                                    <div class="step-item" data-step="1">
                                        <input type="text" placeholder="Step 1 Title" class="step-title" name="step_1_title">
                                        <textarea placeholder="Step 1 Description" class="step-description" name="step_1_description" rows="3"></textarea>
                                    </div>
                                </div>
                                <button type="button" class="btn-secondary" onclick="addStep()">Add Step</button>
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label for="modal_file">Choose File</label>
                                    <input type="file" id="modal_file" name="file" accept="image/*,.pdf,.doc,.docx" placeholder="Upload image, PDF, or document">
                                </div>
                            </div>
                        </div>

                        <!-- Standard Fields -->
                        <div id="standardFields" class="article-type-fields" style="display: none;">
                            <div class="form-group">
                                <label for="modal_description">Description</label>
                                <textarea id="modal_description" name="description" rows="8" placeholder="Enter your Description here..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Article Type, Category, Author -->
                    <div class="form-column-right">
                        <div class="form-group">
                            <label for="modal_type">Article Type</label>
                            <select id="modal_type" name="type" required>
                                <option value="">Select Article Type</option>
                                <option value="simple_question">Simple Question</option>
                                <option value="step_by_step">Step-by-Step</option>
                                <option value="standard">Standard</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="modal_category_id">Category</label>
                            <select id="modal_category_id" name="category_id" data-category-id="" required>
                                <option value="">Select Category</option>
                                <?php 
                                // Fetch categories for the modal form
                                $categories_sql = "SELECT category_id, category_name FROM CATEGORIES ORDER BY category_name ASC";
                                $categories_result = $conn->query($categories_sql);
                                if ($categories_result && $categories_result->num_rows > 0) {
                                    while ($cat_row = $categories_result->fetch_assoc()) {
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
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-create" id="modalSubmitBtn">Create Article</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Article Details Modal -->
    <div id="detailsModalOverlay" class="modal-overlay" onclick="closeDetailsModal()">
        <div class="modal-content" onclick="event.stopPropagation()" style="max-width: 800px; max-height: 90vh; overflow-y: auto; padding: 0;">
            <button class="modal-close" onclick="closeDetailsModal()" style="position: absolute; top: 1.5rem; right: 1.5rem; z-index: 10;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>

            <!-- Article Header with Title -->
            <div style="background: linear-gradient(135deg, #3B82F6 0%, #1E40AF 100%); color: white; padding: 3rem 2rem;">
                <h1 id="detailsTitle" style="font-size: 2rem; font-weight: 700; margin: 0 0 1.5rem 0; line-height: 1.3;"></h1>
                
                <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; flex-wrap: wrap; font-size: 0.875rem; opacity: 0.95;">
                    <span id="detailsAuthor-header" style="display: flex; align-items: center; gap: 0.375rem;">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                            <path d="M10 10C12.21 10 14 8.21 14 6C14 3.79 12.21 2 10 2C7.79 2 6 3.79 6 6C6 8.21 7.79 10 10 10ZM10 12C7.33 12 2 13.34 2 16V18H18V16C18 13.34 12.67 12 10 12Z" fill="currentColor"/>
                        </svg>
                        <span id="detailsAuthor-text"></span>
                    </span>
                    <span style="opacity: 0.7;">•</span>
                    <span id="detailsDate-header" style="display: flex; align-items: center; gap: 0.375rem;">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                            <path d="M5 1C4.4 1 4 1.4 4 2V3H2C0.9 3 0 3.9 0 5V17C0 18.1 0.9 19 2 19H16C17.1 19 18 18.1 18 17V5C18 3.9 17.1 3 16 3H14V2C14 1.4 13.6 1 13 1C12.4 1 12 1.4 12 2V3H6V2C6 1.4 5.6 1 5 1ZM2 6H16V17H2V6Z" fill="currentColor"/>
                        </svg>
                        <span id="detailsDate-text"></span>
                    </span>
                    <span style="opacity: 0.7;">•</span>
                    <span id="detailsCategory-header" style="display: flex; align-items: center; gap: 0.375rem;">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                            <path d="M10.5 1.5H2C0.9 1.5 0 2.4 0 3.5V16.5C0 17.6 0.9 18.5 2 18.5H18C19.1 18.5 20 17.6 20 16.5V8H10.5V1.5Z" fill="currentColor"/>
                        </svg>
                        <span id="detailsCategory-text"></span>
                    </span>
                </div>
            </div>

            <!-- Article Meta Bar -->
            <div style="background: #F3F4F6; border-bottom: 1px solid #E5E7EB; padding: 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; gap: 1.5rem; align-items: center;">
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 600; color: #6B7280; text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Type</span>
                        <span id="detailsType-text" style="font-weight: 600; color: #374151;"></span>
                    </div>
                </div>
            </div>

            <!-- Article Content -->
            <div style="padding: 3rem 2rem;">
                <!-- Standard/Step-by-Step Article View -->
                <div id="standardContent">
                    <div id="detailsContent-text" style="font-size: 1rem; line-height: 1.8; color: #374151; white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;"></div>
                </div>

                <!-- Simple Question Article View -->
                <div id="simpleQuestionContent" style="display: none;">
                    <div style="display: grid; grid-template-columns: 350px 1fr; gap: 3rem; align-items: start;">
                        <!-- Left Side: Image Placeholder -->
                        <div style="display: flex; align-items: center; justify-content: center;">
                            <div style="width: 100%; aspect-ratio: 1; background: linear-gradient(135deg, #E5E7EB 0%, #D1D5DB 100%); border-radius: 12px; border: 3px solid #9CA3AF; display: flex; align-items: center; justify-content: center;">
                                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" style="color: #9CA3AF; opacity: 0.7;">
                                    <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5" fill="currentColor"/>
                                    <path d="M21 15l-5-5L5 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Right Side: Question and Answer -->
                        <div>
                            <!-- Question Section -->
                            <div style="margin-bottom: 2rem;">
                                <h3 style="font-size: 0.875rem; font-weight: 600; color: #6B7280; text-transform: uppercase; margin: 0 0 0.75rem 0; letter-spacing: 0.5px;">Question</h3>
                                <p id="simpleQuestionText" style="font-size: 1rem; line-height: 1.7; color: #374151; margin: 0; padding: 1.25rem; background: #F3F4F6; border-radius: 8px; border-left: 4px solid #3B82F6;"></p>
                            </div>

                            <!-- Answer Section -->
                            <div>
                                <h3 style="font-size: 0.875rem; font-weight: 600; color: #6B7280; text-transform: uppercase; margin: 0 0 0.75rem 0; letter-spacing: 0.5px;">Answer</h3>
                                <p id="simpleAnswerText" style="font-size: 1rem; line-height: 1.8; color: #374151; margin: 0; padding: 1.25rem; background: #F0FDF4; border-radius: 8px; border-left: 4px solid #16A34A; white-space: pre-wrap;"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 0.75rem; padding: 1.5rem 2rem; border-top: 1px solid #E5E7EB; background: #F9FAFB; justify-content: flex-end;">
                <button type="button" class="btn-cancel" onclick="closeDetailsModal()" style="padding: 0.75rem 1.5rem; border: 1px solid #D1D5DB; background: white; color: #374151; border-radius: 6px; font-weight: 500; cursor: pointer; transition: all 0.2s;">Close</button>
                <button type="button" class="btn-create" onclick="editArticleFromDetails()" style="padding: 0.75rem 1.5rem; display: flex; align-items: center; gap: 0.5rem; border-radius: 6px; border: none; background: #3B82F6; color: white; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                        <path d="M2.5 17.5H5.75L15.8167 7.43333C16.0833 7.16667 16.2222 6.8125 16.2222 6.45833C16.2222 6.10417 16.0833 5.75 15.8167 5.48333L13.3667 2.98333C13.1 2.71667 12.75 2.57812 12.3917 2.57812C12.0333 2.57812 11.6792 2.71667 11.4125 2.98333L1.34583 13.05V16.3C1.34583 16.75 1.71667 17.1208 2.16667 17.1208L2.5 17.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11.25 6.875L13.125 8.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Edit
                </button>
                <a href="#" id="deleteBtn" style="padding: 0.75rem 1.5rem; display: flex; align-items: center; gap: 0.5rem; text-decoration: none; background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; border-radius: 6px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                        <path d="M2.5 5H17.5M8.33337 9.16667V14.1667M11.6667 9.16667V14.1667M3.33337 5L4.16671 15.8333C4.25004 17.0667 5.33337 18 6.58337 18H13.4167C14.6667 18 15.75 17.0667 15.8334 15.8333L16.6667 5M7.08337 5V3.66667C7.08337 3.29167 7.375 3 7.75004 3H12.25C12.625 3 12.9167 3.29167 12.9167 3.66667V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Delete
                </a>
            </div>
        </div>
    </div>

    <script src="modal.js"></script>
    <script>
        // Function to initialize category dropdown listener
        function initializeCategoryListener() {
            const categorySelect = document.getElementById('modal_category_id');
            const subcategorySelect = document.getElementById('modal_category');

            if (!categorySelect || !subcategorySelect) {
                console.error('Category or subcategory select elements not found');
                return;
            }

            // Create a new listener, remove old ones
            const newCategorySelect = categorySelect.cloneNode(true);
            categorySelect.parentNode.replaceChild(newCategorySelect, categorySelect);
            
            // Attach listener to the new element
            newCategorySelect.addEventListener('change', function() {
                console.log('Category select change event triggered');
                console.log('this:', this);
                console.log('this.id:', this.id);
                console.log('this.selectedIndex:', this.selectedIndex);
                console.log('this.options.length:', this.options.length);
                console.log('this.value:', this.value);
                console.log('All options:', Array.from(this.options).map(opt => ({value: opt.value, text: opt.text, dataCatId: opt.getAttribute('data-cat-id')})));
                
                // Try using Array.from instead of index
                const selectedOption = Array.from(this.options).find(opt => opt.value === this.value);
                console.log('Selected option (via find):', selectedOption);
                
                if (!selectedOption) {
                    console.warn('No selectedOption found - trying index fallback');
                    const fallbackOption = this.options[this.selectedIndex];
                    console.log('Fallback option:', fallbackOption);
                    if (!fallbackOption) {
                        subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                        return;
                    }
                }
                
                const option = selectedOption || this.options[this.selectedIndex];
                
                // Safety check
                if (!option) {
                    console.error('Could not find selected option');
                    subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                    return;
                }
                
                const categoryId = option.getAttribute('data-cat-id');
                console.log('Category ID from attribute:', categoryId);
                
                // Reset subcategory dropdown
                subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                
                if (!categoryId || categoryId === '') {
                    console.warn('No categoryId, returning');
                    return;
                }

                // Fetch subcategories for selected category
                console.log('Fetching subcategories for categoryId:', categoryId);
                fetch(`get_subcategories.php?category_id=${encodeURIComponent(categoryId)}`)
                    .then(response => {
                        console.log('Response status:', response.status);
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Subcategories data received:', data);
                        if (data.success && data.data && data.data.length > 0) {
                            console.log('Adding subcategories to dropdown:', data.data.length);
                            data.data.forEach(subcat => {
                                const option = document.createElement('option');
                                option.value = subcat.subcategory_id;
                                option.textContent = subcat.subcategory_name;
                                option.setAttribute('data-subcat-id', subcat.subcategory_id);
                                subcategorySelect.appendChild(option);
                                console.log('Added option:', subcat.subcategory_name);
                            });
                        } else {
                            console.warn('No subcategories found or data format incorrect');
                            subcategorySelect.innerHTML = '<option value="">No subcategories available</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching subcategories:', error);
                        subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
                    });
            });
        }

        let ticketsChart = null;
        let articlesChart = null;

        // Initialize charts on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add small delay to ensure Chart.js is fully loaded
            setTimeout(function() {
                console.log('Initializing charts...');
                console.log('Tickets data:', <?php echo json_encode($tickets_chart_data); ?>);
                console.log('Articles data:', <?php echo json_encode($articles_chart_data); ?>);
                
                try {
                    initializeTicketsChart();
                    console.log('Tickets chart initialized');
                } catch (e) {
                    console.error('Error initializing tickets chart:', e);
                }
                
                try {
                    initializeArticlesChart();
                    console.log('Articles chart initialized');
                } catch (e) {
                    console.error('Error initializing articles chart:', e);
                }
                
                try {
                    initializeFeedbackChartWithCategories();
                    initializeCategoryListener();
                } catch (e) {
                    console.error('Error initializing feedback chart:', e);
                }
            }, 500);
        });

        function getDateRange(period) {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const date = String(today.getDate()).padStart(2, '0');
            const todayStr = `${year}-${month}-${date}`;

            switch(period) {
                case 'today':
                    return { start: todayStr, end: todayStr };
                case 'week':
                    const weekStart = new Date(today);
                    weekStart.setDate(today.getDate() - today.getDay());
                    const weekStartStr = weekStart.toISOString().split('T')[0];
                    return { start: weekStartStr, end: todayStr };
                case 'month':
                    const monthStart = new Date(year, today.getMonth(), 1);
                    const monthStartStr = monthStart.toISOString().split('T')[0];
                    return { start: monthStartStr, end: todayStr };
                case 'all':
                default:
                    return { start: '2020-01-01', end: todayStr };
            }
        }

        function initializeTicketsChart() {
            const ticketsCtx = document.getElementById('ticketsChart').getContext('2d');
            const ticketsData = <?php echo json_encode($tickets_chart_data); ?>;
            
            ticketsChart = new Chart(ticketsCtx, {
                type: 'bar',
                data: {
                    labels: ticketsData.map(item => item.category || 'All Tickets'),
                    datasets: [
                        {
                            label: 'Done',
                            data: ticketsData.map(item => parseInt(item.done_count) || 0),
                            backgroundColor: '#a381f5',
                            borderRadius: 4
                        },
                        {
                            label: 'In Progress',
                            data: ticketsData.map(item => parseInt(item.progress_count) || 0),
                            backgroundColor: '#f7cb5e',
                            borderRadius: 4
                        },
                        {
                            label: 'Pending',
                            data: ticketsData.map(item => parseInt(item.pending_count) || 0),
                            backgroundColor: '#ee8e4a',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 10,
                            ticks: {
                                stepSize: 2
                            }
                        }
                    }
                }
            });
        }

        function initializeArticlesChart() {
            const articlesCtx = document.getElementById('articlesChart').getContext('2d');
            const articlesData = <?php echo json_encode($articles_chart_data); ?>;
            const baseColors = ['#78b1f1', '#f6af89'];
            
            // Generate enough colors for all categories
            const colors = [];
            for (let i = 0; i < articlesData.length; i++) {
                colors.push(baseColors[i % baseColors.length]);
            }
            
            articlesChart = new Chart(articlesCtx, {
                type: 'doughnut',
                data: {
                    labels: articlesData.map((item, index) => item.category || 'Category ' + (index + 1)),
                    datasets: [{
                        data: articlesData.map(item => parseInt(item.count) || 0),
                        backgroundColor: colors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'right',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const value = data.datasets[0].data[i];
                                            return {
                                                text: label + ' (' + value + ' Articles)',
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                strokeStyle: data.datasets[0].backgroundColor[i],
                                                lineWidth: 0,
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        }
                    }
                }
            });
        }

        function updateTicketsChart() {
            const dateFilter = document.getElementById('ticketsDateFilter').value;
            const statusFilter = document.getElementById('ticketsStatusFilter').value;
            const dateRange = getDateRange(dateFilter);

            fetch(`get_chart_data.php?type=tickets&date_range=${dateFilter}&status=${statusFilter}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && ticketsChart) {
                        ticketsChart.data.datasets[0].data = data.done_counts;
                        ticketsChart.data.datasets[1].data = data.progress_counts;
                        ticketsChart.data.datasets[2].data = data.pending_counts;
                        ticketsChart.update();
                    }
                })
                .catch(error => console.error('Error updating tickets chart:', error));
        }

        function updateArticlesChart() {
            const dateFilter = document.getElementById('articlesDateFilter').value;
            const categoryFilter = document.getElementById('articlesCategoryFilter').value;
            const publishFilter = document.getElementById('articlesPublishFilter').value;

            fetch(`get_chart_data.php?type=articles&date_range=${dateFilter}&category_id=${categoryFilter}&publish_status=${publishFilter}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && articlesChart) {
                        articlesChart.data.labels = data.labels;
                        articlesChart.data.datasets[0].data = data.counts;
                        articlesChart.update();
                    }
                })
                .catch(error => console.error('Error updating articles chart:', error));
        }

        function updateArticlesSubcategories() {
            const categoryId = document.getElementById('articlesCategoryFilter').value;
            const subcategoryFilter = document.getElementById('articlesSubcategoryFilter');
            
            // Reset subcategory filter
            subcategoryFilter.innerHTML = '<option value="">All Subcategories</option>';
            
            if (!categoryId) {
                subcategoryFilter.disabled = true;
                updateArticlesChart();
                return;
            }

            // Fetch subcategories for the selected category
            fetch(`get_subcategories.php?category_id=${categoryId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        subcategoryFilter.disabled = false;
                        data.data.forEach(subcategory => {
                            const option = document.createElement('option');
                            option.value = subcategory.subcategory_id;
                            option.textContent = subcategory.subcategory_name;
                            subcategoryFilter.appendChild(option);
                        });
                    } else {
                        subcategoryFilter.disabled = true;
                    }
                    updateArticlesChart();
                })
                .catch(error => {
                    console.error('Error fetching subcategories:', error);
                    subcategoryFilter.disabled = true;
                    updateArticlesChart();
                });
        }

        let feedbackChart = null;

        function initializeFeedbackChartWithCategories() {
            fetch('get_feedback_by_category.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error('Error fetching feedback by category:', data.message);
                        return;
                    }

                    if (feedbackChart) {
                        feedbackChart.destroy();
                    }

                    const chartData = processCategoryFeedbackData(data.data);
                    initializeFeedbackChart(chartData, 'category');
                    updateCategoryList(data.data);
                })
                .catch(error => console.error('Error initializing feedback chart:', error));
        }

        function updateFeedbackSubcategories() {
            const categoryId = document.getElementById('feedbackCategoryFilter').value;
            const subcategoryFilter = document.getElementById('feedbackSubcategoryFilter');
            const articlesList = document.getElementById('subcategoryArticlesList');
            
            // Reset subcategory filter
            subcategoryFilter.innerHTML = '<option value="">Select Subcategory</option>';
            subcategoryFilter.disabled = true;
            
            if (!categoryId) {
                // No category selected - show categories chart
                if (feedbackChart) {
                    feedbackChart.destroy();
                }
                initializeFeedbackChartWithCategories();
                return;
            }

            // Fetch subcategories for the selected category
            fetch(`get_subcategories.php?category_id=${categoryId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success || data.data.length === 0) {
                        subcategoryFilter.innerHTML = '<option value="">No subcategories available</option>';
                        return;
                    }

                    // Populate subcategory dropdown
                    data.data.forEach(subcategory => {
                        const option = document.createElement('option');
                        option.value = subcategory.subcategory_id;
                        option.textContent = subcategory.subcategory_name;
                        subcategoryFilter.appendChild(option);
                    });
                    
                    subcategoryFilter.disabled = false;

                    // Show subcategories feedback by default
                    fetch(`get_feedback_by_subcategory.php?category_id=${categoryId}`)
                        .then(response => response.json())
                        .then(subcatData => {
                            if (!subcatData.success) {
                                console.error('Error fetching feedback by subcategory:', subcatData.message);
                                return;
                            }

                            if (feedbackChart) {
                                feedbackChart.destroy();
                            }

                            const chartData = processSubcategoryFeedbackData(subcatData.data);
                            initializeFeedbackChart(chartData, 'subcategory');
                            updateSubcategoryList(subcatData.data);
                        })
                        .catch(error => console.error('Error fetching subcategory feedback:', error));
                })
                .catch(error => console.error('Error fetching subcategories:', error));
        }

        function updateFeedbackChart() {
            const subcategoryId = document.getElementById('feedbackSubcategoryFilter').value;
            const noteDiv = document.getElementById('feedbackChartNote');
            const articlesList = document.getElementById('subcategoryArticlesList');
            
            if (!subcategoryId) {
                // No subcategory selected - show subcategories chart
                const categoryId = document.getElementById('feedbackCategoryFilter').value;
                if (categoryId) {
                    fetch(`get_feedback_by_subcategory.php?category_id=${categoryId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                console.error('Error fetching feedback data:', data.message);
                                return;
                            }

                            if (feedbackChart) {
                                feedbackChart.destroy();
                            }

                            const chartData = processSubcategoryFeedbackData(data.data);
                            initializeFeedbackChart(chartData, 'subcategory');
                            updateSubcategoryList(data.data);
                            noteDiv.style.display = 'block';
                        })
                        .catch(error => console.error('Error updating feedback chart:', error));
                }
                return;
            }

            // Subcategory selected - show articles chart
            fetch(`get_feedback_chart_data.php?subcategory_id=${subcategoryId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error('Error fetching feedback data:', data.message);
                        return;
                    }

                    if (feedbackChart) {
                        feedbackChart.destroy();
                    }

                    const chartData = processFeedbackData(data.data);
                    initializeFeedbackChart(chartData, 'articles');
                    populateArticlesList(data.data);
                    noteDiv.style.display = 'block';
                })
                .catch(error => console.error('Error updating feedback chart:', error));
        }

        function processCategoryFeedbackData(categoriesData) {
            const labels = [];
            const likesData = [];
            const dislikesData = [];
            const tooltipData = [];

            categoriesData.forEach((category) => {
                labels.push(category.category_name);
                likesData.push(category.likes);
                dislikesData.push(category.dislikes);
                tooltipData.push({
                    title: category.category_name,
                    likes: category.likes,
                    dislikes: category.dislikes,
                    total: category.likes + category.dislikes
                });
            });

            return { labels, likesData, dislikesData, tooltipData };
        }

        function processSubcategoryFeedbackData(subcategoriesData) {
            const labels = [];
            const likesData = [];
            const dislikesData = [];
            const tooltipData = [];

            subcategoriesData.forEach((subcategory) => {
                labels.push(subcategory.subcategory_name);
                likesData.push(subcategory.likes);
                dislikesData.push(subcategory.dislikes);
                tooltipData.push({
                    title: subcategory.subcategory_name,
                    likes: subcategory.likes,
                    dislikes: subcategory.dislikes,
                    total: subcategory.likes + subcategory.dislikes
                });
            });

            return { labels, likesData, dislikesData, tooltipData };
        }

        function updateCategoryList(categoriesData) {
            const articlesList = document.getElementById('subcategoryArticlesList');
            
            if (categoriesData.length === 0) {
                articlesList.innerHTML = '<div style="padding: 2rem; text-align: center; color: #9CA3AF; font-size: 0.875rem;">No categories available</div>';
                return;
            }

            let html = '<div style="display: flex; flex-direction: column;">';
            categoriesData.forEach((category, index) => {
                const total = category.likes + category.dislikes;
                const isLast = index === categoriesData.length - 1;
                html += `
                    <div style="padding: 1.25rem; ${!isLast ? 'border-bottom: 1px solid #E5E7EB;' : ''} user-select: none;">
                        <h4 style="margin: 0 0 0.75rem 0; font-size: 0.875rem; font-weight: 600; color: #374151; line-height: 1.3; word-break: break-word;">
                            ${category.category_name}
                        </h4>
                        <div style="display: flex; gap: 1.5rem; font-size: 0.8rem; color: #6B7280; flex-wrap: wrap;">
                            <span style="color: #28ae81; font-weight: 500; display: flex; align-items: center; gap: 0.375rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 1024 1024"><path fill="currentColor" d="M885.9 533.7c16.8-22.2 26.1-49.4 26.1-77.7c0-44.9-25.1-87.4-65.5-111.1a67.67 67.67 0 0 0-34.3-9.3H572.4l6-122.9c1.4-29.7-9.1-57.9-29.5-79.4A106.62 106.62 0 0 0 471 99.9c-52 0-98 35-111.8 85.1l-85.9 311H144c-17.7 0-32 14.3-32 32v364c0 17.7 14.3 32 32 32h601.3c9.2 0 18.2-1.8 26.5-5.4c47.6-20.3 78.3-66.8 78.3-118.4c0-12.6-1.8-25-5.4-37c16.8-22.2 26.1-49.4 26.1-77.7c0-12.6-1.8-25-5.4-37c16.8-22.2 26.1-49.4 26.1-77.7c-.2-12.6-2-25.1-5.6-37.1M184 852V568h81v284zm636.4-353l-21.9 19l13.9 25.4a56.2 56.2 0 0 1 6.9 27.3c0 16.5-7.2 32.2-19.6 43l-21.9 19l13.9 25.4a56.2 56.2 0 0 1 6.9 27.3c0 16.5-7.2 32.2-19.6 43l-21.9 19l13.9 25.4a56.2 56.2 0 0 1 6.9 27.3c0 22.4-13.2 42.6-33.6 51.8H329V564.8l99.5-360.5a44.1 44.1 0 0 1 42.2-32.3c7.6 0 15.1 2.2 21.1 6.7c9.9 7.4 15.2 18.6 14.6 30.5l-9.6 198.4h314.4C829 418.5 840 436.9 840 456c0 16.5-7.2 32.1-19.6 43"/></svg>
                                ${category.likes} Likes
                            </span>
                            <span style="color: #d84a4a; font-weight: 500; display: flex; align-items: center; gap: 0.375rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 1024 1024"><path fill="currentColor" d="M885.9 490.3c3.6-12 5.4-24.4 5.4-37c0-28.3-9.3-55.5-26.1-77.7c3.6-12 5.4-24.4 5.4-37c0-28.3-9.3-55.5-26.1-77.7c3.6-12 5.4-24.4 5.4-37c0-51.6-30.7-98.1-78.3-118.4a66.1 66.1 0 0 0-26.5-5.4H144c-17.7 0-32 14.3-32 32v364c0 17.7 14.3 32 32 32h129.3l85.8 310.8C372.9 889 418.9 924 470.9 924c29.7 0 57.4-11.8 77.9-33.4c20.5-21.5 31-49.7 29.5-79.4l-6-122.9h239.9c12.1 0 23.9-3.2 34.3-9.3c40.4-23.5 65.5-66.1 65.5-111c0-28.3-9.3-55.5-26.1-77.7M184 456V172h81v284zm627.2 160.4H496.8l9.6 198.4c.6 11.9-4.7 23.1-14.6 30.5c-6.1 4.5-13.6 6.8-21.1 6.7a44.28 44.28 0 0 1-42.2-32.3L329 459.2V172h415.4a56.85 56.85 0 0 1 33.6 51.8c0 9.7-2.3 18.9-6.9 27.3l-13.9 25.4l21.9 19a56.76 56.76 0 0 1 19.6 43c0 9.7-2.3 18.9-6.9 27.3l-13.9 25.4l21.9 19a56.76 56.76 0 0 1 19.6 43c0 9.7-2.3 18.9-6.9 27.3l-14 25.5l21.9 19a56.76 56.76 0 0 1 19.6 43c0 19.1-11 37.5-28.8 48.4"/></svg>
                                ${category.dislikes} Dislikes
                            </span>
                            <span style="color: #9CA3AF;">Total: ${total}</span>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            articlesList.innerHTML = html;
        }

        function updateSubcategoryList(subcategoriesData) {
            const articlesList = document.getElementById('subcategoryArticlesList');
            
            if (subcategoriesData.length === 0) {
                articlesList.innerHTML = '<div style="padding: 2rem; text-align: center; color: #9CA3AF; font-size: 0.875rem;">No subcategories available</div>';
                return;
            }

            let html = '<div style="display: flex; flex-direction: column;">';
            subcategoriesData.forEach((subcategory, index) => {
                const total = subcategory.likes + subcategory.dislikes;
                const isLast = index === subcategoriesData.length - 1;
                html += `
                    <div style="padding: 1.25rem; ${!isLast ? 'border-bottom: 1px solid #E5E7EB;' : ''} user-select: none;">
                        <h4 style="margin: 0 0 0.75rem 0; font-size: 0.875rem; font-weight: 600; color: #374151; line-height: 1.3; word-break: break-word;">
                            ${subcategory.subcategory_name}
                        </h4>
                        <div style="display: flex; gap: 1.5rem; font-size: 0.8rem; color: #6B7280; flex-wrap: wrap;">
                            <span style="color: #3fdda8; font-weight: 500; display: flex; align-items: center; gap: 0.375rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 1024 1024"><path fill="currentColor" d="M885.9 533.7c16.8-22.2 26.1-49.4 26.1-77.7c0-44.9-25.1-87.4-65.5-111.1a67.67 67.67 0 0 0-34.3-9.3H572.4l6-122.9c1.4-29.7-9.1-57.9-29.5-79.4A106.62 106.62 0 0 0 471 99.9c-52 0-98 35-111.8 85.1l-85.9 311H144c-17.7 0-32 14.3-32 32v364c0 17.7 14.3 32 32 32h601.3c9.2 0 18.2-1.8 26.5-5.4c47.6-20.3 78.3-66.8 78.3-118.4c0-12.6-1.8-25-5.4-37c16.8-22.2 26.1-49.4 26.1-77.7c0-12.6-1.8-25-5.4-37c16.8-22.2 26.1-49.4 26.1-77.7c-.2-12.6-2-25.1-5.6-37.1M184 852V568h81v284zm636.4-353l-21.9 19l13.9 25.4a56.2 56.2 0 0 1 6.9 27.3c0 16.5-7.2 32.2-19.6 43l-21.9 19l13.9 25.4a56.2 56.2 0 0 1 6.9 27.3c0 16.5-7.2 32.2-19.6 43l-21.9 19l13.9 25.4a56.2 56.2 0 0 1 6.9 27.3c0 22.4-13.2 42.6-33.6 51.8H329V564.8l99.5-360.5a44.1 44.1 0 0 1 42.2-32.3c7.6 0 15.1 2.2 21.1 6.7c9.9 7.4 15.2 18.6 14.6 30.5l-9.6 198.4h314.4C829 418.5 840 436.9 840 456c0 16.5-7.2 32.1-19.6 43"/></svg>
                                ${subcategory.likes} Likes
                            </span>
                            <span style="color: #e65f5f; font-weight: 500; display: flex; align-items: center; gap: 0.375rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 1024 1024"><path fill="currentColor" d="M885.9 490.3c3.6-12 5.4-24.4 5.4-37c0-28.3-9.3-55.5-26.1-77.7c3.6-12 5.4-24.4 5.4-37c0-28.3-9.3-55.5-26.1-77.7c3.6-12 5.4-24.4 5.4-37c0-51.6-30.7-98.1-78.3-118.4a66.1 66.1 0 0 0-26.5-5.4H144c-17.7 0-32 14.3-32 32v364c0 17.7 14.3 32 32 32h129.3l85.8 310.8C372.9 889 418.9 924 470.9 924c29.7 0 57.4-11.8 77.9-33.4c20.5-21.5 31-49.7 29.5-79.4l-6-122.9h239.9c12.1 0 23.9-3.2 34.3-9.3c40.4-23.5 65.5-66.1 65.5-111c0-28.3-9.3-55.5-26.1-77.7M184 456V172h81v284zm627.2 160.4H496.8l9.6 198.4c.6 11.9-4.7 23.1-14.6 30.5c-6.1 4.5-13.6 6.8-21.1 6.7a44.28 44.28 0 0 1-42.2-32.3L329 459.2V172h415.4a56.85 56.85 0 0 1 33.6 51.8c0 9.7-2.3 18.9-6.9 27.3l-13.9 25.4l21.9 19a56.76 56.76 0 0 1 19.6 43c0 9.7-2.3 18.9-6.9 27.3l-13.9 25.4l21.9 19a56.76 56.76 0 0 1 19.6 43c0 9.7-2.3 18.9-6.9 27.3l-14 25.5l21.9 19a56.76 56.76 0 0 1 19.6 43c0 19.1-11 37.5-28.8 48.4"/></svg>
                                ${subcategory.dislikes} Dislikes
                            </span>
                            <span style="color: #9CA3AF;">Total: ${total}</span>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            articlesList.innerHTML = html;
        }

        function processFeedbackData(articlesData) {
            const labels = [];
            const likesData = [];
            const dislikesData = [];
            const tooltipData = [];

            articlesData.forEach((article) => {
                labels.push(article.title);
                likesData.push(article.likes);
                dislikesData.push(article.dislikes);
                tooltipData.push({
                    title: article.title,
                    likes: article.likes,
                    dislikes: article.dislikes,
                    total: article.likes + article.dislikes
                });
            });

            return { labels, likesData, dislikesData, tooltipData };
        }

        function populateArticlesList(articlesData) {
            const articlesList = document.getElementById('subcategoryArticlesList');
            
            if (articlesData.length === 0) {
                articlesList.innerHTML = '<div style="padding: 2rem; text-align: center; color: #9CA3AF; font-size: 0.875rem;">No articles in this subcategory</div>';
                return;
            }

            let html = '<div style="display: flex; flex-direction: column;">';
            articlesData.forEach((article, index) => {
                const total = article.likes + article.dislikes;
                const isLast = index === articlesData.length - 1;
                html += `
                    <div style="padding: 1.25rem; ${!isLast ? 'border-bottom: 1px solid #E5E7EB;' : ''} cursor: pointer; transition: background-color 0.2s; user-select: none;" 
                         onclick="window.location.href='../client/view_article.php?article_id=${article.article_id}';" 
                         onmouseover="this.style.backgroundColor='#F3F4F6'"
                         onmouseout="this.style.backgroundColor='transparent'">
                        <h4 style="margin: 0 0 0.75rem 0; font-size: 0.875rem; font-weight: 600; color: #374151; line-height: 1.3; word-break: break-word;">
                            ${article.title}
                        </h4>
                        <div style="display: flex; gap: 1.5rem; font-size: 0.8rem; color: #6B7280; flex-wrap: wrap;">
                            <span style="color: #3fdda8; font-weight: 500; display: flex; align-items: center; gap: 0.375rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 1024 1024"><path fill="currentColor" d="M885.9 533.7c16.8-22.2 26.1-49.4 26.1-77.7c0-44.9-25.1-87.4-65.5-111.1a67.67 67.67 0 0 0-34.3-9.3H572.4l6-122.9c1.4-29.7-9.1-57.9-29.5-79.4A106.62 106.62 0 0 0 471 99.9c-52 0-98 35-111.8 85.1l-85.9 311H144c-17.7 0-32 14.3-32 32v364c0 17.7 14.3 32 32 32h601.3c9.2 0 18.2-1.8 26.5-5.4c47.6-20.3 78.3-66.8 78.3-118.4c0-12.6-1.8-25-5.4-37c16.8-22.2 26.1-49.4 26.1-77.7c0-12.6-1.8-25-5.4-37c16.8-22.2 26.1-49.4 26.1-77.7c-.2-12.6-2-25.1-5.6-37.1M184 852V568h81v284zm636.4-353l-21.9 19l13.9 25.4a56.2 56.2 0 0 1 6.9 27.3c0 16.5-7.2 32.2-19.6 43l-21.9 19l13.9 25.4a56.2 56.2 0 0 1 6.9 27.3c0 16.5-7.2 32.2-19.6 43l-21.9 19l13.9 25.4a56.2 56.2 0 0 1 6.9 27.3c0 22.4-13.2 42.6-33.6 51.8H329V564.8l99.5-360.5a44.1 44.1 0 0 1 42.2-32.3c7.6 0 15.1 2.2 21.1 6.7c9.9 7.4 15.2 18.6 14.6 30.5l-9.6 198.4h314.4C829 418.5 840 436.9 840 456c0 16.5-7.2 32.1-19.6 43"/></svg>
                                ${article.likes} Likes
                            </span>
                            <span style="color: #e65f5f; font-weight: 500; display: flex; align-items: center; gap: 0.375rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 1024 1024"><path fill="currentColor" d="M885.9 490.3c3.6-12 5.4-24.4 5.4-37c0-28.3-9.3-55.5-26.1-77.7c3.6-12 5.4-24.4 5.4-37c0-28.3-9.3-55.5-26.1-77.7c3.6-12 5.4-24.4 5.4-37c0-51.6-30.7-98.1-78.3-118.4a66.1 66.1 0 0 0-26.5-5.4H144c-17.7 0-32 14.3-32 32v364c0 17.7 14.3 32 32 32h129.3l85.8 310.8C372.9 889 418.9 924 470.9 924c29.7 0 57.4-11.8 77.9-33.4c20.5-21.5 31-49.7 29.5-79.4l-6-122.9h239.9c12.1 0 23.9-3.2 34.3-9.3c40.4-23.5 65.5-66.1 65.5-111c0-28.3-9.3-55.5-26.1-77.7M184 456V172h81v284zm627.2 160.4H496.8l9.6 198.4c.6 11.9-4.7 23.1-14.6 30.5c-6.1 4.5-13.6 6.8-21.1 6.7a44.28 44.28 0 0 1-42.2-32.3L329 459.2V172h415.4a56.85 56.85 0 0 1 33.6 51.8c0 9.7-2.3 18.9-6.9 27.3l-13.9 25.4l21.9 19a56.76 56.76 0 0 1 19.6 43c0 9.7-2.3 18.9-6.9 27.3l-13.9 25.4l21.9 19a56.76 56.76 0 0 1 19.6 43c0 9.7-2.3 18.9-6.9 27.3l-14 25.5l21.9 19a56.76 56.76 0 0 1 19.6 43c0 19.1-11 37.5-28.8 48.4"/></svg>
                                ${article.dislikes} Dislikes
                            </span>
                            <span style="color: #8c9097;">Total: ${total}</span>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            articlesList.innerHTML = html;
        }

        function initializeFeedbackChart(chartData, chartType = 'articles') {
            const feedbackCtx = document.getElementById('feedbackChart').getContext('2d');
            
            feedbackChart = new Chart(feedbackCtx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Likes',
                            data: chartData.likesData,
                            backgroundColor: '#34cf9b',
                            borderRadius: 4,
                            borderSkipped: false
                        },
                        {
                            label: 'Dislikes',
                            data: chartData.dislikesData,
                            backgroundColor: '#f46161',
                            borderRadius: 4,
                            borderSkipped: false
                        }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: { size: 14, weight: 'bold' },
                            bodyFont: { size: 13 },
                            borderColor: '#E5E7EB',
                            borderWidth: 1,
                            cornerRadius: 6,
                            displayColors: false,
                            callbacks: {
                                title: function(context) {
                                    const index = context[0].dataIndex;
                                    if (chartData.tooltipData[index]) {
                                        return chartData.tooltipData[index].title;
                                    }
                                    return context[0].label;
                                },
                                label: function(context) {
                                    const index = context.dataIndex;
                                    const data = chartData.tooltipData[index];
                                    if (!data) return '';
                                    
                                    if (context.datasetIndex === 0) {
                                        return `Likes: ${data.likes}`;
                                    } else {
                                        return `Dislikes: ${data.dislikes}`;
                                    }
                                },
                                footer: function(context) {
                                    const index = context[0].dataIndex;
                                    const data = chartData.tooltipData[index];
                                    if (!data) return '';
                                    return `Total Feedback: ${data.total}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        },
                        y: {
                            stacked: true
                        }
                    }
                }
            });
        }
    </script>

    <!-- Article Preview Modal (Client-Side View) -->
    <div id="previewModalOverlay" class="modal-overlay" style="display: none; background: rgba(0, 0, 0, 0.5); position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 1000; overflow-y: auto;">
        <div class="modal-content" onclick="event.stopPropagation()" style="max-width: 90%; max-height: 90vh; width: 90vw; height: 90vh; padding: 0; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; margin: 5vh auto; background: white;">
            <!-- Preview Header -->
            <div style="background: linear-gradient(135deg, #3B82F6 0%, #1E40AF 100%); color: white; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; gap: 1rem;">
                <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700;">Article Preview</h2>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <!-- Close Button -->
                    <button onclick="closeRecentArticlePreviewModal()" style="background: rgba(255, 255, 255, 0.2); color: white; border: none; cursor: pointer; padding: 0.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; transition: background-color 0.2s;"
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

    <script>
        // Open Recent Article Preview
        function openRecentArticlePreview(articleId) {
            if (articleId) {
                const previewIframe = document.getElementById('previewIframe');
                previewIframe.src = `../client/view_article.php?id=${articleId}&preview_mode=admin`;
                
                const previewModal = document.getElementById('previewModalOverlay');
                previewModal.style.display = 'flex';
                previewModal.style.alignItems = 'center';
                previewModal.style.justifyContent = 'center';
            }
        }

        // Close Recent Article Preview
        function closeRecentArticlePreviewModal() {
            const previewModal = document.getElementById('previewModalOverlay');
            previewModal.style.display = 'none';
            
            // Clear iframe
            const previewIframe = document.getElementById('previewIframe');
            previewIframe.src = '';
        }

        // Close modal on overlay click (outside the modal content)
        document.addEventListener('DOMContentLoaded', function() {
            const previewOverlay = document.getElementById('previewModalOverlay');
            if (previewOverlay) {
                previewOverlay.addEventListener('click', function(e) {
                    if (e.target === previewOverlay) {
                        closeRecentArticlePreviewModal();
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
include 'db.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (!empty($title) && !empty($category) && !empty($description)) {
        $title = $conn->real_escape_string($title);
        $category = $conn->real_escape_string($category);
        $description = $conn->real_escape_string($description);
        
        // Set admin_id to current logged-in admin
        $admin_id = $_SESSION['admin_id'];
        
        $sql = "INSERT INTO ARTICLES (title, content, category, admin_id, article_date) 
                VALUES ('$title', '$description', '$category', $admin_id, CURDATE())";
        
        if ($conn->query($sql)) {
            // Get the inserted article ID
            $article_id = $conn->insert_id;
            
            // Log the activity
            $user_id = $_SESSION['user_id'];
            $title_escaped = $conn->real_escape_string($title);
            $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, new_value) 
                        VALUES ($user_id, 'CREATE', 'Article', $article_id, '$title_escaped')";
            if (!$conn->query($log_sql)) {
                error_log("Activity log error: " . $conn->error);
            }
            
            $message = 'Article created successfully!';
            $message_type = 'success';
            header('Location: articles.php?msg=' . urlencode($message) . '&msg_type=success');
            exit();
        } else {
            $message = 'Error creating article: ' . $conn->error;
            $message_type = 'error';
        }
    } else {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS Genie | New Article</title>
    <link rel="icon" type="image/jpeg" href="assets/img/intellismart.jpg">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>

        <main class="content">
            <div class="content-header">
                <h1>New Article</h1>
                <p>Create a new article for actionable support for FAQ</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" class="article-form">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" placeholder="Enter Article Title" required>
                    </div>

                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="Attendance">Attendance</option>
                            <option value="Payroll & Compensation">Payroll & Compensation</option>
                            <option value="Technology Support">Technology Support</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="8" placeholder="Enter your Description here..." required></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="articles.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-create">Create Article</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

<?php $conn->close(); ?>

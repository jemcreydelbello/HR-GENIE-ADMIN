<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
include 'db.php';

$message = '';
$message_type = '';
$article = null;

// Get article ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: articles.php');
    exit();
}

// Fetch article
$sql = "SELECT * FROM ARTICLES WHERE article_id = $id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $article = $result->fetch_assoc();
} else {
    header('Location: articles.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (!empty($title) && !empty($category) && !empty($description)) {
        $title = $conn->real_escape_string($title);
        $category = $conn->real_escape_string($category);
        $description = $conn->real_escape_string($description);
        
        // Keep existing admin_id for tracking
        $admin_id = $article['admin_id'];
        if (intval($admin_id) > 0) {
            $admin_id = intval($admin_id);
        }
        $id = intval($id);

        $sql = "UPDATE ARTICLES SET 
                title = '$title',
                content = '$description',
                category = '$category',
                admin_id = $admin_id,
                article_date = NOW()
                WHERE article_id = $id";
        
        if ($conn->query($sql)) {
            // Log the activity with old and new values
            $user_id = $_SESSION['user_id'];
            $old_value = "Title: " . $article['title'] . " | Category: " . $article['category'];
            $new_value = "Title: " . $title . " | Category: " . $category;
            $old_value_escaped = $conn->real_escape_string($old_value);
            $new_value_escaped = $conn->real_escape_string($new_value);
            $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, old_value, new_value) 
                        VALUES ($user_id, 'UPDATE', 'Article', $id, '$old_value_escaped', '$new_value_escaped')";
            $conn->query($log_sql);
            
            $message = 'Article updated successfully!';
            $message_type = 'success';
            header('Location: articles.php?msg=' . urlencode($message) . '&msg_type=success');
            exit();
        } else {
            $message = 'Error updating article: ' . $conn->error;
            $message_type = 'error';
        }
    } else {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    }
    
    // Refresh article data
    $sql = "SELECT * FROM ARTICLES WHERE article_id = $id";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $article = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hrdotnet Genie | Edit Article</title>
    <link rel="icon" type="image/jpeg" href="admin/assets/img/intellismart.jpg">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>

        <main class="content">
            <div class="content-header">
                <h1>Edit Article</h1>
                <p>Update article information</p>
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
                        <input type="text" id="title" name="title" placeholder="Enter Article Title" value="<?= htmlspecialchars($article['title']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="category">Article Type</label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="Attendance" <?= $article['category'] === 'Attendance' ? 'selected' : '' ?>>Simple Question</option>
                            <option value="Payroll & Compensation" <?= $article['category'] === 'Payroll & Compensation' ? 'selected' : '' ?>>Step by Step Guide</option>
                            <option value="Technology Support" <?= $article['category'] === 'Technology Support' ? 'selected' : '' ?>>Standard Article</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="8" placeholder="Enter your Description here..." required><?= htmlspecialchars($article['content']) ?></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="articles.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-create">Update Article</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

<?php $conn->close(); ?>
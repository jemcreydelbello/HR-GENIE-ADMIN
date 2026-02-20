<?php
// Suppress warnings and notices that could interfere with JSON response
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Start output buffering to catch any unexpected output
ob_start();

// Add shutdown handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        header('Content-Type: application/json; charset=utf-8');
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $error['message'],
            'error_type' => $error['type'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});

header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['admin_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include 'db.php';

// Check for database connection errors
if (!isset($conn) || $conn->connect_error) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid article id']);
            exit();
        }

        $sql = "SELECT * FROM ARTICLES WHERE article_id = $id";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $article = $result->fetch_assoc();
            
            // Fetch category_id from subcategory_id if available
            $category_id = 0;
            if (!empty($article['subcategory_id'])) {
                $cat_lookup_sql = "SELECT category_id FROM subcategories WHERE subcategory_id = ?";
                $cat_lookup_stmt = $conn->prepare($cat_lookup_sql);
                $cat_lookup_stmt->bind_param('i', $article['subcategory_id']);
                $cat_lookup_stmt->execute();
                $cat_lookup_result = $cat_lookup_stmt->get_result();
                if ($cat_lookup_result && $cat_lookup_result->num_rows > 0) {
                    $cat_row = $cat_lookup_result->fetch_assoc();
                    $category_id = intval($cat_row['category_id']);
                }
                $cat_lookup_stmt->close();
            }
            $article['category_id'] = $category_id;
            
            // Fetch tags for this article
            $tags_sql = "SELECT t.tag_id, t.tag_name FROM tags t 
                         INNER JOIN article_tags at ON t.tag_id = at.tag_id 
                         WHERE at.article_id = ?";
            $tags_stmt = $conn->prepare($tags_sql);
            $tags_stmt->bind_param('i', $id);
            $tags_stmt->execute();
            $tags_result = $tags_stmt->get_result();
            $article['tags'] = [];
            if ($tags_result && $tags_result->num_rows > 0) {
                while ($tag_row = $tags_result->fetch_assoc()) {
                    $article['tags'][] = [
                        'tag_id' => (int)$tag_row['tag_id'],
                        'tag_name' => $tag_row['tag_name']
                    ];
                }
            }
            
            // Detect article type: Check article_steps table first for step-by-step articles
            $content = $article['content'];
            $article['steps'] = [];
            
            // Check if this is a step-by-step article by looking in article_steps table
            $check_steps_sql = "SELECT step_number, step_title, step_description, step_image FROM article_steps WHERE article_id = ? ORDER BY step_number ASC";
            $check_steps_stmt = $conn->prepare($check_steps_sql);
            $check_steps_stmt->bind_param('i', $id);
            $check_steps_stmt->execute();
            $check_steps_result = $check_steps_stmt->get_result();
            
            if ($check_steps_result && $check_steps_result->num_rows > 0) {
                // This is a step-by-step article
                $article['type'] = 'step_by_step';
                
                // Introduction is now stored in the articles table
                while ($step_row = $check_steps_result->fetch_assoc()) {
                    $article['steps'][] = [
                        'step_num' => intval($step_row['step_number']),
                        'title' => $step_row['step_title'],
                        'description' => $step_row['step_description'],
                        'image' => $step_row['step_image']
                    ];
                }
            } else if (strpos($content, 'Q: ') === 0 && strpos($content, 'A: ') !== false) {
                // Simple question format: Q: question\n\nA: answer
                $article['type'] = 'simple_question';
                preg_match('/Q: (.*?)\n\nA: (.*)/s', $content, $matches);
                $article['question'] = isset($matches[1]) ? trim($matches[1]) : '';
                $article['answer'] = isset($matches[2]) ? trim($matches[2]) : '';
                
            } else {
                // Standard article
                $article['type'] = 'standard';
                
                // Fetch description and image from article_standard table
                $standard_sql = "SELECT description, standard_image FROM article_standard WHERE article_id = ?";
                $standard_stmt = $conn->prepare($standard_sql);
                $standard_stmt->bind_param('i', $id);
                $standard_stmt->execute();
                $standard_result = $standard_stmt->get_result();
                
                if ($standard_result && $standard_result->num_rows > 0) {
                    $standard_row = $standard_result->fetch_assoc();
                    $article['description'] = $standard_row['description'];
                    $article['standard_image'] = $standard_row['standard_image'];
                } else {
                    // Fallback to content field if article_standard doesn't have record
                    $article['description'] = $article['content'];
                    $article['standard_image'] = null;
                }
            }
            
            echo json_encode(['success' => true, 'article' => $article]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Article not found']);
        }
        exit();
    }

    if ($method === 'POST') {
        // Force integer id if provided as form field
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $title = $_POST['title'] ?? '';
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $category = $_POST['category'] ?? '';
        $subcategory_id = isset($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : 0;
        $type = $_POST['type'] ?? 'standard';
        $status = $_POST['status'] ?? 'Publish';  // Get status from form

        // If category_id was provided, look up the category name
        if ($category_id > 0) {
            $cat_lookup_sql = "SELECT category_name FROM CATEGORIES WHERE category_id = $category_id";
            $cat_lookup_result = $conn->query($cat_lookup_sql);
            if ($cat_lookup_result && $cat_lookup_result->num_rows > 0) {
                $cat_row = $cat_lookup_result->fetch_assoc();
                $category = $cat_row['category_name'];
            }
        }

        if ($id <= 0 || trim($title) === '' || (trim($category) === '' && $category_id <= 0)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
            exit();
        }
        
        // If subcategory_id wasn't sent, try to look it up from the subcategory_name
        if ($subcategory_id <= 0 && !empty($category)) {
            $lookup_sql = "SELECT subcategory_id FROM subcategories WHERE subcategory_name = ?";
            $lookup_stmt = $conn->prepare($lookup_sql);
            if ($lookup_stmt) {
                $lookup_stmt->bind_param('s', $category);
                $lookup_stmt->execute();
                $lookup_result = $lookup_stmt->get_result();
                if ($lookup_result && $lookup_result->num_rows > 0) {
                    $lookup_row = $lookup_result->fetch_assoc();
                    $subcategory_id = intval($lookup_row['subcategory_id']);
                }
                $lookup_stmt->close();
            }
        }

        // Escape inputs
        $title = $conn->real_escape_string($title);
        $category = $conn->real_escape_string($category);
        $type = $conn->real_escape_string($type);
        $status = $conn->real_escape_string($status);  // Escape status
        
        // Handle file uploads for step-by-step articles - collect all step images
        $article_images = [];
        $standard_image = null;
        $upload_dir = 'uploads/articles/';
        
        if ($type === 'step_by_step') {
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Parse existing images from database
            $old_image_sql = "SELECT article_image FROM ARTICLES WHERE article_id = $id";
            $old_image_result = $conn->query($old_image_sql);
            $existing_images = [];
            if ($old_image_result && $old_image_result->num_rows > 0) {
                $old_row = $old_image_result->fetch_assoc();
                if (!empty($old_row['article_image'])) {
                    $decoded = json_decode($old_row['article_image'], true);
                    if (is_array($decoded)) {
                        // Convert string keys to integer keys for consistency
                        foreach ($decoded as $key => $value) {
                            $existing_images[(int)$key] = $value;
                        }
                    }
                }
            }
            
            // Process files from all steps - need to check which ones have files to upload
            $stepNum = 1;
            // First, find the max step number from POST data
            $max_step = 0;
            foreach ($_POST as $key => $value) {
                if (preg_match('/^step_(\d+)_title$/', $key, $matches)) {
                    $step_num = intval($matches[1]);
                    if ($step_num > $max_step) {
                        $max_step = $step_num;
                    }
                }
            }
            
            // Also check existing images to ensure we don't lose them
            foreach ($existing_images as $stepNum => $imageName) {
                if ($stepNum > $max_step) {
                    $max_step = $stepNum;
                }
            }
            
            // Now process files for all steps from 1 to max_step
            for ($stepNum = 1; $stepNum <= $max_step; $stepNum++) {
                // Only process if file exists and no error
                if (isset($_FILES["step_{$stepNum}_file"]) && $_FILES["step_{$stepNum}_file"]['error'] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES["step_{$stepNum}_file"]['name'];
                    $file_tmp = $_FILES["step_{$stepNum}_file"]['tmp_name'];
                    $file_size = $_FILES["step_{$stepNum}_file"]['size'];
                    $file_type = mime_content_type($file_tmp);
                    
                    // Validate file
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($file_type, $allowed_types)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid file type for Step ' . $stepNum . '. Allowed: JPG, PNG, GIF, PDF, DOC, DOCX']);
                        exit();
                    }
                    
                    if ($file_size > $max_size) {
                        echo json_encode(['success' => false, 'message' => 'File size for Step ' . $stepNum . ' exceeds 5MB limit']);
                        exit();
                    }
                    
                    // Generate unique filename
                    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                    $unique_filename = 'article_' . time() . '_' . uniqid() . '.' . $file_ext;
                    $file_path = $upload_dir . $unique_filename;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Delete old image for this step if exists
                        if (!empty($existing_images[$stepNum])) {
                            $old_path = $upload_dir . $existing_images[$stepNum];
                            if (file_exists($old_path)) {
                                unlink($old_path);
                            }
                        }
                        $article_images[$stepNum] = $unique_filename;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to upload file for Step ' . $stepNum]);
                        exit();
                    }
                }
            }
            
            // Keep existing images for steps that weren't updated
            foreach ($existing_images as $stepNum => $imageName) {
                if (!isset($article_images[$stepNum])) {
                    $article_images[$stepNum] = $imageName;
                }
            }
        }
        
        // Encode images as JSON string for storage
        $article_image = !empty($article_images) ? json_encode($article_images) : null;
        
        // Prepare content based on article type
        $content = '';
        
        if ($type === 'simple_question') {
            $question = $_POST['question'] ?? '';
            $answer = $_POST['answer'] ?? '';
            
            if (empty($question) || empty($answer)) {
                echo json_encode(['success' => false, 'message' => 'Question and Answer are required for Simple Question articles.']);
                exit();
            }
            
            $question = $conn->real_escape_string($question);
            $answer = $conn->real_escape_string($answer);
            $content = "Q: " . $question . "\n\nA: " . $answer;
            
        } else if ($type === 'step_by_step') {
            $content = '';
            
            // Collect all steps
            $stepNum = 1;
            while (isset($_POST["step_{$stepNum}_title"])) {
                $stepTitle = $_POST["step_{$stepNum}_title"] ?? '';
                $stepDesc = $_POST["step_{$stepNum}_description"] ?? '';
                
                if (!empty($stepTitle)) {
                    $stepTitle = $conn->real_escape_string($stepTitle);
                    $stepDesc = $conn->real_escape_string($stepDesc);
                    $content .= ($content ? "\n\n" : "") . "Step " . $stepNum . ": " . $stepTitle . "\n" . $stepDesc;
                }
                $stepNum++;
            }
            
            // Validate that at least one step was provided
            if (empty($content)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'At least one step is required for Step-by-Step articles.']);
                exit();
            }
            
        } else {
            // Standard article
            $description = $_POST['description'] ?? '';
            
            if (empty($description)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Description is required for Standard articles.']);
                exit();
            }
            
            $content = $conn->real_escape_string($description);
            
            // Get existing standard image from database BEFORE processing uploads
            $existing_standard_image = null;
            $get_existing_sql = "SELECT standard_image FROM article_standard WHERE article_id = $id";
            $get_existing_result = $conn->query($get_existing_sql);
            if ($get_existing_result && $get_existing_result->num_rows > 0) {
                $existing_row = $get_existing_result->fetch_assoc();
                $existing_standard_image = $existing_row['standard_image'];
            }
            
            // Handle file upload for standard article image
            if (isset($_FILES['standard_image']) && $_FILES['standard_image']['error'] === UPLOAD_ERR_OK) {
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = $_FILES['standard_image']['name'];
                $file_tmp = $_FILES['standard_image']['tmp_name'];
                $file_size = $_FILES['standard_image']['size'];
                $file_type = mime_content_type($file_tmp);
                
                // Validate file
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file_type, $allowed_types)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, PDF, DOC, DOCX']);
                    exit();
                }
                
                if ($file_size > $max_size) {
                    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
                    exit();
                }
                
                // Generate unique filename
                $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_filename = 'article_' . time() . '_' . uniqid() . '.' . $file_ext;
                $file_path = $upload_dir . $unique_filename;
                
                if (move_uploaded_file($file_tmp, $file_path)) {
                    // Delete old image if exists
                    if (!empty($existing_standard_image)) {
                        $old_path = $upload_dir . $existing_standard_image;
                        if (file_exists($old_path)) {
                            unlink($old_path);
                        }
                    }
                    $standard_image = $unique_filename;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
                    exit();
                }
            } else {
                // No new file uploaded - preserve existing image
                $standard_image = $existing_standard_image;
            }
        }

        // Get admin_id from the currently logged-in admin
        $admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
        if ($admin_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid admin session']);
            exit();
        }
        $author_id = $admin_id;

        $id = intval($id);
        $author_id = intval($author_id);
        
        // Fetch old values before update
        $old_article_sql = "SELECT title, category FROM ARTICLES WHERE article_id = $id";
        $old_result = $conn->query($old_article_sql);
        $old_article = $old_result ? $old_result->fetch_assoc() : null;

        // Get article type and introduction early
        $article_type = $_POST['type'] ?? $article['article_type'];
        $introduction = '';
        $introduction_escaped = '';
        
        if ($article_type === 'step_by_step') {
            $introduction = $_POST['introduction'] ?? ($article['introduction'] ?? '');
            $introduction_escaped = $conn->real_escape_string($introduction);
        }
        
        // Build UPDATE query with optional image
        if ($article_image) {
            $article_image_escaped = $conn->real_escape_string($article_image);
            if ($article_type === 'step_by_step') {
                $sql = "UPDATE ARTICLES SET
                            title = '$title',
                            content = '$content',
                            category = '$category',
                            admin_id = $author_id,
                            article_type = '$article_type',
                            article_image = '$article_image_escaped',
                            article_date = NOW(),
                            subcategory_id = $subcategory_id,
                            introduction = '$introduction_escaped',
                            status = '$status'
                        WHERE article_id = $id";
            } else {
                $sql = "UPDATE ARTICLES SET
                            title = '$title',
                            content = '$content',
                            category = '$category',
                            admin_id = $author_id,
                            article_type = '$article_type',
                            article_image = '$article_image_escaped',
                            article_date = NOW(),
                            subcategory_id = $subcategory_id,
                            status = '$status'
                        WHERE article_id = $id";
            }
        } else {
            if ($article_type === 'step_by_step') {
                $sql = "UPDATE ARTICLES SET
                            title = '$title',
                            content = '$content',
                            category = '$category',
                            admin_id = $author_id,
                            article_type = '$article_type',
                            article_date = NOW(),
                            subcategory_id = $subcategory_id,
                            introduction = '$introduction_escaped',
                            status = '$status'
                        WHERE article_id = $id";
            } else {
                $sql = "UPDATE ARTICLES SET
                            title = '$title',
                            content = '$content',
                            category = '$category',
                            admin_id = $author_id,
                            article_type = '$article_type',
                            article_date = NOW(),
                            subcategory_id = $subcategory_id,
                            status = '$status'
                        WHERE article_id = $id";
            }
        }

        if (!$conn->query($sql)) {
            // Return DB error as JSON
            echo json_encode(['success' => false, 'message' => 'Error updating article: ' . $conn->error]);
            exit();
        }
        
        if ($article_type === 'simple_question') {
            // Check if QA record exists
            $check_sql = "SELECT article_id FROM article_qa WHERE article_id = $id";
            $check_result = $conn->query($check_sql);
            
            $question = $_POST['question'] ?? '';
            $answer = $_POST['answer'] ?? '';
            $question_escaped = $conn->real_escape_string($question);
            $answer_escaped = $conn->real_escape_string($answer);
            
            if ($check_result && $check_result->num_rows > 0) {
                // Update existing record
                $qa_sql = "UPDATE article_qa SET question = '$question_escaped', answer = '$answer_escaped' WHERE article_id = $id";
            } else {
                // Insert new record
                $qa_sql = "INSERT INTO article_qa (article_id, question, answer) VALUES ($id, '$question_escaped', '$answer_escaped')";
            }
            $conn->query($qa_sql);
            
            // Delete from other content tables
            $conn->query("DELETE FROM article_standard WHERE article_id = $id");
            $conn->query("DELETE FROM article_steps WHERE article_id = $id");
            
        } else if ($article_type === 'step_by_step') {
            // Delete existing steps before inserting new ones
            $conn->query("DELETE FROM article_steps WHERE article_id = $id");
            
            // Insert steps
            $stepNum = 1;
            while (isset($_POST["step_{$stepNum}_title"])) {
                $stepTitle = $_POST["step_{$stepNum}_title"] ?? '';
                $stepDesc = $_POST["step_{$stepNum}_description"] ?? '';
                
                if (!empty($stepTitle)) {
                    $stepTitle_escaped = $conn->real_escape_string($stepTitle);
                    $stepDesc_escaped = $conn->real_escape_string($stepDesc);
                    $step_image = $article_images[$stepNum] ?? null;
                    
                    if ($step_image) {
                        $step_image_escaped = $conn->real_escape_string($step_image);
                        $step_sql = "INSERT INTO article_steps (article_id, step_number, step_title, step_description, step_image) 
                                     VALUES ($id, $stepNum, '$stepTitle_escaped', '$stepDesc_escaped', '$step_image_escaped')";
                    } else {
                        $step_sql = "INSERT INTO article_steps (article_id, step_number, step_title, step_description) 
                                     VALUES ($id, $stepNum, '$stepTitle_escaped', '$stepDesc_escaped')";
                    }
                    $conn->query($step_sql);
                }
                $stepNum++;
            }
            
            // Delete from other content tables
            $conn->query("DELETE FROM article_standard WHERE article_id = $id");
            $conn->query("DELETE FROM article_qa WHERE article_id = $id");
            
        } else if ($article_type === 'standard') {
            // Check if standard record exists
            $check_sql = "SELECT article_id FROM article_standard WHERE article_id = $id";
            $check_result = $conn->query($check_sql);
            
            $description = $_POST['description'] ?? '';
            $description_escaped = $conn->real_escape_string($description);
            
            if ($check_result && $check_result->num_rows > 0) {
                // Update existing record
                if (!empty($standard_image)) {
                    $standard_image_escaped = $conn->real_escape_string($standard_image);
                    $standard_sql = "UPDATE article_standard SET description = '$description_escaped', standard_image = '$standard_image_escaped' WHERE article_id = $id";
                } else {
                    $standard_sql = "UPDATE article_standard SET description = '$description_escaped' WHERE article_id = $id";
                }
            } else {
                // Insert new record
                if (!empty($standard_image)) {
                    $standard_image_escaped = $conn->real_escape_string($standard_image);
                    $standard_sql = "INSERT INTO article_standard (article_id, description, standard_image) VALUES ($id, '$description_escaped', '$standard_image_escaped')";
                } else {
                    $standard_sql = "INSERT INTO article_standard (article_id, description) VALUES ($id, '$description_escaped')";
                }
            }
            $conn->query($standard_sql);
            
            // Delete from other content tables
            $conn->query("DELETE FROM article_steps WHERE article_id = $id");
            $conn->query("DELETE FROM article_qa WHERE article_id = $id");
        }

        // Log activity with old and new values
        if ($old_article) {
            $old_value = "Title: " . $old_article['title'] . " | Category: " . $old_article['category'];
            $new_value = "Title: " . $title . " | Category: " . $category;
            $old_value_escaped = $conn->real_escape_string($old_value);
            $new_value_escaped = $conn->real_escape_string($new_value);
            $admin_id = $_SESSION['admin_id'];
            $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, old_value, new_value) VALUES ($admin_id, 'UPDATE', 'Article', $id, '$old_value_escaped', '$new_value_escaped')";
            $conn->query($log_sql);
        }

        // Handle tags if provided
        if (isset($_POST['tags'])) {
            // Delete existing article-tag mappings for this article
            $delete_tags_sql = "DELETE FROM article_tags WHERE article_id = ?";
            $delete_stmt = $conn->prepare($delete_tags_sql);
            $delete_stmt->bind_param('i', $id);
            $delete_stmt->execute();
            
            // Add new tags
            $tag_ids = $_POST['tags'];
            $tags = array_filter(array_map('intval', explode(',', $tag_ids)));
            
            if (count($tags) <= 3 && !empty($tags)) {
                // Insert article-tag mappings for each selected tag_id
                foreach ($tags as $tag_id) {
                    $insert_sql = "INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($insert_sql);
                    $stmt->bind_param('ii', $id, $tag_id);
                    $stmt->execute();
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Article updated successfully!']);
        exit();
    }

} catch (Throwable $e) {
    // Catch any exception or error and return JSON so the client can handle it
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit();
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    // Clean up output buffering if still active
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}
?>
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}
include 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'] ?? '';
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $category = $_POST['category'] ?? '';
    $subcategory_id = isset($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : 0;
    $type = $_POST['type'] ?? 'standard';
    $status = $_POST['status'] ?? 'Publish';  // Default status is 'Publish' (draft)
    $author_id = $_SESSION['admin_id']; // Set admin_id to current logged-in admin
    
    // If category_id was provided, look up the category name
    if ($category_id > 0) {
        $cat_lookup_sql = "SELECT category_name FROM CATEGORIES WHERE category_id = $category_id";
        $cat_lookup_result = $conn->query($cat_lookup_sql);
        if ($cat_lookup_result && $cat_lookup_result->num_rows > 0) {
            $cat_row = $cat_lookup_result->fetch_assoc();
            $category = $cat_row['category_name'];
        }
    }
    
    // Validate required fields
    if (empty($title) || (empty($category) && $category_id <= 0)) {
        echo json_encode(['success' => false, 'message' => 'Title and Category are required.']);
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
        
        // Process files from all steps
        $stepNum = 1;
        while (isset($_FILES["step_{$stepNum}_file"]) && $_FILES["step_{$stepNum}_file"]['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES["step_{$stepNum}_file"]['name'];
            $file_tmp = $_FILES["step_{$stepNum}_file"]['tmp_name'];
            $file_size = $_FILES["step_{$stepNum}_file"]['size'];
            $file_type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file_tmp);
            
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
                $article_images[$stepNum] = $unique_filename;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file for Step ' . $stepNum]);
                exit();
            }
            
            $stepNum++;
        }
    }
    
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
            echo json_encode(['success' => false, 'message' => 'At least one step is required for Step-by-Step articles.']);
            exit();
        }
        
    } else {
        // Standard article
        $description = $_POST['description'] ?? '';
        
        if (empty($description)) {
            echo json_encode(['success' => false, 'message' => 'Description is required for Standard articles.']);
            exit();
        }
        
        $content = $conn->real_escape_string($description);
        
        // Handle file upload for standard article image
        if (isset($_FILES['standard_image']) && $_FILES['standard_image']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = $_FILES['standard_image']['name'];
            $file_tmp = $_FILES['standard_image']['tmp_name'];
            $file_size = $_FILES['standard_image']['size'];
            $file_type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file_tmp);
            
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
                $standard_image = $unique_filename;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
                exit();
            }
        }
    }
    
    // Get admin_id from the currently logged-in admin
    $admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
    if ($admin_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid admin session']);
        exit();
    }
    $author_id = $admin_id;
    
    // Prepare article_image if it exists
    $article_image_filename = null;
    if (!empty($article_images)) {
        // Get the first image or combine them
        $article_image_filename = json_encode($article_images);
    }
    
    // Get introduction for step-by-step articles
    $introduction = $_POST['introduction'] ?? '';
    $introduction_escaped = $conn->real_escape_string($introduction);
    
    // Insert article with subcategory_id and introduction
    if ($article_image_filename) {
        $article_image_escaped = $conn->real_escape_string($article_image_filename);
        if ($type === 'step_by_step' && !empty($introduction)) {
            $sql = "INSERT INTO ARTICLES (title, content, category, admin_id, article_type, article_image, article_date, subcategory_id, introduction, status) 
                    VALUES ('$title', '$content', '$category', $author_id, '$type', '$article_image_escaped', NOW(), $subcategory_id, '$introduction_escaped', '$status')";
        } else {
            $sql = "INSERT INTO ARTICLES (title, content, category, admin_id, article_type, article_image, article_date, subcategory_id, status) 
                    VALUES ('$title', '$content', '$category', $author_id, '$type', '$article_image_escaped', NOW(), $subcategory_id, '$status')";
        }
    } else {
        if ($type === 'step_by_step' && !empty($introduction)) {
            $sql = "INSERT INTO ARTICLES (title, content, category, admin_id, article_type, article_date, subcategory_id, introduction, status) 
                    VALUES ('$title', '$content', '$category', $author_id, '$type', NOW(), $subcategory_id, '$introduction_escaped', '$status')";
        } else {
            $sql = "INSERT INTO ARTICLES (title, content, category, admin_id, article_type, article_date, subcategory_id, status) 
                    VALUES ('$title', '$content', '$category', $author_id, '$type', NOW(), $subcategory_id, '$status')";
        }
    }
    
    if ($conn->query($sql)) {
        $article_id = $conn->insert_id;
        $admin_id = $_SESSION['admin_id'];
        $title_escaped = $conn->real_escape_string($title);
        $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, new_value) 
                    VALUES ($admin_id, 'CREATE', 'Article', $article_id, '$title_escaped')";
        $conn->query($log_sql);
        
        // Insert into type-specific tables
        if ($type === 'simple_question') {
            $question = $_POST['question'] ?? '';
            $answer = $_POST['answer'] ?? '';
            $question_escaped = $conn->real_escape_string($question);
            $answer_escaped = $conn->real_escape_string($answer);
            $qa_sql = "INSERT INTO article_qa (article_id, question, answer) VALUES ($article_id, '$question_escaped', '$answer_escaped')";
            $conn->query($qa_sql);
        } else if ($type === 'step_by_step') {
            // Insert steps into article_steps table
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
                                     VALUES ($article_id, $stepNum, '$stepTitle_escaped', '$stepDesc_escaped', '$step_image_escaped')";
                    } else {
                        $step_sql = "INSERT INTO article_steps (article_id, step_number, step_title, step_description) 
                                     VALUES ($article_id, $stepNum, '$stepTitle_escaped', '$stepDesc_escaped')";
                    }
                    $conn->query($step_sql);
                }
                $stepNum++;
            }
        } else if ($type === 'standard') {
            // Insert into article_standard table
            $description = $_POST['description'] ?? '';
            $description_escaped = $conn->real_escape_string($description);
            
            if (!empty($standard_image)) {
                $standard_image_escaped = $conn->real_escape_string($standard_image);
                $standard_sql = "INSERT INTO article_standard (article_id, description, standard_image) VALUES ($article_id, '$description_escaped', '$standard_image_escaped')";
            } else {
                $standard_sql = "INSERT INTO article_standard (article_id, description) VALUES ($article_id, '$description_escaped')";
            }
            $conn->query($standard_sql);
        }
        
        // Handle tags if provided
        if (!empty($_POST['tags'])) {
            $tag_ids = $_POST['tags'];
            $tags = array_filter(array_map('intval', explode(',', $tag_ids)));
            
            // Validate max 3 tags
            if (count($tags) <= 3 && !empty($tags)) {
                // Insert article-tag mappings for each selected tag_id
                foreach ($tags as $tag_id) {
                    $insert_sql = "INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($insert_sql);
                    $stmt->bind_param('ii', $article_id, $tag_id);
                    $stmt->execute();
                }
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Article created successfully!']);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating article: ' . $conn->error]);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
} catch (Exception $e) {
    error_log("Exception in add_article_modal.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit();
}
$conn->close();
?>

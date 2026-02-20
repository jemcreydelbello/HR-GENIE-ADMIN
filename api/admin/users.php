<?php
session_start();
include 'db.php';

// Check if user is admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Create admin table if not exists
$create_admin_table = "CREATE TABLE IF NOT EXISTS admins (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    admin_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_admin_table);

// Check if admin_image column exists, if not add it
$check_admin_image = $conn->query("SHOW COLUMNS FROM admins LIKE 'admin_image'");
if ($check_admin_image->num_rows === 0) {
    $conn->query("ALTER TABLE admins ADD COLUMN admin_image VARCHAR(255)");
}

// Check if approved column exists, if not add it
$check_column = $conn->query("SHOW COLUMNS FROM GOOGLE_OAUTH_USERS LIKE 'approved'");
if ($check_column->num_rows === 0) {
    $conn->query("ALTER TABLE GOOGLE_OAUTH_USERS ADD COLUMN approved TINYINT(1) DEFAULT 1");
}

// Check if registration_date column exists, if not add it
$check_date = $conn->query("SHOW COLUMNS FROM GOOGLE_OAUTH_USERS LIKE 'registration_date'");
if ($check_date->num_rows === 0) {
    $conn->query("ALTER TABLE GOOGLE_OAUTH_USERS ADD COLUMN registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

// Handle status update via dropdown
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $oauth_id = intval($_POST['user_id']);
    $status = intval($_POST['status']);
    
    $update_sql = "UPDATE GOOGLE_OAUTH_USERS SET approved = ? WHERE oauth_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param('ii', $status, $oauth_id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: users.php?' . http_build_query(['page' => $_GET['page'] ?? 1, 'user_type' => $_GET['user_type'] ?? 'client']));
    exit;
}

// Handle admin CRUD operations
$admin_action = isset($_GET['admin_action']) ? $_GET['admin_action'] : '';

// Create Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_admin') {
    $admin_username = trim($_POST['admin_username'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_full_name = trim($_POST['admin_full_name'] ?? '');
    $admin_image = 'profile.png'; // Default image
    
    // Handle file upload
    if (isset($_FILES['admin_image']) && $_FILES['admin_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['admin_image']['tmp_name'];
        $file_name = $_FILES['admin_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed image extensions
        $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_filename = 'admin_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = 'uploads/admin_images/' . $new_filename;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $admin_image = $upload_path;
            }
        }
    }
    
    if ($admin_username && $admin_email && $admin_password && $admin_full_name) {
        $password_hash = password_hash($admin_password, PASSWORD_BCRYPT);
        
        $insert_sql = "INSERT INTO admins (username, email, password_hash, full_name, admin_image) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        if ($stmt) {
            $stmt->bind_param('sssss', $admin_username, $admin_email, $password_hash, $admin_full_name, $admin_image);
            if ($stmt->execute()) {
                $_SESSION['admin_message'] = 'Admin created successfully';
                $_SESSION['admin_message_type'] = 'success';
            } else {
                $_SESSION['admin_message'] = 'Error creating admin: ' . $stmt->error;
                $_SESSION['admin_message_type'] = 'error';
            }
            $stmt->close();
        }
        header('Location: users.php?user_type=admin');
        exit;
    }
}

// Update Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_admin') {
    $update_admin_id = intval($_POST['update_admin_id']);
    $update_admin_username = trim($_POST['update_admin_username'] ?? '');
    $update_admin_name = trim($_POST['update_admin_full_name'] ?? '');
    $update_admin_email = trim($_POST['update_admin_email'] ?? '');
    $update_admin_image = null;
    
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    // Handle file upload
    if (isset($_FILES['update_admin_image']) && $_FILES['update_admin_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['update_admin_image']['tmp_name'];
        $file_name = $_FILES['update_admin_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed image extensions
        $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        
        if (in_array($file_ext, $allowed_ext)) {
            // Get old image to delete
            $old_image_result = $conn->query("SELECT admin_image FROM admins WHERE admin_id = $update_admin_id");
            if ($old_image_result) {
                $old_image = $old_image_result->fetch_assoc();
                if ($old_image['admin_image'] && $old_image['admin_image'] !== 'profile.png' && file_exists($old_image['admin_image'])) {
                    unlink($old_image['admin_image']);
                }
            }
            
            $new_filename = 'admin_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = 'uploads/admin_images/' . $new_filename;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $update_admin_image = $upload_path;
            }
        }
    }
    
    if ($update_admin_username && $update_admin_name && $update_admin_email) {
        if ($update_admin_image) {
            $update_sql = "UPDATE admins SET username = ?, full_name = ?, email = ?, admin_image = ? WHERE admin_id = ?";
            $stmt = $conn->prepare($update_sql);
            if ($stmt) {
                $stmt->bind_param('ssssi', $update_admin_username, $update_admin_name, $update_admin_email, $update_admin_image, $update_admin_id);
                if ($stmt->execute()) {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Admin updated successfully', 'admin_id' => $update_admin_id, 'email' => $update_admin_email, 'full_name' => $update_admin_name, 'admin_image' => $update_admin_image]);
                        exit;
                    }
                    $_SESSION['admin_message'] = 'Admin updated successfully';
                    $_SESSION['admin_message_type'] = 'success';
                } else {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error updating admin']);
                        exit;
                    }
                    $_SESSION['admin_message'] = 'Error updating admin';
                    $_SESSION['admin_message_type'] = 'error';
                }
                $stmt->close();
            }
        } else {
            $update_sql = "UPDATE admins SET username = ?, full_name = ?, email = ? WHERE admin_id = ?";
            $stmt = $conn->prepare($update_sql);
            if ($stmt) {
                $stmt->bind_param('sssi', $update_admin_username, $update_admin_name, $update_admin_email, $update_admin_id);
                if ($stmt->execute()) {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Admin updated successfully', 'admin_id' => $update_admin_id, 'email' => $update_admin_email, 'full_name' => $update_admin_name]);
                        exit;
                    }
                    $_SESSION['admin_message'] = 'Admin updated successfully';
                    $_SESSION['admin_message_type'] = 'success';
                } else {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error updating admin']);
                        exit;
                    }
                    $_SESSION['admin_message'] = 'Error updating admin';
                    $_SESSION['admin_message_type'] = 'error';
                }
                $stmt->close();
            }
        }
    }
    if (!$is_ajax) {
        header('Location: users.php?user_type=admin');
        exit;
    }
}

// Delete Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
    header('Content-Type: application/json');
    
    $delete_admin_id = intval($_POST['delete_admin_id']);
    
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    // First, delete related records to avoid foreign key constraint violations
    $conn->query("DELETE FROM activity_logs WHERE user_id = $delete_admin_id");
    $conn->query("DELETE FROM categories WHERE created_by = $delete_admin_id");
    $conn->query("DELETE FROM articles WHERE admin_id = $delete_admin_id");
    $conn->query("DELETE FROM subcategories WHERE created_by = $delete_admin_id");
    
    // Now delete the admin
    $delete_sql = "DELETE FROM admins WHERE admin_id = ?";
    $stmt = $conn->prepare($delete_sql);
    if ($stmt) {
        $stmt->bind_param('i', $delete_admin_id);
        if ($stmt->execute()) {
            if ($is_ajax) {
                echo json_encode(['success' => true, 'message' => 'Admin deleted successfully', 'admin_id' => $delete_admin_id]);
                exit;
            }
            $_SESSION['admin_message'] = 'Admin deleted successfully';
            $_SESSION['admin_message_type'] = 'success';
        } else {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'Error deleting admin: ' . $stmt->error]);
                exit;
            }
            $_SESSION['admin_message'] = 'Error deleting admin: ' . $stmt->error;
            $_SESSION['admin_message_type'] = 'error';
        }
        $stmt->close();
    } else {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . $conn->error]);
            exit;
        }
        $_SESSION['admin_message'] = 'Error preparing statement: ' . $conn->error;
        $_SESSION['admin_message_type'] = 'error';
    }
    if (!$is_ajax) {
        header('Location: users.php?user_type=admin');
        exit;
    }
}

// Get filter and search parameters
$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : 'client';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'user_id_desc';

// Pagination settings
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Build query based on user type and filters
$where_clause = "1=1";
$all_users = [];

if ($user_type === 'admin') {
    // Admin users query
    if (!empty($search)) {
        $search_term = $conn->real_escape_string($search);
        $where_clause = "(username LIKE '%$search_term%' OR email LIKE '%$search_term%' OR full_name LIKE '%$search_term%')";
    }
    
    // Determine sort order for admins
    $order_clause = "ORDER BY admin_id DESC";
    switch ($sort) {
        case 'name_asc':
            $order_clause = "ORDER BY full_name ASC";
            break;
        case 'name_desc':
            $order_clause = "ORDER BY full_name DESC";
            break;
        case 'email_asc':
            $order_clause = "ORDER BY email ASC";
            break;
        case 'email_desc':
            $order_clause = "ORDER BY email DESC";
            break;
        case 'user_id_asc':
            $order_clause = "ORDER BY admin_id ASC";
            break;
    }
    
    // Fetch all admins
    $all_users_sql = "SELECT admin_id, username, email, full_name, admin_image, created_at FROM admins WHERE $where_clause $order_clause";
    $count_users_result = $conn->query("SELECT COUNT(*) as total FROM admins WHERE $where_clause");
    $count_row = $count_users_result->fetch_assoc();
    $total_records = $count_row['total'];
    $total_pages = ceil($total_records / $per_page);
    
    $all_users_sql .= " LIMIT $per_page OFFSET $offset";
    $all_users_result = $conn->query($all_users_sql);
    if ($all_users_result) {
        while ($row = $all_users_result->fetch_assoc()) {
            $all_users[] = $row;
        }
    }
    
    // Get admin stats
    $counts = ['total' => $total_records, 'approved_count' => 0, 'pending_count' => 0];
} else {
    // Client users query (existing logic)
    if ($filter === 'approved') {
        $where_clause = "approved = 1";
    } elseif ($filter === 'pending') {
        $where_clause = "approved = 0";
    }

    if (!empty($search)) {
        $search_term = $conn->real_escape_string($search);
        $where_clause .= " AND (user_name LIKE '%$search_term%' OR email LIKE '%$search_term%')";
    }

    // Determine sort order
    $order_clause = "ORDER BY oauth_id DESC";
    switch ($sort) {
        case 'name_asc':
            $order_clause = "ORDER BY user_name ASC";
            break;
        case 'name_desc':
            $order_clause = "ORDER BY user_name DESC";
            break;
        case 'email_asc':
            $order_clause = "ORDER BY email ASC";
            break;
        case 'email_desc':
            $order_clause = "ORDER BY email DESC";
            break;
        case 'status_approved':
            $order_clause = "ORDER BY approved DESC, oauth_id DESC";
            break;
        case 'status_pending':
            $order_clause = "ORDER BY approved ASC, oauth_id DESC";
            break;
        case 'user_id_asc':
            $order_clause = "ORDER BY oauth_id ASC";
            break;
    }

    // Fetch all users
    $all_users_sql = "SELECT oauth_id, user_name, email, department, approved, avatar, registration_date FROM GOOGLE_OAUTH_USERS WHERE $where_clause $order_clause";
    $count_users_result = $conn->query("SELECT COUNT(*) as total FROM GOOGLE_OAUTH_USERS WHERE $where_clause");
    $count_row = $count_users_result->fetch_assoc();
    $total_records = $count_row['total'];
    $total_pages = ceil($total_records / $per_page);

    // Add pagination to the query
    $all_users_sql .= " LIMIT $per_page OFFSET $offset";
    $all_users_result = $conn->query($all_users_sql);
    if ($all_users_result) {
        while ($row = $all_users_result->fetch_assoc()) {
            $all_users[] = $row;
        }
    }

    // Get counts for filter badges
    $count_sql = "SELECT 
        (SELECT COUNT(*) FROM GOOGLE_OAUTH_USERS) as total,
        (SELECT COUNT(*) FROM GOOGLE_OAUTH_USERS WHERE approved = 1) as approved_count,
        (SELECT COUNT(*) FROM GOOGLE_OAUTH_USERS WHERE approved = 0) as pending_count";
    $count_result = $conn->query($count_sql);
    $counts = $count_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Genie | Users Management</title>
    <link rel="icon" type="image/jpeg" href="assets/img/intellismart.jpg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .user-type-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #E5E7EB;
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            color: #6B7280;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }

        .tab-button.active {
            color: #3B82F6;
            border-bottom-color: #3B82F6;
        }

        .tab-button:hover {
            color: #3B82F6;
        }

        .admin-form-container {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .admin-form-container h3 {
            margin-top: 0;
            color: #1F2937;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #D1D5DB;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .admin-form-container button {
            background: #3B82F6;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }

        .admin-form-container button:hover {
            background: #2563EB;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .alert.success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .alert.error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        .admin-actions {
            display: flex;
            gap: 0.5rem;
        }

        .admin-actions button {
            padding: 0.5rem;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            background: white;
            color: #6B7280;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
        }

        .admin-actions .edit-btn:hover {
            background: #F3F4F6;
            color: #3B82F6;
            border-color: #3B82F6;
        }

        .admin-actions .delete-btn:hover {
            background: #FEE2E2;
            color: #EF4444;
            border-color: #EF4444;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }

        .modal.active {
            display: block;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 2rem;
            border: 1px solid #888;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .modal-close {
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }

        .modal-close:hover {
            color: #000;
        }

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

        /* Modal Overlay Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-overlay .modal-content {
            background: #FFFFFF;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
            position: relative;
            padding: 2rem;
            onclick: event.stopPropagation();
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #E5E7EB;
        }

        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1F2937;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            color: #6B7280;
            transition: color 0.2s;
            font-size: 1.5rem;
            line-height: 1;
        }

        .modal-close:hover {
            color: #111827;
        }

        /* Add Admin Button */
        .add-admin-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(90deg, #3B82F6 0%, #F97316 100%);
            color: #FFFFFF;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            white-space: nowrap;
            cursor: pointer;
            border: none;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
            margin-left: auto;
        }

        .add-admin-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.35);
        }

        .add-admin-btn i {
            display: flex;
            align-items: center;
        }

        /* Toast Notification Styles */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            max-width: 400px;
            animation: slideInRight 0.3s ease;
        }

        .toast-notification.show {
            opacity: 1;
        }

        .toast-notification.success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .toast-notification.error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="content">
            <div class="content-header">
                <h1>Users Management</h1>
                <p>Manage Users and Admins effectively</p>
            </div>

            <!-- User Type Tabs -->
            <div class="user-type-tabs">
                <button class="tab-button <?= $user_type === 'client' ? 'active' : '' ?>" onclick="window.location.href='users.php?user_type=client'">
                    <i class="bi bi-people"></i> Users (Client)
                </button>
                <button class="tab-button <?= $user_type === 'admin' ? 'active' : '' ?>" onclick="window.location.href='users.php?user_type=admin'">
                    <i class="bi bi-shield-lock"></i> Admin
                </button>
            </div>

            <!-- Display message if exists -->
            <?php if (isset($_SESSION['admin_message'])): ?>
                <div class="alert <?= $_SESSION['admin_message_type'] ?>">
                    <?= $_SESSION['admin_message'] ?>
                </div>
                <?php unset($_SESSION['admin_message']); unset($_SESSION['admin_message_type']); ?>
            <?php endif; ?>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search <?= $user_type === 'admin' ? 'admins' : 'users' ?>..." value="<?= htmlspecialchars($search) ?>">
                    <div id="searchSuggestions" class="search-suggestions"></div>
                </div>

                <?php if ($user_type === 'client'): ?>
                <div class="filter-selects">
                    <div class="filter-group">
                        <label for="filterCategory">Filter:</label>
                        <form method="GET" id="filterForm" style="display: contents;">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                            <input type="hidden" name="per_page" value="<?= htmlspecialchars($per_page) ?>">
                            <input type="hidden" name="user_type" value="<?= htmlspecialchars($user_type) ?>">
                            <select id="filterCategory" name="filter" onchange="document.getElementById('filterForm').submit()">
                                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Users (<?= $counts['total'] ?>)</option>
                                <option value="approved" <?= $filter === 'approved' ? 'selected' : '' ?>>Approved (<?= $counts['approved_count'] ?>)</option>
                                <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending (<?= $counts['pending_count'] ?>)</option>
                            </select>
                        </form>
                    </div>
                <?php endif; ?>

                    <div class="filter-group">
                        <label for="sortBySelect">Sort by:</label>
                        <form method="GET" id="sortForm" style="display: contents;">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <input type="hidden" name="per_page" value="<?= htmlspecialchars($per_page) ?>">
                            <input type="hidden" name="user_type" value="<?= htmlspecialchars($user_type) ?>">
                            <select id="sortBySelect" name="sort" onchange="document.getElementById('sortForm').submit()">
                                <option value="user_id_desc" <?= $sort === 'user_id_desc' ? 'selected' : '' ?>>Newest</option>
                                <option value="user_id_asc" <?= $sort === 'user_id_asc' ? 'selected' : '' ?>>Oldest</option>
                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                                <option value="email_asc" <?= $sort === 'email_asc' ? 'selected' : '' ?>>Email (A-Z)</option>
                                <option value="email_desc" <?= $sort === 'email_desc' ? 'selected' : '' ?>>Email (Z-A)</option>
                            </select>
                        </form>
                    </div>
                <?php if ($user_type === 'client'): ?>
                </div>
                <?php endif; ?>

                <?php if ($user_type === 'admin'): ?>
                <button type="button" class="add-admin-btn" onclick="openAddAdminForm()">
                    <i class="bi bi-plus-lg"></i>
                    <span>New Admin</span>
                </button>
                <?php endif; ?>
            </div>

            <!-- Users Table -->
            <div class="table-container">
                <?php if (!empty($all_users)): ?>
                    <table>
                        <thead>
                            <tr>
                                <?php if ($user_type === 'client'): ?>
                                    <th>Profile Picture</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Registered Date</th>
                                <?php else: ?>
                                    <th>Profile Picture</th>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_users as $user): ?>
                                <tr<?php if ($user_type === 'admin') echo ' data-admin-id="' . htmlspecialchars($user['admin_id']) . '"'; ?>>
                                    <?php if ($user_type === 'client'): ?>
                                        <td>
                                            <img src="<?= htmlspecialchars($user['avatar'] ?? 'profile.png') ?>" alt="<?= htmlspecialchars($user['user_name']) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #D1D5DB;">
                                        </td>
                                        <td><?= htmlspecialchars($user['user_name']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= htmlspecialchars($user['department'] ?? '-') ?></td>
                                        <td>
                                            <?php
                                                $date = new DateTime($user['registration_date']);
                                                echo $date->format('M d, Y h:i A');
                                            ?>
                                        </td>
                                    <?php else: ?>
                                        <td>
                                            <img src="<?= htmlspecialchars($user['admin_image'] ?? 'profile.png') ?>" alt="<?= htmlspecialchars($user['full_name']) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #D1D5DB;">
                                        </td>
                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <?php
                                                $date = new DateTime($user['created_at']);
                                                echo $date->format('M d, Y h:i A');
                                            ?>
                                        </td>
                                        <td>
                                            <div class="admin-actions">
                                                <button type="button" class="edit-btn" onclick="openEditModal(<?= htmlspecialchars(json_encode(['admin_id' => $user['admin_id'], 'username' => $user['username'], 'email' => $user['email'], 'full_name' => $user['full_name'], 'admin_image' => $user['admin_image'] ?? 'profile.png'])) ?>)" title="Edit">
                                                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                                        <path d="M11.3333 2.00001C11.5084 1.82491 11.7163 1.68602 11.9447 1.59129C12.1731 1.49657 12.4173 1.44775 12.6667 1.44775C12.916 1.44775 13.1602 1.49657 13.3886 1.59129C13.617 1.68602 13.8249 1.82491 14 2.00001C14.1751 2.17511 14.314 2.38305 14.4087 2.61143C14.5034 2.83981 14.5522 3.08405 14.5522 3.33334C14.5522 3.58263 14.5034 3.82687 14.4087 4.05525C14.314 4.28363 14.1751 4.49157 14 4.66667L5.00001 13.6667L1.33334 14.6667L2.33334 11L11.3333 2.00001Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                                </button>
                                                <button type="button" class="delete-btn" onclick="deleteAdmin(<?= $user['admin_id'] ?>)" title="Delete">
                                                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                                        <path d="M4 6H16M15 6V16C15 17.1046 14.1046 18 13 18H7C5.89543 18 5 17.1046 5 16V6M15 6V5C15 3.89543 14.1046 3 13 3H7C5.89543 3 5 3.89543 5 5V6M8 9V14M12 9V14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                                </button>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

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
                                <a href="?page=<?php echo max(1, $page - 1); ?>&per_page=<?php echo $per_page; ?>&search=<?= htmlspecialchars($search) ?>&filter=<?= htmlspecialchars($filter) ?>&sort=<?= htmlspecialchars($sort) ?>&user_type=<?= htmlspecialchars($user_type) ?>" 
                                   class="pagination-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                    &lt;
                                </a>
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<a href="?page=1&per_page=' . $per_page . '&search=' . htmlspecialchars($search) . '&filter=' . htmlspecialchars($filter) . '&sort=' . htmlspecialchars($sort) . '&user_type=' . htmlspecialchars($user_type) . '" class="pagination-btn">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span class="pagination-btn disabled">...</span>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    $active = ($i == $page) ? 'active' : '';
                                    echo '<a href="?page=' . $i . '&per_page=' . $per_page . '&search=' . htmlspecialchars($search) . '&filter=' . htmlspecialchars($filter) . '&sort=' . htmlspecialchars($sort) . '&user_type=' . htmlspecialchars($user_type) . '" class="pagination-btn ' . $active . '">' . $i . '</a>';
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="pagination-btn disabled">...</span>';
                                    }
                                    echo '<a href="?page=' . $total_pages . '&per_page=' . $per_page . '&search=' . htmlspecialchars($search) . '&filter=' . htmlspecialchars($filter) . '&sort=' . htmlspecialchars($sort) . '&user_type=' . htmlspecialchars($user_type) . '" class="pagination-btn">' . $total_pages . '</a>';
                                }
                                ?>
                                <a href="?page=<?php echo min($total_pages, $page + 1); ?>&per_page=<?php echo $per_page; ?>&search=<?= htmlspecialchars($search) ?>&filter=<?= htmlspecialchars($filter) ?>&sort=<?= htmlspecialchars($sort) ?>&user_type=<?= htmlspecialchars($user_type) ?>" 
                                   class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    &gt;
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem 1.5rem; color: #9CA3AF;">
                            <i class="bi bi-inbox" style="font-size: 3rem; display: block; margin-bottom: 1rem; color: #D1D5DB;"></i>
                            <p style="font-size: 1.125rem; margin-bottom: 0.5rem;">No <?= $user_type === 'admin' ? 'admins' : 'users' ?> found</p>
                            <p style="font-size: 0.875rem;">Try adjusting your search or filter criteria</p>
                        </div>
                    <?php endif; ?>
            </div>

            <!-- Edit Admin Modal -->
            <div id="editAdminModal" class="modal-overlay" onclick="closeEditModal(event)">
                <div class="modal-content" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <h3>Edit Admin</h3>
                        <span class="modal-close" onclick="closeEditModal()">&times;</span>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_admin">
                        <input type="hidden" name="update_admin_id" id="update_admin_id" value="">
                        <div class="form-group">
                            <label for="update_admin_username">Username</label>
                            <input type="text" id="update_admin_username" name="update_admin_username" required>
                        </div>
                        <div class="form-group">
                            <label for="update_admin_email">Email</label>
                            <input type="email" id="update_admin_email" name="update_admin_email" required>
                        </div>
                        <div class="form-group">
                            <label for="update_admin_full_name">Full Name</label>
                            <input type="text" id="update_admin_full_name" name="update_admin_full_name" required>
                        </div>
                        <div class="form-group">
                            <label for="update_admin_image">Profile Picture</label>
                            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                <div style="flex: 1;">
                                    <input type="file" id="update_admin_image" name="update_admin_image" accept="image/*" onchange="previewEditAdminImage(this)">
                                    <small style="color: #6B7280; display: block; margin-top: 0.5rem;">Supported: JPG, PNG, GIF, WebP (Max 5MB)</small>
                                </div>
                                <img id="update_admin_image_preview" src="profile.png" alt="Preview" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover; border: 1px solid #E5E7EB;">
                            </div>
                        </div>
                        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                            <button type="button" onclick="closeEditModal()" style="padding: 0.75rem 1.5rem; background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                                Cancel
                            </button>
                            <button type="submit" style="padding: 0.75rem 1.5rem;background: linear-gradient(90deg, #3B82F6 0%, #F97316 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                                Update Admin
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Admin Modal -->
            <div id="addAdminModal" class="modal-overlay" onclick="closeAddAdminForm(event)">
                <div class="modal-content" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <h3>Add New Admin</h3>
                        <span class="modal-close" onclick="closeAddAdminForm()">&times;</span>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create_admin">
                        <div class="form-group">
                            <label for="admin_username">Username *</label>
                            <input type="text" id="admin_username" name="admin_username" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_email">Email *</label>
                            <input type="email" id="admin_email" name="admin_email" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_password">Password *</label>
                            <input type="password" id="admin_password" name="admin_password" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_full_name">Full Name *</label>
                            <input type="text" id="admin_full_name" name="admin_full_name" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_image">Profile Picture</label>
                            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                <div style="flex: 1;">
                                    <input type="file" id="admin_image" name="admin_image" accept="image/*" onchange="previewAdminImage(this)">
                                    <small style="color: #6B7280; display: block; margin-top: 0.5rem;">Supported: JPG, PNG, GIF, WebP (Max 5MB)</small>
                                </div>
                                <img id="admin_image_preview" src="profile.png" alt="Preview" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover; border: 1px solid #E5E7EB;">
                            </div>
                        </div>
                        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                            <button type="button" onclick="closeAddAdminForm()" style="padding: 0.75rem 1.5rem; background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                                Cancel
                            </button>
                            <button type="submit" style="padding: 0.75rem 1.5rem; background: #3B82F6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                                Create Admin
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Admin Confirmation Modal -->
            <div id="deleteAdminConfirmationOverlay" class="modal-overlay" style="display: none;">
                <div class="modal-content" onclick="event.stopPropagation()" style="max-width: 400px;">
                    <div style="margin-bottom: 1.5rem;">
                        <div style="width: 60px; height: 60px; background: #FEE2E2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path>
                            </svg>
                        </div>
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin: 0 0 0.5rem 0;">Delete Admin?</h3>
                        <p style="font-size: 0.875rem; color: #6B7280; margin: 0;">Are you sure you want to delete this admin? This action cannot be undone.</p>
                    </div>
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button id="deleteAdminCancel" type="button" style="padding: 0.75rem 1.5rem; background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                            Cancel
                        </button>
                        <button id="deleteAdminConfirm" type="button" style="padding: 0.75rem 1.5rem; background: #DC2626; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <!-- Delete Admin Form (hidden) -->
            <form id="deleteAdminForm" method="POST" action="" style="display: none;">
                <input type="hidden" name="action" value="delete_admin">
                <input type="hidden" name="delete_admin_id" id="delete_admin_id" value="">
            </form>
        </main>
    </div>
    
    <script>
        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');
        const userTable = document.querySelector('table tbody');

        // Image preview functions
        function previewAdminImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('admin_image_preview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewEditAdminImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('update_admin_image_preview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Edit modal functions
        function openEditModal(adminData) {
            document.getElementById('update_admin_id').value = adminData.admin_id;
            document.getElementById('update_admin_username').value = adminData.username;
            document.getElementById('update_admin_email').value = adminData.email;
            document.getElementById('update_admin_full_name').value = adminData.full_name;
            document.getElementById('update_admin_image').value = '';
            
            // Set preview image with proper error handling
            const previewImg = document.getElementById('update_admin_image_preview');
            const imageSrc = adminData.admin_image || 'profile.png';
            previewImg.src = imageSrc;
            previewImg.onerror = function() {
                this.src = 'profile.png';
            };
            
            document.getElementById('editAdminModal').classList.add('active');
        }

        function closeEditModal(event) {
            if (event && event.target.id !== 'editAdminModal') return;
            document.getElementById('editAdminModal').classList.remove('active');
        }

        // Add admin modal functions
        function openAddAdminForm() {
            document.getElementById('admin_username').value = '';
            document.getElementById('admin_email').value = '';
            document.getElementById('admin_password').value = '';
            document.getElementById('admin_full_name').value = '';
            document.getElementById('admin_image').value = '';
            document.getElementById('admin_image_preview').src = 'profile.png';
            document.getElementById('addAdminModal').classList.add('active');
        }

        function closeAddAdminForm(event) {
            if (event && event.target.id !== 'addAdminModal') return;
            document.getElementById('addAdminModal').classList.remove('active');
        }

        // Toast notification function
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 10);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Delete admin function with AJAX
        function deleteAdmin(adminId) {
            document.getElementById('delete_admin_id').value = adminId;
            document.getElementById('deleteAdminConfirmationOverlay').style.display = 'flex';
        }

        // Delete confirmation handlers with AJAX
        document.getElementById('deleteAdminCancel')?.addEventListener('click', function() {
            document.getElementById('deleteAdminConfirmationOverlay').style.display = 'none';
        });

        document.getElementById('deleteAdminConfirm')?.addEventListener('click', async function() {
            const adminId = document.getElementById('delete_admin_id').value;
            
            const formData = new FormData();
            formData.append('action', 'delete_admin');
            formData.append('delete_admin_id', adminId);
            
            try {
                const response = await fetch('users.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Remove row from table
                    const row = document.querySelector(`tr[data-admin-id="${adminId}"]`);
                    if (row) {
                        row.style.animation = 'slideInRight 0.3s ease reverse';
                        setTimeout(() => row.remove(), 300);
                    }
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
                
                document.getElementById('deleteAdminConfirmationOverlay').style.display = 'none';
            } catch (error) {
                showToast('Error deleting admin', 'error');
                console.error('Error:', error);
            }
        });

        // Edit admin form submission with AJAX
        const editAdminForm = document.querySelector('#editAdminModal form');
        if (editAdminForm) {
            editAdminForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const adminId = document.getElementById('update_admin_id').value;
                const email = document.getElementById('update_admin_email').value;
                const fullName = document.getElementById('update_admin_full_name').value;
                const imageFile = document.getElementById('update_admin_image').files[0];
                
                const formData = new FormData();
                formData.append('action', 'update_admin');
                formData.append('update_admin_id', adminId);
                formData.append('update_admin_email', email);
                formData.append('update_admin_full_name', fullName);
                if (imageFile) {
                    formData.append('update_admin_image', imageFile);
                }
                
                try {
                    const response = await fetch('users.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Update row in table
                        const row = document.querySelector(`tr[data-admin-id="${adminId}"]`);
                        if (row) {
                            const cells = row.querySelectorAll('td');
                            if (cells.length >= 4) {
                                cells[2].textContent = email;
                                cells[3].textContent = fullName;
                            }
                        }
                        closeEditModal();
                        showToast(data.message, 'success');
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (error) {
                    showToast('Error updating admin', 'error');
                    console.error('Error:', error);
                }
            });
        }

        // Close confirmation modal when clicking outside
        document.getElementById('deleteAdminConfirmationOverlay')?.addEventListener('click', function(event) {
            if (event.target === this) {
                this.style.display = 'none';
            }
        });

        // Live search and filter functionality
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const suggestions = [];
            const suggestionSet = new Set();

            if (userTable) {
                const rows = userTable.querySelectorAll('tr');
                
                // Filter table rows and collect suggestions
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length < 2) return;
                    
                    // Get search columns based on user type
                    let searchFields = [];
                    if ('<?= $user_type ?>' === 'admin') {
                        // For admin: username (index 1), email (index 2), full_name (index 3)
                        searchFields = [
                            cells[1]?.textContent.trim() || '',
                            cells[2]?.textContent.trim() || '',
                            cells[3]?.textContent.trim() || ''
                        ];
                    } else {
                        // For client: name (index 1), email (index 2), department (index 3)
                        searchFields = [
                            cells[1]?.textContent.trim() || '',
                            cells[2]?.textContent.trim() || '',
                            cells[3]?.textContent.trim() || ''
                        ];
                    }
                    
                    const lowerFields = searchFields.map(f => f.toLowerCase());
                    let matches = false;
                    
                    if (query.length > 0) {
                        matches = lowerFields.some(field => field.includes(query));
                        
                        if (matches) {
                            row.style.display = '';
                            searchFields.forEach((field, idx) => {
                                if (field && lowerFields[idx].includes(query) && !suggestionSet.has(field)) {
                                    suggestionSet.add(field);
                                    suggestions.push(field);
                                }
                            });
                        } else {
                            row.style.display = 'none';
                        }
                    } else {
                        row.style.display = '';
                    }
                });

                // Show suggestions dropdown
                if (query.length > 0) {
                    if (suggestions.length > 0) {
                        searchSuggestions.innerHTML = suggestions.map(suggestion => {
                            const lowerSuggestion = suggestion.toLowerCase();
                            const queryIndex = lowerSuggestion.indexOf(query);
                            let highlightedSuggestion = suggestion;
                            
                            if (queryIndex !== -1) {
                                const before = suggestion.substring(0, queryIndex);
                                const match = suggestion.substring(queryIndex, queryIndex + query.length);
                                const after = suggestion.substring(queryIndex + query.length);
                                highlightedSuggestion = `${before}<span style="background-color: #FFFFFF; font-weight: 600; padding: 2px 4px; border-radius: 3px;">${match}</span>${after}`;
                            }
                            
                            return `<div class="suggestion-item" style="background-color: #FFFFFF;" onclick="fillSearchSuggestion('${suggestion.replace(/'/g, "\\'")}')">${highlightedSuggestion}</div>`;
                        }).join('');
                        searchSuggestions.classList.add('active');
                    } else {
                        searchSuggestions.innerHTML = '<div class="suggestion-item no-results">No results found</div>';
                        searchSuggestions.classList.add('active');
                    }
                } else {
                    searchSuggestions.classList.remove('active');
                }
            }
        });

        // Click on suggestion to fill search
        function fillSearchSuggestion(value) {
            searchInput.value = value;
            // Trigger input event to update table
            const event = new Event('input', { bubbles: true });
            searchInput.dispatchEvent(event);
            searchSuggestions.classList.remove('active');
        }

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== searchInput && e.target.parentElement !== searchSuggestions) {
                searchSuggestions.classList.remove('active');
            }
        });

        // Change per page function
        function changePerPage(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', '1'); // Reset to first page
            window.location.href = '?' + urlParams.toString();
        }
    </script>
</body>
</html>

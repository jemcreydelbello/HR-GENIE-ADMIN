<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
include 'db.php';

$message = '';
$message_type = '';

// Upload directory path
$upload_dir = 'uploads/admin_images/';

// Fetch current user settings from database
$admin_id = $_SESSION['admin_id'];
$user_result = $conn->query("SELECT * FROM admins WHERE admin_id = $admin_id");
$current_user = $user_result ? $user_result->fetch_assoc() : null;

// Fallback to session variables if database is empty
if (!$current_user) {
    $current_user = [
        'admin_id' => $admin_id,
        'username' => $_SESSION['admin_name'] ?? 'Admin User',
        'email' => $_SESSION['admin_email'] ?? 'admin@company.com',
        'department' => $_SESSION['admin_department'] ?? 'Human Resources',
        'profile_picture' => $_SESSION['admin_profile_picture'] ?? 'profile.png'
    ];
}

// Determine profile image src (use admin_image if available, fall back to profile_picture)
$profile_img_src = $current_user['admin_image'] ?? $current_user['profile_picture'] ?? 'profile.png';
if (!file_exists($profile_img_src)) {
    $profile_img_src = 'profile.png';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action == 'update_profile_picture') {
        // Handle profile picture upload only
        $profile_picture = $current_user['profile_picture'] ?? 'profile-placeholder.png';
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['profile_picture']['tmp_name'];
            $file_name = $_FILES['profile_picture']['name'];
            $file_size = $_FILES['profile_picture']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate file (allow only images, max 2MB)
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($file_ext, $allowed_exts) && $file_size <= 2097152) { // 2MB
                $new_file_name = 'profile_' . $admin_id . '_' . time() . '.' . $file_ext;
                $file_path = $upload_dir . $new_file_name;
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $profile_picture = $file_path;
                    $update_sql = "UPDATE admins SET admin_image = '$profile_picture' WHERE admin_id = $admin_id";
                    
                    if ($conn->query($update_sql)) {
                        header('Location: settings.php?status=success&message=' . urlencode('Profile picture updated successfully!'));
                        exit();
                    } else {
                        header('Location: settings.php?status=error&message=' . urlencode('Error updating profile picture: ' . $conn->error));
                        exit();
                    }
                } else {
                    header('Location: settings.php?status=error&message=' . urlencode('Error uploading profile picture.'));
                    exit();
                }
            } else {
                header('Location: settings.php?status=error&message=' . urlencode('Invalid file type or size. Only JPG, PNG, GIF allowed (max 2MB).'));
                exit();
            }
        } else {
            header('Location: settings.php?status=error&message=' . urlencode('No file uploaded or upload error.'));
            exit();
        }
    } elseif ($action == 'update_profile') {
        $username = isset($_POST['username']) ? $conn->real_escape_string($_POST['username']) : '';
        $full_name = isset($_POST['full_name']) ? $conn->real_escape_string($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? $conn->real_escape_string($_POST['email']) : '';
        
        // Handle profile picture upload
        $profile_picture = $current_user['admin_image'] ?? 'profile.png';
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['profile_picture']['tmp_name'];
            $file_name = $_FILES['profile_picture']['name'];
            $file_size = $_FILES['profile_picture']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate file (allow only images, max 2MB)
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($file_ext, $allowed_exts) && $file_size <= 2097152) { // 2MB
                $new_file_name = 'profile_' . $admin_id . '_' . time() . '.' . $file_ext;
                $file_path = $upload_dir . $new_file_name;
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $profile_picture = $file_path;
                } else {
                    header('Location: settings.php?status=error&message=' . urlencode('Error uploading profile picture.'));
                    exit();
                }
            } else {
                header('Location: settings.php?status=error&message=' . urlencode('Invalid file type or size. Only JPG, PNG, GIF allowed (max 2MB).'));
                exit();
            }
        }
        
        if (!empty($username) && !empty($full_name) && !empty($email)) {
            $update_sql = "UPDATE admins SET username = '$username', full_name = '$full_name', email = '$email', admin_image = '$profile_picture' WHERE admin_id = $admin_id";
            
            if ($conn->query($update_sql)) {
                $_SESSION['admin_name'] = $full_name;
                $_SESSION['admin_email'] = $email;
                $_SESSION['admin_profile_picture'] = $profile_picture;
                header('Location: settings.php?status=success&message=' . urlencode('Profile updated successfully!'));
                exit();
            } else {
                header('Location: settings.php?status=error&message=' . urlencode('Error updating profile: ' . $conn->error));
                exit();
            }
        } else {
            header('Location: settings.php?status=error&message=' . urlencode('Please fill in all required fields.'));
            exit();
        }
    } elseif ($action == 'change_password') {
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
            if ($new_password === $confirm_password) {
                // Verify current password - handle both hashed and plaintext passwords
                $password_match = false;
                $db_password = isset($current_user['password_hash']) ? trim($current_user['password_hash']) : '';
                $input_password = trim($current_password);
                
                if (!empty($db_password)) {
                    // Try password_verify first (for hashed passwords)
                    if (password_verify($input_password, $db_password)) {
                        $password_match = true;
                    }
                    // Fallback to plaintext comparison (for plaintext passwords in database)
                    elseif ($input_password === $db_password) {
                        $password_match = true;
                    }
                    // Case-insensitive plaintext comparison
                    elseif (strtolower($input_password) === strtolower($db_password)) {
                        $password_match = true;
                    }
                }
                
                if ($password_match) {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $password_sql = "UPDATE admins SET password_hash = '$hashed_password' WHERE admin_id = $admin_id";
                    
                    if ($conn->query($password_sql)) {
                        header('Location: settings.php?status=success&message=' . urlencode('Password changed successfully!'));
                        exit();
                    } else {
                        header('Location: settings.php?status=error&message=' . urlencode('Error changing password: ' . $conn->error));
                        exit();
                    }
                } else {
                    header('Location: settings.php?status=error&message=' . urlencode('Current password is incorrect.'));
                    exit();
                }
            } else {
                header('Location: settings.php?status=error&message=' . urlencode('New passwords do not match.'));
                exit();
            }
        } else {
            header('Location: settings.php?status=error&message=' . urlencode('Please fill in all password fields.'));
            exit();
        }
    }
}

// Fetch system statistics
$total_articles = $conn->query("SELECT COUNT(*) as count FROM ARTICLES")->fetch_assoc()['count'];
$total_categories = $conn->query("SELECT COUNT(*) as count FROM CATEGORIES")->fetch_assoc()['count'];
$total_tickets = $conn->query("SELECT COUNT(*) as count FROM TICKETS")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM admins")->fetch_assoc()['count'];
$total_logs = $conn->query("SELECT COUNT(*) as count FROM ACTIVITY_LOGS")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Genie | Settings</title>
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

        /* Layout */
        .settings-layout {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 2rem;
            align-items: flex-start;
        }

        /* Left profile card */
        .settings-profile {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            border: 1px solid #E5E7EB;
        }

        .settings-profile-header {
            background: url('admin/assets/img/article-bg.png') center/cover;
            padding: 2.5rem 2rem 1.25rem;
            text-align: center;
            position: relative;
        }

        .settings-profile-avatar {
            width: 96px;
            height: 96px;
            border-radius: 999px;
            overflow: hidden;
            border: 4px solid #FFFFFF;
            margin: 0 auto 1rem;
            background: #EFF6FF;
            position: relative;
            cursor: pointer;
        }

        .settings-profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: opacity 0.3s;
        }

        .settings-profile-avatar:hover img {
            opacity: 0.7;
        }

        .settings-profile-avatar .camera-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }

        .settings-profile-avatar:hover .camera-icon {
            opacity: 1;
        }

        .settings-profile-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #292727;
            margin-bottom: 0.25rem;
        }

        .settings-profile-role {
            font-size: 0.875rem;
            color: rgba(37, 38, 40, 0.9);
        }

        .settings-profile-body {
            padding: 1.75rem 2rem 2rem;
            background: #FFFFFF;
        }

        .settings-profile-metrics {
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .settings-profile-metric {
            display: flex;
            justify-content: space-between;
            padding: 0.85rem 1rem;
            font-size: 0.875rem;
            background: #FFFFFF;
        }

        .settings-profile-metric:nth-child(2n) {
            background: #F9FAFB;
        }

        .settings-profile-metric span:first-child {
            color: #6B7280;
        }

        .settings-profile-metric span:last-child {
            font-weight: 600;
            color: #111827;
        }

        .settings-profile-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.7rem 1rem;
            border-radius: 8px;
            border: 1px solid #D1D5DB;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            background: #FFFFFF;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
        }

        .btn-outline:hover {
            background: #F9FAFB;
            border-color: #9CA3AF;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12);
        }

        .profile-link {
            font-size: 0.75rem;
            color: #2563EB;
            background: #F1F5F9;
            border-radius: 8px;
            padding: 0.75rem 0.9rem;
            word-break: break-all;
        }

        /* Right content */
        .settings-main-card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            border: 1px solid #E5E7EB;
            display: flex;
            flex-direction: column;
            min-height: 470px;
        }

        .settings-main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 2rem 0.75rem;
            border-bottom: 1px solid #E5E7EB;
        }

        .settings-tabs {
            display: flex;
            gap: 1.75rem;
            font-size: 0.9rem;
        }

        .settings-tab {
            position: relative;
            padding-bottom: 0.8rem;
            cursor: pointer;
            color: #6B7280;
            font-weight: 500;
            white-space: nowrap;
        }

        .settings-tab.active {
            color: #111827;
        }

        .settings-tab.active::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 3px;
            border-radius: 999px;
            background: #2563EB;
        }

        .settings-tab-content {
            display: none;
        }

        .settings-tab-content.active {
            display: block;
        }

        /* Profile Picture Editor Modal */
        .profile-edit-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease;
        }

        .profile-edit-modal.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .profile-edit-modal-content {
            background: #FFFFFF;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
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

        .profile-edit-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .profile-edit-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }

        .profile-edit-close {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            color: #6B7280;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .profile-edit-close:hover {
            background: #F3F4F6;
        }

        .profile-edit-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            overflow-y: auto;
        }

        .profile-edit-preview-container {
            position: relative;
            width: 100%;
            height: 400px;
            background: #F9FAFB;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-edit-image-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            cursor: grab;
        }

        .profile-edit-image-wrapper:active {
            cursor: grabbing;
        }

        .profile-edit-image {
            position: absolute;
            max-width: none;
            user-select: none;
            pointer-events: none;
            will-change: transform;
        }

        .profile-edit-crop-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 300px;
            height: 300px;
            border: 3px solid #FFFFFF;
            border-radius: 50%;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
            pointer-events: none;
            z-index: 10;
        }

        .profile-edit-controls {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .profile-edit-zoom-control {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .profile-edit-zoom-control label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            min-width: 60px;
        }

        .profile-edit-zoom-slider {
            flex: 1;
            height: 6px;
            border-radius: 3px;
            background: #E5E7EB;
            outline: none;
            -webkit-appearance: none;
        }

        .profile-edit-zoom-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #2563EB;
            cursor: pointer;
        }

        .profile-edit-zoom-slider::-moz-range-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #2563EB;
            cursor: pointer;
            border: none;
        }

        .profile-edit-preview-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #E5E7EB;
            margin: 0 auto;
            background: #F9FAFB;
        }

        .profile-edit-preview-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-edit-actions {
            display: flex;
            gap: 0.75rem;
            padding-top: 1rem;
            border-top: 1px solid #E5E7EB;
        }

        .btn-profile-save {
            flex: 1;
            padding: 0.75rem 1.5rem;
            background: #2563EB;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-profile-save:hover {
            background: #1D4ED8;
        }

        .btn-profile-cancel {
            flex: 1;
            padding: 0.75rem 1.5rem;
            background: #F3F4F6;
            color: #374151;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-profile-cancel:hover {
            background: #E5E7EB;
        }

        .btn-change-cover {
            padding: 0.5rem 0.9rem;
            font-size: 0.8rem;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            background: rgba(15, 23, 42, 0.08);
            color: #111827;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
        }

        .settings-main-body {
            padding: 2rem;
            flex: 1;
        }

        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .alert-success {
            background-color: #D1FAE5;
            border: 1px solid #6EE7B7;
            color: #065F46;
        }

        .alert-error {
            background-color: #FEE2E2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }

        .settings-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.25rem 2rem;
            margin-bottom: 1.75rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .form-group label {
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #6B7280;
        }

        .form-control {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border-radius: 8px;
            border: 1px solid #D1D5DB;
            font-size: 0.9rem;
            color: #111827;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
            background: #FFFFFF;
        }

        .form-control::placeholder {
            color: #9CA3AF;
        }

        .form-control:focus {
            border-color: #2563EB;
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.2);
        }

        .settings-actions {
            margin-top: 3.5rem;
            display: flex;
            gap: 0.75rem;
        }

        .btn-primary {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(90deg, #3B82F6 0%, #F97316 100%);
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.35);
        }

        .btn-secondary {
            padding: 0.75rem 1.5rem;
            background: #F3F4F6;
            color: #374151;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: background 0.2s, box-shadow 0.2s;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary:hover {
            background: #F9FAFB;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Password section inside same card */
        .settings-subsection-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #111827;
            margin: 0 0 0.75rem;
        }

        .settings-password-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.25rem 2rem;
        }

        @media (max-width: 1024px) {
            .settings-layout {
                grid-template-columns: 1fr;
            }

            .settings-main-card {
                order: -1;
            }
        }

        @media (max-width: 768px) {
            .settings-form-grid,
            .settings-password-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        function switchTab(tab) {
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.settings-tab');
            tabs.forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            event.target.classList.add('active');
            
            // Hide all tab contents
            const contents = document.querySelectorAll('.settings-tab-content');
            contents.forEach(c => c.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tab + '-settings').classList.add('active');
        }

        // Profile Picture Editor Variables
        let profileEditor = {
            modal: null,
            image: null,
            imageWrapper: null,
            preview: null,
            zoom: 1,
            x: 0,
            y: 0,
            isDragging: false,
            startX: 0,
            startY: 0,
            cropSize: 300,
            currentFile: null
        };

        function openProfileEditor() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (file) {
                    profileEditor.currentFile = file;
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        initProfileEditor(e.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            };
            input.click();
        }

        function initProfileEditor(imageSrc) {
            profileEditor.modal = document.getElementById('profile-edit-modal');
            profileEditor.image = document.getElementById('profile-edit-image');
            profileEditor.imageWrapper = document.getElementById('profile-edit-image-wrapper');
            profileEditor.preview = document.getElementById('profile-edit-preview');
            
            // Reset zoom slider
            const zoomSlider = document.getElementById('profile-zoom-slider');
            if (zoomSlider) {
                zoomSlider.value = 1;
            }
            
            profileEditor.image.src = imageSrc;
            profileEditor.modal.classList.add('active');
            
            // Reset zoom and position
            profileEditor.zoom = 1;
            profileEditor.x = 0;
            profileEditor.y = 0;
            
            // Wait for image to load
            profileEditor.image.onload = function() {
                // Set initial image size
                const container = profileEditor.imageWrapper;
                const img = profileEditor.image;
                const containerWidth = container.offsetWidth;
                const containerHeight = container.offsetHeight;
                
                // Scale image to fit container initially
                const scaleX = containerWidth / img.naturalWidth;
                const scaleY = containerHeight / img.naturalHeight;
                const initialScale = Math.max(scaleX, scaleY) * 1.2; // Slightly larger to allow zoom out
                
                profileEditor.zoom = initialScale;
                if (zoomSlider) {
                    zoomSlider.min = (initialScale * 0.5).toFixed(1);
                    zoomSlider.max = (initialScale * 3).toFixed(1);
                    zoomSlider.value = initialScale.toFixed(1);
                }
                
                centerImage();
                updateImageTransform();
                updatePreview();
            };
        }

        function centerImage() {
            const container = profileEditor.imageWrapper;
            const img = profileEditor.image;
            const containerWidth = container.offsetWidth;
            const containerHeight = container.offsetHeight;
            
            // Calculate initial position to center image
            const imgWidth = img.naturalWidth * profileEditor.zoom;
            const imgHeight = img.naturalHeight * profileEditor.zoom;
            
            profileEditor.x = (containerWidth - imgWidth) / 2;
            profileEditor.y = (containerHeight - imgHeight) / 2;
            
            // Constrain position to prevent dragging too far
            constrainImagePosition();
        }

        function constrainImagePosition() {
            const container = profileEditor.imageWrapper;
            const img = profileEditor.image;
            const containerWidth = container.offsetWidth;
            const containerHeight = container.offsetHeight;
            const cropSize = profileEditor.cropSize;
            
            const imgWidth = img.naturalWidth * profileEditor.zoom;
            const imgHeight = img.naturalHeight * profileEditor.zoom;
            
            // Calculate bounds
            const minX = containerWidth - imgWidth - (containerWidth - cropSize) / 2;
            const maxX = (containerWidth - cropSize) / 2;
            const minY = containerHeight - imgHeight - (containerHeight - cropSize) / 2;
            const maxY = (containerHeight - cropSize) / 2;
            
            profileEditor.x = Math.max(minX, Math.min(maxX, profileEditor.x));
            profileEditor.y = Math.max(minY, Math.min(maxY, profileEditor.y));
        }

        function updateImageTransform() {
            if (profileEditor.image) {
                profileEditor.image.style.transform = `translate(${profileEditor.x}px, ${profileEditor.y}px) scale(${profileEditor.zoom})`;
                profileEditor.image.style.transformOrigin = '0 0';
            }
            // Update preview after transform
            setTimeout(updatePreview, 10);
        }

        function updatePreview() {
            if (!profileEditor.image || !profileEditor.preview) return;
            
            const canvas = document.createElement('canvas');
            canvas.width = 300;
            canvas.height = 300;
            const ctx = canvas.getContext('2d');
            
            const img = profileEditor.image;
            const container = profileEditor.imageWrapper;
            const cropSize = profileEditor.cropSize;
            
            // Calculate crop area
            const containerWidth = container.offsetWidth;
            const containerHeight = container.offsetHeight;
            const cropX = (containerWidth - cropSize) / 2;
            const cropY = (containerHeight - cropSize) / 2;
            
            // Calculate source coordinates
            const scale = profileEditor.zoom;
            const imgX = (cropX - profileEditor.x) / scale;
            const imgY = (cropY - profileEditor.y) / scale;
            const imgSize = cropSize / scale;
            
            // Draw cropped image
            ctx.save();
            ctx.beginPath();
            ctx.arc(150, 150, 150, 0, 2 * Math.PI);
            ctx.clip();
            ctx.drawImage(img, imgX, imgY, imgSize, imgSize, 0, 0, 300, 300);
            ctx.restore();
            
            profileEditor.preview.src = canvas.toDataURL('image/png');
        }

        // Zoom slider handler
        document.addEventListener('DOMContentLoaded', function() {
            const zoomSlider = document.getElementById('profile-zoom-slider');
            if (zoomSlider) {
                zoomSlider.addEventListener('input', function(e) {
                    profileEditor.zoom = parseFloat(e.target.value);
                    // Re-center image when zooming
                    const container = profileEditor.imageWrapper;
                    const img = profileEditor.image;
                    if (container && img && img.complete) {
                        const containerWidth = container.offsetWidth;
                        const containerHeight = container.offsetHeight;
                        const imgWidth = img.naturalWidth * profileEditor.zoom;
                        const imgHeight = img.naturalHeight * profileEditor.zoom;
                        
                        // Keep image centered during zoom
                        const cropSize = profileEditor.cropSize;
                        const centerX = containerWidth / 2;
                        const centerY = containerHeight / 2;
                        profileEditor.x = centerX - imgWidth / 2;
                        profileEditor.y = centerY - imgHeight / 2;
                        
                        constrainImagePosition();
                        updateImageTransform();
                    }
                });
            }

            // Mouse drag handlers
            const imageWrapper = document.getElementById('profile-edit-image-wrapper');
            if (imageWrapper) {
                imageWrapper.addEventListener('mousedown', function(e) {
                    if (e.target === profileEditor.image || e.target === imageWrapper) {
                        profileEditor.isDragging = true;
                        profileEditor.startX = e.clientX - profileEditor.x;
                        profileEditor.startY = e.clientY - profileEditor.y;
                        imageWrapper.style.cursor = 'grabbing';
                    }
                });

                document.addEventListener('mousemove', function(e) {
                    if (profileEditor.isDragging) {
                        profileEditor.x = e.clientX - profileEditor.startX;
                        profileEditor.y = e.clientY - profileEditor.startY;
                        constrainImagePosition();
                        updateImageTransform();
                    }
                });

                document.addEventListener('mouseup', function() {
                    profileEditor.isDragging = false;
                    if (imageWrapper) {
                        imageWrapper.style.cursor = 'move';
                    }
                });

                // Touch support for mobile
                imageWrapper.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    const touch = e.touches[0];
                    profileEditor.isDragging = true;
                    profileEditor.startX = touch.clientX - profileEditor.x;
                    profileEditor.startY = touch.clientY - profileEditor.y;
                });

                document.addEventListener('touchmove', function(e) {
                    if (profileEditor.isDragging) {
                        e.preventDefault();
                        const touch = e.touches[0];
                        profileEditor.x = touch.clientX - profileEditor.startX;
                        profileEditor.y = touch.clientY - profileEditor.startY;
                        constrainImagePosition();
                        updateImageTransform();
                    }
                });

                document.addEventListener('touchend', function() {
                    profileEditor.isDragging = false;
                });
            }
        });

        function closeProfileEditor() {
            if (profileEditor.modal) {
                profileEditor.modal.classList.remove('active');
            }
        }

        function saveProfilePicture() {
            if (!profileEditor.image || !profileEditor.imageWrapper) return;
            
            // Create canvas for cropping
            const canvas = document.createElement('canvas');
            canvas.width = 300;
            canvas.height = 300;
            const ctx = canvas.getContext('2d');
            
            const img = profileEditor.image;
            const container = profileEditor.imageWrapper;
            const cropSize = profileEditor.cropSize;
            
            // Calculate crop area
            const containerWidth = container.offsetWidth;
            const containerHeight = container.offsetHeight;
            const cropX = (containerWidth - cropSize) / 2;
            const cropY = (containerHeight - cropSize) / 2;
            
            // Calculate source coordinates
            const scale = profileEditor.zoom;
            const imgX = (cropX - profileEditor.x) / scale;
            const imgY = (cropY - profileEditor.y) / scale;
            const imgSize = cropSize / scale;
            
            // Draw cropped image
            ctx.save();
            ctx.beginPath();
            ctx.arc(150, 150, 150, 0, 2 * Math.PI);
            ctx.clip();
            ctx.drawImage(img, imgX, imgY, imgSize, imgSize, 0, 0, 300, 300);
            ctx.restore();
            
            // Convert canvas to blob
            canvas.toBlob(function(blob) {
                const formData = new FormData();
                formData.append('action', 'update_profile_picture');
                formData.append('profile_picture', blob, 'profile.jpg');
                
                fetch('settings.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    // Reload page to show updated profile picture
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving profile picture. Please try again.');
                });
            }, 'image/jpeg', 0.9);
        }

        // Close modal on outside click
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('profile-edit-modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeProfileEditor();
                    }
                });
            }

            // Show notification if status parameter exists
            const params = new URLSearchParams(window.location.search);
            const status = params.get('status');
            const message = params.get('message');

            if (status && message) {
                showNotification(decodeURIComponent(message), status);
                // Clean up URL
                window.history.replaceState({}, document.title, 'settings.php');
            }
        });

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
    </script>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>

        <main class="content">
            <div class="content-header">
                <h1>Account Settings</h1>
                <p>Manage your profile information, credentials, and preferences.</p>
            </div>

            <div class="settings-layout">
                <!-- Left profile card -->
                <aside class="settings-profile">
                    <div class="settings-profile-header">
                        <div class="settings-profile-avatar" onclick="openProfileEditor()">
                            <!-- Replace src with your chosen image path -->
                            <img id="profile-avatar-img" src="<?php echo htmlspecialchars($profile_img_src); ?>?t=<?php echo rand(); ?>" alt="Profile photo">
                            <div class="camera-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="13" r="3" fill="white"/>
                                    <path d="M20 4H16.83L15.59 2.65C15.22 2.24 14.68 2 14.12 2H9.88C9.32 2 8.78 2.24 8.41 2.65L7.17 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM12 17C9.24 17 7 14.76 7 12S9.24 7 12 7 17 9.24 17 12 14.76 17 12 17Z" fill="white"/>
                                </svg>
                            </div>
                        </div>
                        <div class="settings-profile-name">
                            <?php echo htmlspecialchars($current_user['full_name'] ?? 'Admin User'); ?>
                        </div>
                        <div class="settings-profile-role">
                            <?php echo htmlspecialchars($current_user['department'] ?? 'Human Resources'); ?>
                        </div>
                    </div>
                    <div class="settings-profile-body">
                        <div class="settings-profile-metrics">
                            <div class="settings-profile-metric">
                                <span>Articles</span>
                                <span><?php echo $total_articles; ?></span>
                            </div>
                            <div class="settings-profile-metric">
                                <span>Categories</span>
                                <span><?php echo $total_categories; ?></span>
                            </div>
                            <div class="settings-profile-metric">
                                <span>Tickets</span>
                                <span><?php echo $total_tickets; ?></span>
                            </div>
                
                        </div>

                       
                    </div>
                </aside>

                <!-- Right main content -->
                <section class="settings-main-card">
                    <div class="settings-main-header">
                        <div class="settings-tabs">
                            <div class="settings-tab active" onclick="switchTab('account')">Account Settings</div>
                            <div class="settings-tab" onclick="switchTab('security')">Security Settings</div>
                        </div>
                        
                    </div>

                    <div class="settings-main-body">
                        <!-- Account Settings Tab Content -->
                        <div id="account-settings" class="settings-tab-content active">
                            <!-- Account details form -->
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="file" id="profile_picture_input" name="profile_picture" accept="image/*" style="display: none;">

                                <div class="settings-form-grid">
                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <input
                                            type="text"
                                            id="username"
                                            name="username"
                                            class="form-control"
                                            placeholder="Enter your username"
                                            value="<?php echo htmlspecialchars($current_user['username'] ?? ''); ?>"
                                            required
                                        >
                                    </div>
                                    <div class="form-group">
                                        <label for="full_name">Full Name</label>
                                        <input
                                            type="text"
                                            id="full_name"
                                            name="full_name"
                                            class="form-control"
                                            placeholder="Enter your full name"
                                            value="<?php echo htmlspecialchars($current_user['full_name'] ?? ''); ?>"
                                            required
                                        >
                                    </div>
                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input
                                            type="email"
                                            id="email"
                                            name="email"
                                            class="form-control"
                                            placeholder="name@company.com"
                                            value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>"
                                            required
                                        >
                                    </div>
                                    <div class="form-group">
                                        <label for="department">Department</label>
                                        <input
                                            type="text"
                                            id="department"
                                            name="department"
                                            class="form-control"
                                            placeholder="e.g. Human Resources"
                                            value="<?php echo htmlspecialchars($current_user['department'] ?? ''); ?>"
                                            readonly
                                        >
                                    </div>
                                
                                </div>

                                <div class="settings-actions">
                                    <button type="submit" class="btn-primary">Update Profile</button>
                                    <button type="reset" class="btn-secondary">Reset</button>
                                </div>
                            </form>
                        </div>

                        <!-- Security Settings Tab Content -->
                        <div id="security-settings" class="settings-tab-content">
                            <!-- Change password section -->
                            <h3 class="settings-subsection-title">Change Password</h3>

                            <form method="POST" action="">
                                <input type="hidden" name="action" value="change_password">

                                <div class="form-group" style="max-width: 360px; margin-bottom: 1.25rem;">
                                    <label for="current_password">Current Password</label>
                                    <input
                                        type="password"
                                        id="current_password"
                                        name="current_password"
                                        class="form-control"
                                        placeholder="Enter current password"
                                        required
                                    >
                                </div>

                                <div class="settings-password-grid">
                                    <div class="form-group">
                                        <label for="new_password">New Password</label>
                                        <input
                                            type="password"
                                            id="new_password"
                                            name="new_password"
                                            class="form-control"
                                            placeholder="Enter new password"
                                            required
                                        >
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm Password</label>
                                        <input
                                            type="password"
                                            id="confirm_password"
                                            name="confirm_password"
                                            class="form-control"
                                            placeholder="Confirm new password"
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="settings-actions">
                                    <button type="submit" class="btn-primary">Update Password</button>
                                    <button type="reset" class="btn-secondary">Reset</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Profile Picture Editor Modal -->
    <div id="profile-edit-modal" class="profile-edit-modal">
        <div class="profile-edit-modal-content">
            <div class="profile-edit-header">
                <h3>Update Profile Picture</h3>
                <button class="profile-edit-close" onclick="closeProfileEditor()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <div class="profile-edit-body">
                <div class="profile-edit-preview-container">
                    <div id="profile-edit-image-wrapper" class="profile-edit-image-wrapper">
                        <img id="profile-edit-image" class="profile-edit-image" alt="Profile preview">
                        <div class="profile-edit-crop-overlay"></div>
                    </div>
                </div>
                <div class="profile-edit-controls">
                    <div class="profile-edit-zoom-control">
                        <label>Zoom:</label>
                        <input type="range" id="profile-zoom-slider" class="profile-edit-zoom-slider" min="0.5" max="3" step="0.1" value="1">
                    </div>
                    <div class="profile-edit-preview-circle">
                        <img id="profile-edit-preview" alt="Preview">
                    </div>
                </div>
                <div class="profile-edit-actions">
                    <button class="btn-profile-cancel" onclick="closeProfileEditor()">Cancel</button>
                    <button class="btn-profile-save" onclick="saveProfilePicture()">Save</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>

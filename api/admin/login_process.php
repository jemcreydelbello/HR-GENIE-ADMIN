<?php
session_start();
include 'db.php';
require_once 'verify_recaptcha.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify reCAPTCHA
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    if (!verifyRecaptcha($recaptcha_response)) {
        $error = 'reCAPTCHA verification failed. Please try again.';
        header('Location: login.php?error=' . urlencode($error));
        exit();
    }
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
        header('Location: login.php?error=' . urlencode($error));
        exit();
    }
    
    // Escape username to prevent SQL injection
    $username = $conn->real_escape_string($username);
    
    // Check admins table (unified admin system)
    $admin_sql = "SELECT admin_id, username, email, password_hash, full_name FROM admins 
                  WHERE username = '$username' OR email = '$username'";
    $admin_result = $conn->query($admin_sql);
    
    if ($admin_result && $admin_result->num_rows > 0) {
        $admin = $admin_result->fetch_assoc();
        
        // Verify password using password_verify for bcrypt hashed passwords
        if (password_verify($password, $admin['password_hash'])) {
            // Set session variables for admin accounts
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_name'] = $admin['username'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_full_name'] = $admin['full_name'];
            $_SESSION['admin_role_name'] = 'Administrator';
            
            // Remember me functionality
            if ($remember_me) {
                setcookie('remember_username', $admin['username'], time() + (86400 * 30), '/'); // 30 days
            } else {
                setcookie('remember_username', '', time() - 3600, '/'); // Delete cookie
            }
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Invalid username or password.';
    }
    
    // If login failed, redirect back with error
    header('Location: login.php?error=' . urlencode($error));
    exit();
} else {
    header('Location: login.php');
    exit();
}
?>
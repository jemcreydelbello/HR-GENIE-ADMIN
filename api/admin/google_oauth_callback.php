<?php
session_start();
require_once 'db.php';
require_once '../config/google_oauth_config.php';

if (!isset($_GET['code'])) {
    header('Location: login.php?error=' . urlencode('Google authentication failed.'));
    exit();
}

$code = $_GET['code'];
$state = $_GET['state'] ?? '';

// Verify state to prevent CSRF attacks
if (!empty($state) && (!isset($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state'])) {
    header('Location: login.php?error=' . urlencode('Invalid state parameter.'));
    exit();
}

// Exchange authorization code for access token
$token_data = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init(GOOGLE_TOKEN_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    header('Location: login.php?error=' . urlencode('Failed to get access token from Google.'));
    exit();
}

$token_response = json_decode($response, true);

if (!isset($token_response['access_token'])) {
    header('Location: login.php?error=' . urlencode('Invalid response from Google.'));
    exit();
}

$access_token = $token_response['access_token'];

// Get user info from Google
$ch = curl_init(GOOGLE_USERINFO_URL . '?access_token=' . urlencode($access_token));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);

$user_info_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    header('Location: login.php?error=' . urlencode('Failed to get user info from Google.'));
    exit();
}

$user_info = json_decode($user_info_response, true);

if (!isset($user_info['id']) || !isset($user_info['email'])) {
    header('Location: login.php?error=' . urlencode('Invalid user info from Google.'));
    exit();
}

$google_id = $user_info['id'];
$email = $user_info['email'];
$name = $user_info['name'] ?? $user_info['given_name'] ?? 'Google User';
$picture = $user_info['picture'] ?? null;

// Check if user exists by Google ID
$check_sql = "SELECT * FROM USERS WHERE google_id = ? OR email = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param('ss', $google_id, $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user) {
    // Update Google ID if not set
    if (empty($user['google_id'])) {
        $update_sql = "UPDATE USERS SET google_id = ? WHERE user_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('si', $google_id, $user['user_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    // Update name and profile picture if available
    if (!empty($name) && $name !== $user['user_name']) {
        $update_sql = "UPDATE USERS SET user_name = ? WHERE user_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('si', $name, $user['user_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    if (!empty($picture) && empty($user['profile_picture'])) {
        $update_sql = "UPDATE USERS SET profile_picture = ? WHERE user_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('si', $picture, $user['user_id']);
        $stmt->execute();
        $stmt->close();
    }
} else {
    // Create new user with Google account
    // Get default role_id for regular users (assuming role_id 2 is for regular users)
    $role_result = $conn->query("SELECT role_id FROM ROLES WHERE role_name = 'User' LIMIT 1");
    $role_row = $role_result->fetch_assoc();
    $role_id = $role_row ? $role_row['role_id'] : 2;
    
    // Generate a random password (user won't need it for Google login)
    $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    
    $insert_sql = "INSERT INTO USERS (user_name, email, password_, role_id, google_id, profile_picture) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param('sssiss', $name, $email, $random_password, $role_id, $google_id, $picture);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $stmt->close();
    
    // Fetch the newly created user
    $user_sql = "SELECT * FROM USERS WHERE user_id = ?";
    $stmt = $conn->prepare($user_sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

// Set session variables
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['user_name'] = $user['user_name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role_id'] = $user['role_id'];
$_SESSION['google_id'] = $google_id;
$_SESSION['login_method'] = 'google';

// Get role name
$role_sql = "SELECT role_name FROM ROLES WHERE role_id = ?";
$stmt = $conn->prepare($role_sql);
$stmt->bind_param('i', $user['role_id']);
$stmt->execute();
$role_result = $stmt->get_result();
$role_row = $role_result->fetch_assoc();
$_SESSION['role_name'] = $role_row['role_name'];
$stmt->close();

$conn->close();

// Redirect based on role
if ($user['role_id'] == 1) {
    // Admin
    header('Location: dashboard.php');
} else {
    // Regular user - redirect to client index
    header('Location: client/index.php');
}
exit();
?>

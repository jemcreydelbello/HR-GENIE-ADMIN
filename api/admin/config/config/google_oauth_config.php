<?php
// Google OAuth Configuration
// Get these from: https://console.cloud.google.com/apis/credentials

// CLIENT SIDE - Google OAuth credentials (for client portal)
// TODO: Create a separate OAuth 2.0 Client ID in Google Cloud Console for client side
// Then replace these with your client-side credentials
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET'));
define('GOOGLE_REDIRECT_URI_CLIENT', 'http://localhost/FAQ/client/google_oauth_callback.php');

// reCAPTCHA Configuration
// Get these from: https://www.google.com/recaptcha/admin

// Replace with your actual reCAPTCHA keys
define('RECAPTCHA_SITE_KEY', '6LeXRUwsAAAAAD_I5j4fH91f637HV_rPW9vPoEek');
define('RECAPTCHA_SECRET_KEY', '6LeXRUwsAAAAALuJcZZAjYoLaAeK8pxjCFvrw4he');

// Google OAuth endpoints (for client side)
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo');
?>

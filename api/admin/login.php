<?php
session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Genie | Admin Login</title>
    <link rel="icon" type="image/jpeg" href="assets/img/intellismart.jpg">
    <link rel="stylesheet" href="styles.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
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

        .notification-warning {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FCD34D;
        }
    </style>
<body>
    <div class="login-container">
        <!-- Logo at top left -->
        <div class="top-logo">
            <h1 style="color: white;">HR Genie</h1>
        </div>

        <!-- Left Panel - Slideshow Banner -->
        <div class="left-panel">
            <div class="slideshow-container">
                 <div class="slide active">
                    <img src="assets/img/login3.png" alt="Slide 1" class="slide-image">
                </div>
                <div class="slide active">
                    <img src="assets/img/login2.png" alt="Slide 2" class="slide-image">
                </div>
                <div class="slide">
                    <img src="assets/img/login3.png" alt="Slide 3" class="slide-image">
                </div>
            </div>
            
            <!-- Decorative shapes overlay -->
            <div class="shapes-overlay">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
                <div class="shape shape-4"></div>
                <div class="shape shape-5"></div>
            </div>
            
           
            
            <!-- Slide indicators -->
            <div class="slide-indicators">
                <span class="indicator active" data-slide="0"></span>
                <span class="indicator" data-slide="1"></span>
                <span class="indicator" data-slide="2"></span>
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="right-panel">
            <div class="login-form-container">
                <div class="form-header">
                    <h2 class="form-logo">HR Genie</h2>
                    <h3 class="form-title">Welcome Aboard, Admin!</h3>
                    <p class="form-subtitle">Enter your username and password to proceed.</p>
                </div>

                <?php if ($error): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login_process.php" class="login-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            placeholder="Enter your username"
                            value="<?php echo isset($_COOKIE['remember_username']) ? htmlspecialchars($_COOKIE['remember_username']) : ''; ?>"
                            required
                            autocomplete="username"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-input-wrapper">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="toggle-password" id="togglePassword">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M10 3C6 3 3.5 5.5 2 8C3.5 10.5 6 13 10 13C14 13 16.5 10.5 18 8C16.5 5.5 14 3 10 3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember_me" <?php echo isset($_COOKIE['remember_username']) ? 'checked' : ''; ?>>
                            <span>Remember Me</span>
                        </label>
                        <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                    </div>

                    <!-- reCAPTCHA -->
                    <div class="recaptcha-container" style="margin: 1rem 0; display: flex; justify-content: center;">
                        <div class="g-recaptcha" data-sitekey="<?php 
                            require_once '../config/google_oauth_config.php';
                            echo RECAPTCHA_SITE_KEY; 
                        ?>"></div>
                    </div>

                    <button type="submit" class="login-btn">
                        Log In
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Slideshow functionality
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');
        const indicators = document.querySelectorAll('.indicator');
        const totalSlides = slides.length;

        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            indicators.forEach(indicator => indicator.classList.remove('active'));
            
            slides[index].classList.add('active');
            indicators[index].classList.add('active');
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % totalSlides;
            showSlide(currentSlide);
        }

        // Auto-advance slideshow every 5 seconds
        setInterval(nextSlide, 5000);

        // Manual indicator click
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => {
                currentSlide = index;
                showSlide(currentSlide);
            });
        });

        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Update icon
            togglePassword.innerHTML = type === 'password' 
                ? '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 3C6 3 3.5 5.5 2 8C3.5 10.5 6 13 10 13C14 13 16.5 10.5 18 8C16.5 5.5 14 3 10 3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/></svg>'
                : '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M2 2L18 18M8 8C7.5 8.5 7.5 9.5 8 10C8.5 10.5 9.5 10.5 10 10M14 14C12.5 15 10.5 15.5 8 15.5C4 15.5 1.5 13 0 10.5C0.5 9.5 1.5 8.5 2.5 7.5M6 6C4.5 5 3 4.5 2 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        });

        // Show notification function
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification-toast notification-${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            // Hide and remove after 4 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 4000);
        }

        // Verify reCAPTCHA on form submit
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            if (typeof grecaptcha === 'undefined') {
                e.preventDefault();
                showNotification('reCAPTCHA failed to load. Please refresh and try again.', 'error');
                return false;
            }
            
            const recaptchaResponse = grecaptcha.getResponse();
            if (!recaptchaResponse) {
                e.preventDefault();
                showNotification('Please complete the reCAPTCHA verification.', 'warning');
                return false;
            }
        });
    </script>
</body>
</html>
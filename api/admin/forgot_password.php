<?php
session_start();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include 'db.php';
    
    $email = isset($_POST['email']) ? $conn->real_escape_string(trim($_POST['email'])) : '';
    
    if (!empty($email)) {
        // Check if email exists
        $check_sql = "SELECT user_id FROM USERS WHERE email = '$email'";
        $result = $conn->query($check_sql);
        
        if ($result && $result->num_rows > 0) {
            // Reset password to 'reset123'
            $reset_password = password_hash('reset123', PASSWORD_DEFAULT);
            $user = $result->fetch_assoc();
            $user_id = $user['user_id'];
            
            $update_sql = "UPDATE USERS SET password_ = '$reset_password' WHERE user_id = $user_id";
            
            if ($conn->query($update_sql)) {
                $message = 'Password has been reset to "reset123". Please log in and change your password immediately.';
                $message_type = 'success';
            } else {
                $message = 'Error resetting password: ' . $conn->error;
                $message_type = 'error';
            }
        } else {
            $message = 'Email address not found.';
            $message_type = 'error';
        }
    } else {
        $message = 'Please enter your email address.';
        $message_type = 'error';
    }
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hrdotnet Genie | Forgot Password</title>
    <link rel="icon" type="image/jpeg" href="assets/img/intellismart.jpg">
    <link rel="stylesheet" href="styles.css">
    <style>
        .forgot-form-container {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
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
        
        .back-link {
            text-align: center;
            margin-top: 1rem;
        }
        
        .back-link a {
            color: #3B82F6;
            text-decoration: none;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Logo at top left -->
        <div class="top-logo">
            <h1 style="color: white;">Hrdotnet Genie</h1>
        </div>

        <!-- Left Panel - Same as login -->
        <div class="left-panel">
            <div class="slideshow-container">
                <div class="slide active">
                    <img src="login2.png" alt="Slide 1" class="slide-image">
                </div>
                <div class="slide">
                    <img src="login3.png" alt="Slide 2" class="slide-image">
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

        <!-- Right Panel - Forgot Password Form -->
        <div class="right-panel">
            <div class="login-form-container forgot-form-container">
                <div class="form-header">
                    <h2 class="form-logo">Hrdotnet Genie</h2>
                    <h3 class="form-title">Forgot Password</h3>
                    <p class="form-subtitle">Enter your email address and we'll reset your password.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="login-form">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Enter your email address"
                            required
                            autocomplete="email"
                        >
                    </div>

                    <button type="submit" class="login-btn">
                        Reset Password
                    </button>
                </form>
                
                <div class="back-link">
                    <a href="login.php">← Back to Login</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Slideshow functionality (same as login.php)
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
    </script>
</body>
</html>
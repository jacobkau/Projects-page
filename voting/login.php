<?php
session_start();
include("conn.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        // Note: Your table uses 'password' column, not 'password_hash'
        $stmt = $conn->prepare("SELECT id, username, name, email, profile_photo_blob, profile_photo_type, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password using the 'password' column
            if (password_verify($password, $row['password'])) {
                // Store user info in session
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['full_name'] = $row['name'];
                $_SESSION['email'] = $row['email'];
                
                if (!empty($row['profile_photo_blob'])) {
                    $_SESSION['profile_photo'] = 'data:image/' . $row['profile_photo_type'] . ';base64,' . base64_encode($row['profile_photo_blob']);
                } else {
                    $_SESSION['profile_photo'] = null;
                }
                
                $_SESSION['user_logged_in'] = true;

                // Redirect to profile page
                header("Location: profile.php");
                exit();
            } else {
                $error = "Invalid password. Please try again.";
            }
        } else {
            $error = "Username not found. Please check your username or <a href='register.php'>register here</a>.";
        }
        $stmt->closeCursor();
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $error = "An error occurred during login. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Online Voting Management System | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-form-wrapper {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            width: 550px;
            max-width: 90%;
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-title {
            text-align: center;
            margin-bottom: 35px;
            color: #374151;
            font-size: 28px;
            font-weight: 600;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .form-group {
            margin-bottom: 25px;
            width: 100%;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            color: #4B5563;
            font-weight: 500;
            font-size: 16px;
            text-align: left;
        }

        .form-input {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 16px;
            color: #374151;
            background-color: #F9FAFB;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-input:focus {
            outline: none;
            border-color: #6366F1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            background-color: #ffffff;
        }

        .form-button {
            background-color: #6366F1;
            color: #ffffff;
            padding: 16px 24px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: fit-content;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 160px;
        }

        .form-button:hover {
            background-color: #4F46E5;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .form-button:active {
            transform: translateY(0);
        }
        
        .form-button.loading {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .form-button.loading:hover {
            transform: none;
            box-shadow: none;
        }
        
        .form-button .btn-text {
            display: inline-block;
        }
        
        .form-button .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            margin-right: 10px;
        }
        
        .form-button.loading .spinner {
            display: inline-block;
        }
        
        .form-button.loading .btn-text {
            display: inline-block;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .error-message {
            color: #E11D48;
            font-size: 14px;
            margin-top: 5px;
            margin-bottom: 20px;
            text-align: center;
            padding: 10px;
            background-color: #FEE2E2;
            border-radius: 8px;
            border-left: 4px solid #E11D48;
        }

        .success-message {
            color: #10B981;
            font-size: 14px;
            margin-top: 5px;
            margin-bottom: 20px;
            text-align: center;
            padding: 10px;
            background-color: #D1FAE5;
            border-radius: 8px;
            border-left: 4px solid #10B981;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
        }

        .register-link a {
            color: #6366F1;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: #4F46E5;
            text-decoration: underline;
        }
        
        .divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #E5E7EB;
        }
        
        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            color: #9CA3AF;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form-wrapper">
            <h1 class="login-title">Welcome Back!</h1>
            <p style="text-align: center; color: #6B7280; margin-bottom: 30px;">Please login to your account</p>
            
            <?php if (isset($error) && !empty($error)): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] == 'success'): ?>
                <div class="success-message">
                    Registration successful! Please login with your credentials.
                </div>
            <?php endif; ?>
            
            <form method="post" action="login.php" class="login-form" id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" name="username" id="username" class="form-input" placeholder="Enter your username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="form-button" id="loginBtn">
                    <span class="spinner"></span>
                    <span class="btn-text">Login</span>
                </button>
                
                <div class="divider">
                    <span>or</span>
                </div>
                
                <p class="register-link">
                    <a href="register.php">Create New Account</a>
                </p>
                <p class="register-link">
                    <a href="forgot_password.php">Forgot Password?</a>
                </p>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        
        if (form && loginBtn) {
            form.addEventListener('submit', function(e) {
                // Validate fields
                if (!usernameInput.value.trim()) {
                    e.preventDefault();
                    showError('Please enter your username.');
                    return false;
                }
                
                if (!passwordInput.value.trim()) {
                    e.preventDefault();
                    showError('Please enter your password.');
                    return false;
                }
                
                // Show loading state
                loginBtn.classList.add('loading');
                loginBtn.disabled = true;
                
                // The form will submit normally
                return true;
            });
        }
        
        function showError(message) {
            // Remove any existing error message
            const existingError = document.querySelector('.error-message:not(.success-message)');
            if (existingError && !existingError.classList.contains('success-message')) {
                existingError.remove();
            }
            
            // Create new error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.innerHTML = message;
            
            // Insert after the form title
            const title = document.querySelector('.login-title');
            title.insertAdjacentElement('afterend', errorDiv);
            
            // Remove after 5 seconds
            setTimeout(function() {
                errorDiv.remove();
            }, 5000);
        }
        
        // Reset loading state if user navigates back
        window.addEventListener('pageshow', function() {
            if (loginBtn) {
                loginBtn.classList.remove('loading');
                loginBtn.disabled = false;
            }
        });
        
        // Remove error message when user starts typing
        if (usernameInput) {
            usernameInput.addEventListener('focus', function() {
                const error = document.querySelector('.error-message:not(.success-message)');
                if (error) error.remove();
            });
        }
        
        if (passwordInput) {
            passwordInput.addEventListener('focus', function() {
                const error = document.querySelector('.error-message:not(.success-message)');
                if (error) error.remove();
            });
        }
    });
    </script>
</body>
</html>

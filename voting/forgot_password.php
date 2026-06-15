<?php
session_start();
include("conn.php");

$message = "";
$messageType = "";
$display_reset_form = false;
$token = "";

// Check if token is provided in URL
if (isset($_GET['token'])) {
    $display_reset_form = true;
    $token = trim($_GET['token']);
}

// Handle Forgot Password Request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot_password'])) {
    $email = trim($_POST['email']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $messageType = "error";
    } else {
        try {
            // Check if email exists using PDO
            $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $user_id = $user['id'];
                $username = $user['username'];

                // Generate a unique token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Create password_reset_tokens table if not exists
                $conn->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(255) NOT NULL UNIQUE,
                    expiry DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )");

                // Store token in database
                $insertStmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expiry) VALUES (?, ?, ?)");
                $insertStmt->execute([$user_id, $token, $expiry]);

                // Send email with reset link
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/forgot_password.php?token=" . $token;
                $subject = "Password Reset Request - Voting System";
                $body = "
                    <html>
                    <head>
                        <title>Password Reset</title>
                    </head>
                    <body>
                        <h2>Password Reset Request</h2>
                        <p>Hello <strong>$username</strong>,</p>
                        <p>We received a request to reset your password for your Voting System account.</p>
                        <p>Click the link below to reset your password (valid for 1 hour):</p>
                        <p><a href='$reset_link' style='background:#6366F1; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Reset Password</a></p>
                        <p>If you didn't request this, please ignore this email.</p>
                        <br>
                        <p>Thank you,<br>Voting System Team</p>
                    </body>
                    </html>
                ";
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: no-reply@votingsystem.com" . "\r\n";

                if (mail($email, $subject, $body, $headers)) {
                    $message = "A password reset link has been sent to your email. Please check your inbox (and spam folder).";
                    $messageType = "success";
                } else {
                    $message = "Failed to send reset email. Please try again later.";
                    $messageType = "error";
                    error_log("Failed to send password reset email to: " . $email);
                }
            } else {
                $message = "Email address not found in our records.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "An error occurred. Please try again later.";
            $messageType = "error";
            error_log("Forgot password error: " . $e->getMessage());
        }
    }
}

// Handle Reset Password Request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password']) && isset($_POST['token'])) {
    $token = trim($_POST['token']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $messageType = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } else {
        try {
            // Verify token and expiry using PDO
            $stmt = $conn->prepare("SELECT user_id, expiry FROM password_reset_tokens WHERE token = ?");
            $stmt->execute([$token]);
            
            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $user_id = $row['user_id'];
                $expiry = $row['expiry'];

                if (strtotime(date('Y-m-d H:i:s')) > strtotime($expiry)) {
                    $message = "Password reset link has expired. Please request a new one.";
                    $messageType = "error";
                } else {
                    // Update password (note: your table uses 'password' column, not 'password_hash')
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->execute([$password_hash, $user_id]);

                    // Delete used token
                    $deleteStmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
                    $deleteStmt->execute([$token]);

                    $message = "Password reset successfully! You can now login with your new password.";
                    $messageType = "success";
                    $display_reset_form = false;
                    
                    // Redirect to login after 3 seconds
                    echo "<script>setTimeout(function(){ window.location.href = 'login.php'; }, 3000);</script>";
                }
            } else {
                $message = "Invalid password reset link.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "An error occurred. Please try again later.";
            $messageType = "error";
            error_log("Reset password error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $display_reset_form ? "Reset Password" : "Forgot Password"; ?> | Voting System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-form-wrapper {
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .login-title {
            text-align: center;
            margin-bottom: 10px;
            color: #1f2937;
            font-size: 28px;
            font-weight: 700;
        }
        
        .login-subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 30px;
        }
        
        .message {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .message.error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-label i {
            margin-right: 8px;
            color: #667eea;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-button {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .form-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .form-button.loading {
            opacity: 0.8;
            cursor: not-allowed;
            transform: none;
        }
        
        .form-button.loading:hover {
            transform: none;
            box-shadow: none;
        }
        
        .form-button .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        .form-button.loading .spinner {
            display: inline-block;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .login-register-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .login-register-links a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            margin: 0 10px;
            transition: color 0.3s;
        }
        
        .login-register-links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .back-to-login {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #9ca3af;
            font-size: 13px;
            text-decoration: none;
        }
        
        .back-to-login:hover {
            color: #667eea;
        }
        
        @media (max-width: 480px) {
            .login-form-wrapper {
                padding: 30px 25px;
            }
            .login-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form-wrapper">
            <h1 class="login-title">
                <i class="fas <?php echo $display_reset_form ? 'fa-key' : 'fa-envelope'; ?>"></i>
                <?php echo $display_reset_form ? "Reset Password" : "Forgot Password"; ?>
            </h1>
            <p class="login-subtitle">
                <?php echo $display_reset_form ? "Enter your new password below" : "Enter your email to receive a reset link"; ?>
            </p>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo $display_reset_form ? 'forgot_password.php?token=' . $token : 'forgot_password.php'; ?>" class="login-form" id="passwordForm">
                <?php if ($display_reset_form) { ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> New Password
                        </label>
                        <input type="password" name="password" id="password" class="form-input" placeholder="Enter new password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-check-circle"></i> Confirm Password
                        </label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Confirm new password" required>
                    </div>
                    <button type="submit" name="reset_password" class="form-button" id="submitBtn">
                        <span class="spinner"></span>
                        <span><i class="fas fa-save"></i> Reset Password</span>
                    </button>
                <?php } else { ?>
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" name="email" id="email" class="form-input" placeholder="your@email.com" required>
                    </div>
                    <button type="submit" name="forgot_password" class="form-button" id="submitBtn">
                        <span class="spinner"></span>
                        <span><i class="fas fa-paper-plane"></i> Send Reset Link</span>
                    </button>
                <?php } ?>
            </form>
            
            <div class="login-register-links">
                <?php if (!$display_reset_form) { ?>
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <span>|</span>
                    <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
                <?php } else { ?>
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                <?php } ?>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('passwordForm');
        const submitBtn = document.getElementById('submitBtn');
        
        if (form && submitBtn) {
            form.addEventListener('submit', function() {
                // Show loading state
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                return true;
            });
        }
        
        // Reset loading state if user navigates back
        window.addEventListener('pageshow', function() {
            if (submitBtn) {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        });
    });
    </script>
</body>
</html>

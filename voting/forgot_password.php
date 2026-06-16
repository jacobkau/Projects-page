<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ob_start(); 

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

// Handle Forgot Password Request - Moved to top for proper handling
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot_password'])) {
    $email = trim($_POST['email']);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Invalid email format. Please enter a valid email address.";
        $messageType = "error";
    } else {
        try {
            // Check if email exists
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

                // Get the correct base URL
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $uri = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                $reset_link = "$protocol://$host$uri/forgot_password.php?token=$token";

                // Send email using simple mail function (works on most hosts)
                $subject = "Password Reset Request - Voting System";
                $body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Password Reset</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { padding: 30px; background: #f9fafb; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px; }
                        .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>🗳️ Voting System Password Reset</h2>
                        </div>
                        <div class='content'>
                            <p>Hello <strong>$username</strong>,</p>
                            <p>We received a request to reset your password for your Voting System account.</p>
                            <div style='text-align: center;'>
                                <a href='$reset_link' class='button'>🔐 Reset Password</a>
                            </div>
                            <p>Or copy this link: <br><small>$reset_link</small></p>
                            <p><strong>Note:</strong> This link will expire in 1 hour.</p>
                            <p>If you didn't request this, please ignore this email.</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " Voting System. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: Voting System <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
                $headers .= "Reply-To: support@" . $_SERVER['HTTP_HOST'] . "\r\n";

                if (mail($email, $subject, $body, $headers)) {
                    $message = "✓ A password reset link has been sent to your email. Please check your inbox (and spam folder).";
                    $messageType = "success";
                } else {
                    $message = "⚠️ Unable to send reset email. Please try again later or contact support.";
                    $messageType = "error";
                    error_log("Failed to send password reset email to: " . $email);
                }
            } else {
                $message = "❌ Email address not found in our records. Please check and try again.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "❌ An error occurred. Please try again later.";
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
        $message = "❌ Password must be at least 6 characters long.";
        $messageType = "error";
    } elseif ($password !== $confirm_password) {
        $message = "❌ Passwords do not match. Please try again.";
        $messageType = "error";
    } else {
        try {
            // Verify token and expiry
            $stmt = $conn->prepare("SELECT user_id, expiry FROM password_reset_tokens WHERE token = ?");
            $stmt->execute([$token]);
            
            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $user_id = $row['user_id'];
                $expiry = $row['expiry'];

                if (strtotime(date('Y-m-d H:i:s')) > strtotime($expiry)) {
                    $message = "❌ Password reset link has expired. Please request a new one.";
                    $messageType = "error";
                } else {
                    // Update password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->execute([$password_hash, $user_id]);

                    // Delete used token
                    $deleteStmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
                    $deleteStmt->execute([$token]);

                    $message = "✓ Password reset successfully! Redirecting to login page...";
                    $messageType = "success";
                    $display_reset_form = false;
                    
                    // Redirect to login after 2 seconds
                    echo "<script>setTimeout(function(){ window.location.href = 'login.php'; }, 2000);</script>";
                }
            } else {
                $message = "❌ Invalid password reset link. Please request a new one.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "❌ An error occurred. Please try again later.";
            $messageType = "error";
            error_log("Reset password error: " . $e->getMessage());
        }
    }
}

// Verify token validity when displaying reset form
if ($display_reset_form && $token) {
    try {
        $stmt = $conn->prepare("SELECT expiry FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (strtotime(date('Y-m-d H:i:s')) > strtotime($row['expiry'])) {
                $message = "❌ This password reset link has expired. Please request a new one.";
                $messageType = "error";
                $display_reset_form = false;
            }
        } else {
            $message = "❌ Invalid password reset link. Please request a new one.";
            $messageType = "error";
            $display_reset_form = false;
        }
    } catch (PDOException $e) {
        $message = "❌ An error occurred. Please try again later.";
        $messageType = "error";
        $display_reset_form = false;
    }
}
?>

<?php include("header.php"); ?>

<style>
    .forgot-container {
        max-width: 500px;
        margin: 40px auto;
        background: white;
        border-radius: 24px;
        padding: 40px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
    }
    
    body.dark-theme .forgot-container {
        background: #1e1e2e;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    }
    
    .forgot-title {
        text-align: center;
        margin-bottom: 10px;
        color: #1f2937;
        font-size: 28px;
        font-weight: 700;
        transition: color 0.3s ease;
    }
    
    body.dark-theme .forgot-title {
        color: #f3f4f6;
    }
    
    .forgot-subtitle {
        text-align: center;
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 30px;
        transition: color 0.3s ease;
    }
    
    body.dark-theme .forgot-subtitle {
        color: #9ca3af;
    }
    
    .message {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .message.success {
        background-color: #d1fae5;
        color: #065f46;
        border-left: 4px solid #10b981;
    }
    
    body.dark-theme .message.success {
        background-color: #064e3b;
        color: #a7f3d0;
    }
    
    .message.error {
        background-color: #fee2e2;
        color: #991b1b;
        border-left: 4px solid #dc2626;
    }
    
    body.dark-theme .message.error {
        background-color: #7f1d1d;
        color: #fecaca;
    }
    
    .message.info {
        background-color: #dbeafe;
        color: #1e40af;
        border-left: 4px solid #3b82f6;
    }
    
    body.dark-theme .message.info {
        background-color: #1e3a5f;
        color: #93c5fd;
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
        transition: color 0.3s ease;
    }
    
    body.dark-theme .form-label {
        color: #e5e7eb;
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
        background: white;
    }
    
    body.dark-theme .form-input {
        background: #2d2d3d;
        border-color: #3d3d4d;
        color: #f3f4f6;
    }
    
    .form-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-input.error {
        border-color: #dc2626;
    }
    
    .form-input.valid {
        border-color: #10b981;
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
    
    .login-links {
        text-align: center;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }
    
    body.dark-theme .login-links {
        border-top-color: #3d3d4d;
    }
    
    .login-links a {
        color: #667eea;
        text-decoration: none;
        font-size: 14px;
        margin: 0 10px;
        transition: color 0.3s;
    }
    
    .login-links a:hover {
        color: #764ba2;
        text-decoration: underline;
    }
    
    @media (max-width: 768px) {
        .forgot-container {
            margin: 20px;
            padding: 30px 25px;
        }
        .forgot-title {
            font-size: 24px;
        }
    }
</style>

<div class="forgot-container">
    <h1 class="forgot-title">
        <i class="fas <?php echo $display_reset_form ? 'fa-key' : 'fa-envelope'; ?>"></i>
        <?php echo $display_reset_form ? "Reset Password" : "Forgot Password"; ?>
    </h1>
    <p class="forgot-subtitle">
        <?php echo $display_reset_form ? "Enter your new password below" : "Enter your email to receive a password reset link"; ?>
    </p>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>" id="statusMessage">
            <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : ($messageType == 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'); ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="<?php echo $display_reset_form ? 'forgot_password.php?token=' . urlencode($token) : 'forgot_password.php'; ?>" id="passwordForm">
        <?php if ($display_reset_form) { ?>
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="fas fa-lock"></i> New Password
                </label>
                <input type="password" name="password" id="password" class="form-input" placeholder="Enter new password (min 6 characters)" required>
                <small style="color: #6b7280; font-size: 12px; margin-top: 5px; display: block;">Password must be at least 6 characters long</small>
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
                <small style="color: #6b7280; font-size: 12px; margin-top: 5px; display: block;">We'll send a password reset link to this email</small>
            </div>
            <button type="submit" name="forgot_password" class="form-button" id="submitBtn">
                <span class="spinner"></span>
                <span><i class="fas fa-paper-plane"></i> Send Reset Link</span>
            </button>
        <?php } ?>
    </form>
    
    <div class="login-links">
        <?php if (!$display_reset_form) { ?>
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Back to Login</a>
            <span>|</span>
            <a href="register.php"><i class="fas fa-user-plus"></i> Create New Account</a>
        <?php } else { ?>
            <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
        <?php } ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('passwordForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            <?php if ($display_reset_form): ?>
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            // Reset border colors
            password.style.borderColor = '#e5e7eb';
            confirmPassword.style.borderColor = '#e5e7eb';
            
            // Validate password length
            if (password.value.length < 6) {
                e.preventDefault();
                password.style.borderColor = '#dc2626';
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            // Validate password match
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                confirmPassword.style.borderColor = '#dc2626';
                alert('Passwords do not match!');
                return false;
            }
            <?php else: ?>
            // Validate email for forgot password form
            const email = document.getElementById('email');
            const emailValue = email.value.trim();
            
            if (emailValue === '') {
                e.preventDefault();
                email.style.borderColor = '#dc2626';
                alert('Please enter your email address!');
                return false;
            }
            
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(emailValue)) {
                e.preventDefault();
                email.style.borderColor = '#dc2626';
                alert('Please enter a valid email address!');
                return false;
            }
            <?php endif; ?>
            
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            return true;
        });
    }
    
    // Real-time validation
    <?php if ($display_reset_form): ?>
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (password.value !== this.value) {
                this.style.borderColor = '#dc2626';
            } else {
                this.style.borderColor = '#10b981';
            }
        });
    }
    
    if (password) {
        password.addEventListener('input', function() {
            if (this.value.length > 0 && this.value.length < 6) {
                this.style.borderColor = '#f59e0b';
            } else if (this.value.length >= 6) {
                this.style.borderColor = '#10b981';
            } else {
                this.style.borderColor = '#e5e7eb';
            }
        });
    }
    <?php else: ?>
    const email = document.getElementById('email');
    if (email) {
        email.addEventListener('input', function() {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const isValid = emailPattern.test(this.value);
            if (this.value.length > 0 && !isValid) {
                this.style.borderColor = '#f59e0b';
            } else if (isValid) {
                this.style.borderColor = '#10b981';
            } else {
                this.style.borderColor = '#e5e7eb';
            }
        });
    }
    <?php endif; ?>
    
    // Reset loading state if user navigates back
    window.addEventListener('pageshow', function() {
        if (submitBtn) {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        }
    });
});
</script>

<?php include("footer.php");
ob_end_flush(); // Flush output buffer

?>

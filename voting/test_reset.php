<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include("conn.php");

// Include Resend mail config
if (file_exists(__DIR__ . '/mail_config.php')) {
    require_once __DIR__ . '/mail_config.php';
}

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
                $reset_link = "$protocol://$host$uri/test_reset.php?token=$token";

                // Send email using Resend or fallback
                $emailSent = false;
                
                if (function_exists('sendEmailWithResend')) {
                    $result = sendEmailWithResend($email, $username, $reset_link);
                    if ($result['success']) {
                        $emailSent = true;
                    }
                }
                
                // If Resend failed or not available, try native mail
                if (!$emailSent) {
                    $subject = "Password Reset Request - Voting System";
                    $body = "
                    <html>
                    <body>
                        <h2>Password Reset Request</h2>
                        <p>Hello <strong>$username</strong>,</p>
                        <p>Click the link below to reset your password:</p>
                        <p><a href='$reset_link'>$reset_link</a></p>
                        <p>This link expires in 1 hour.</p>
                        <p>If you didn't request this, please ignore this email.</p>
                    </body>
                    </html>
                    ";
                    
                    $headers = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                    $headers .= "From: Voting System <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
                    
                    $emailSent = mail($email, $subject, $body, $headers);
                }

                if ($emailSent) {
                    $message = "✓ A password reset link has been sent to your email. Please check your inbox (and spam folder).";
                    $messageType = "success";
                } else {
                    $message = "⚠️ Unable to send reset email. Please try again later.";
                    $messageType = "error";
                }
            } else {
                $message = "❌ Email address not found in our records. Please check and try again.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "❌ Database error: " . $e->getMessage();
            $messageType = "error";
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
            $message = "❌ Database error: " . $e->getMessage();
            $messageType = "error";
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
        $message = "❌ Database error: " . $e->getMessage();
        $messageType = "error";
        $display_reset_form = false;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $display_reset_form ? "Reset Password" : "Forgot Password"; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .container { max-width: 500px; width: 100%; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        h1 { text-align: center; color: #333; margin-bottom: 10px; font-size: 28px; }
        .subtitle { text-align: center; color: #666; margin-bottom: 30px; font-size: 14px; }
        .message { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .message.success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .message.error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; color: #333; margin-bottom: 5px; }
        label i { color: #667eea; margin-right: 8px; }
        input[type="email"], input[type="password"] { width: 100%; padding: 14px 16px; border: 2px solid #ddd; border-radius: 10px; font-size: 16px; transition: all 0.3s; }
        input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        button { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        button:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102,126,234,0.4); }
        button:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        .links { text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
        .links a { color: #667eea; text-decoration: none; margin: 0 10px; font-size: 14px; }
        .links a:hover { text-decoration: underline; }
        small { color: #666; font-size: 12px; display: block; margin-top: 5px; }
        .spinner { display: none; width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 0.8s linear infinite; }
        button.loading .spinner { display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 480px) { .container { padding: 25px; } h1 { font-size: 24px; } }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas <?php echo $display_reset_form ? 'fa-key' : 'fa-envelope'; ?>"></i> <?php echo $display_reset_form ? "Reset Password" : "Forgot Password"; ?></h1>
        <p class="subtitle"><?php echo $display_reset_form ? "Enter your new password below" : "Enter your email to receive a reset link"; ?></p>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo $display_reset_form ? 'test_reset.php?token=' . urlencode($token) : 'test_reset.php'; ?>" id="resetForm">
            <?php if ($display_reset_form) { ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> New Password</label>
                    <input type="password" name="password" id="password" placeholder="Enter new password (min 6 characters)" required>
                    <small>Password must be at least 6 characters long</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-check-circle"></i> Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                </div>
                <button type="submit" name="reset_password" id="submitBtn">
                    <span class="spinner"></span>
                    <i class="fas fa-save"></i> Reset Password
                </button>
            <?php } else { ?>
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" id="email" placeholder="your@email.com" required>
                    <small>We'll send a password reset link to this email</small>
                </div>
                <button type="submit" name="forgot_password" id="submitBtn">
                    <span class="spinner"></span>
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            <?php } ?>
        </form>
        
        <div class="links">
            <?php if (!$display_reset_form) { ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                <span>|</span>
                <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
            <?php } else { ?>
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            <?php } ?>
        </div>
    </div>
    
    <script>
    document.getElementById('resetForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('submitBtn');
        
        <?php if ($display_reset_form): ?>
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (password.value.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long!');
            return false;
        }
        if (password.value !== confirmPassword.value) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
        <?php else: ?>
        const email = document.getElementById('email');
        if (email.value.trim() === '') {
            e.preventDefault();
            alert('Please enter your email address!');
            return false;
        }
        <?php endif; ?>
        
        btn.classList.add('loading');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Processing...';
    });
    </script>
</body>
</html>

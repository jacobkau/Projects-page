<?php
session_start();
include("conn.php");

$message = ""; // Initialize message variable

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['forgot_password'])) {
        $email = trim($_POST['email']);

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
        } else {
            try {
                // Check if email exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                $stmt->bind_param("s", $email);
                if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $row = $result->fetch_assoc();
                    $user_id = $row['id'];

                    // Generate a unique token
                    $token = bin2hex(random_bytes(32));

                    // Store token in database with expiration time (e.g., 1 hour)
                    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expiry) VALUES (?, ?, ?)");
                    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                    $stmt->bind_param("iss", $user_id, $token, $expiry);
                    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

                    // Send email with reset link
                    $reset_link = "forgot_password.php?token=" . $token;
                    $subject = "Password Reset Request";
                    $body = "Please click the following link to reset your password: " . $reset_link;
                    $from = "kaujames4@gmail.com"; // Replace with your valid "From" address
                    $headers = "From: " . $from . "\r\n" .
                               "Reply-To: " . $from . "\r\n" .
                               "X-Mailer: PHP/" . phpversion();

                    if (mail($email, $subject, $body, $headers)) {
                        $message = "A password reset link has been sent to your email. Please check your inbox (and spam folder).";
                    } else {
                        $message = "Failed to send reset email. Please try again. Check your spam folder.";
                        error_log("Failed to send password reset email to: " . $email);
                        $error = error_get_last();
                        if ($error) {
                            error_log("Mail Error: " . print_r($error, true));
                        }
                    }
                } else {
                    $message = "Email address not found.";
                }
                $stmt->close();
            } catch (Exception $e) {
                $message = "An error occurred. Please try again later.";
                error_log("Forgot password error: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['reset_password']) && isset($_POST['token'])) {
        $token = trim($_POST['token']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        if ($password !== $confirm_password) {
            $message = "Passwords do not match.";
        } else {
            try {
                // Verify token and expiry
                $stmt = $conn->prepare("SELECT user_id, expiry FROM password_reset_tokens WHERE token = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                $stmt->bind_param("s", $token);
                if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $row = $result->fetch_assoc();
                    $user_id = $row['user_id'];
                    $expiry = $row['expiry'];

                    if (strtotime(date('Y-m-d H:i:s')) > strtotime($expiry)) {
                        $message = "Password reset link has expired.";
                    } else {
                        // Update password
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                        $stmt->bind_param("si", $password_hash, $user_id);
                        if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

                        // Delete token
                        $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
                        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                        $stmt->bind_param("s", $token);
                        if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

                        $message = "Password reset successfully. You can now log in.";
                    }
                } else {
                    $message = "Invalid password reset link.";
                }
                $stmt->close();
            } catch (Exception $e) {
                $message = "An error occurred. Please try again later.";
                error_log("Reset password error: " . $e->getMessage());
            }
        }
    }
}

// Display Forgot Password Form or Reset Password Form based on token
$display_reset_form = false;
if (isset($_GET['token'])) {
    $display_reset_form = true;
    $token = $_GET['token'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $display_reset_form ? "Reset Password" : "Forgot Password"; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob Witty">
    <link rel="icon" href="logo.jpg" type="image/x-icon">
</head>
<body>
    <div class="login-container">
        <div class="login-form-wrapper">
            <h1 class="login-title"><?php echo $display_reset_form ? "Reset Password" : "Forgot Password"; ?></h1>
            <p class="message"><?php echo $message; ?></p>
            <form method="post" action="<?php echo $display_reset_form ? "reset_password.php?token=" . $token : "forgot_password.php"; ?>" class="login-form">
                <?php if ($display_reset_form) { ?>
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    <div class="form-group">
                        <label for="password" class="form-label">New Password:</label>
                        <input type="password" name="password" id="password" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password:</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-input" required>
                    </div>
                    <button type="submit" name="reset_password" class="form-button">Reset Password</button>
                <?php } else { ?>
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address:</label>
                        <input type="email" name="email" id="email" class="form-input" required>
                    </div>
                    <button type="submit" name="forgot_password" class="form-button">Send Reset Link</button>
                <?php } ?>
            </form>
            <div class="login-register-links">
                <?php if (!$display_reset_form) { ?>
                    <p><a href="login.php">Login</a> | <a href="register.php">Register</a></p>
                <?php } else { ?>
                    <p><a href="login.php">Login</a></p>
                <?php } ?>
            </div>
        </div>
    </div>
</body>
</html>

<style>
.login-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background-color: #f4f4f4;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.login-form-wrapper {
    background-color: #ffffff;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    width: 400px;
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
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    border-color: #6366F1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
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
    transition: background-color 0.3s ease;
}
.login-register-links {
    text-align: center;
    margin-top: 20px;
}

.login-register-links a {
    color: #6366F1;
    text-decoration: none;
}

.login-register-links a:hover {
    text-decoration: underline;
}
</style>
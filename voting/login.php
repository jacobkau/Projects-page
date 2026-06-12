<?php
session_start();
include("conn.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        // Fetch user data from the database using PDO
        $stmt = $conn->prepare("SELECT id, username, full_name, email, password_hash, profile_photo FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['password_hash'])) {
                // Store user info in session
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['profile_photo'] = $row['profile_photo'];
                $_SESSION['user_logged_in'] = true;

                // Redirect to intended page or dashboard
                $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'profile.php';
                unset($_SESSION['redirect_after_login']);
                header("Location: " . $redirect);
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
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
    <link rel="icon" href="logo.jpg" type="image/x-icon">
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
        }

        .form-button:hover {
            background-color: #4F46E5;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .form-button:active {
            transform: translateY(0);
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
            
            <?php if (isset($error)) { 
                echo "<p class='error-message'>$error</p>"; 
            } ?>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] == 'success') { 
                echo "<p class='success-message'>Registration successful! Please login with your credentials.</p>"; 
            } ?>
            
            <form method="post" action="login.php" class="login-form">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" name="username" id="username" class="form-input" placeholder="Enter your username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="form-button">Login</button>
                
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
</body>
</html>

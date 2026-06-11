<?php
session_start();
include("conn.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Fetch user data from the database
    $stmt = $conn->prepare("SELECT id, username, full_name, email, password_hash, profile_photo FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password_hash'])) {
            // Store user info in session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['profile_photo'] = $row['profile_photo'];

            header("Location: profile.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Username not found.";
    }
    $stmt->close();
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
</head>
<body>
    <div class="login-container">
        <div class="login-form-wrapper">
            <h1 class="login-title">User Login</h1>
            <?php if (isset($error)) { echo "<p class='error-message'>$error</p>"; } ?>
            <form method="post" action="login.php" class="login-form">
                <div class="form-group">
                    <label for="username" class="form-label">Username:</label>
                    <input type="text" name="username" id="username" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="password" class="form-label">Password:</label>
                    <input type="password" name="password" id="password" class="form-input" required>
                </div>
                <button type="submit" class="form-button">Login</button>
                <p class="register-link">
                    <a href="register.php">Register here</a>
                </p>
                <p class="register-link">
                    <a href="forgot_password.php">Forgotten Password</a>
                </p>
            </form>
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
        width: 550px;
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
        align-items: center; /* Center the form elements horizontally */
    }

    .form-group {
        margin-bottom: 25px;
        width: 100%; /* Make form groups take full width */
    }

    .form-label {
        display: block;
        margin-bottom: 10px;
        color: #4B5563;
        font-weight: 500;
        font-size: 16px;
        text-align: left; /* Align labels to the left */
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
        box-sizing: border-box; /* Include padding and border in width */
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
        width: fit-content; /* Make the button only as wide as its content */
    }

    .form-button:hover {
        background-color: #4F46E5;
    }

    .error-message {
        color: #E11D48;
        font-size: 14px;
        margin-top: 5px;
        text-align: center;
    }

    .register-link {
        text-align: center;
        margin-top: 20px;
    }

    .register-link a {
        color: #6366F1;
        text-decoration: none;
    }

    .register-link a:hover {
        text-decoration: underline;
    }
</style>
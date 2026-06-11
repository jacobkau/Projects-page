<?php
session_start();
include("conn.php");

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: main.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ?");
            if (!$stmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            $stmt->bind_param("s", $username);

            if (!$stmt->execute()) {
                throw new Exception("Database execute error: " . $stmt->error);
            }

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $admin_id = $row["id"];
                $db_username = $row["username"];
                $db_password = $row["password"];

                if (password_verify($password, $db_password)) {  // Ensure passwords are hashed in DB
                    $_SESSION['admin_id'] = $admin_id;
                    $_SESSION['username'] = $db_username;

                    // Log event
                    $eventStmt = $conn->prepare("INSERT INTO event_log (username, event_type, event_description) VALUES (?, ?, ?)");
                    $eventType = "Admin Login";
                    $eventDesc = "Admin logged in successfully.";
                    $eventStmt->bind_param("sss", $db_username, $eventType, $eventDesc);
                    $eventStmt->execute();
                    $eventStmt->close();

                    header("Location: main.php");
                    exit();
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "Invalid username.";
            }
            $stmt->close();
        } catch (Exception $dbException) {
            error_log("Login Database Error: " . $dbException->getMessage());
            $error = "An error occurred during login. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob Witty">
    <link rel="icon" href="../logo.jpg" type="image/x-icon">
    <style>
        body {
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f4f4f4;
        }
        .login-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 400px;
        }
        .login-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        .login-container label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .login-container input[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }
        .login-container input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>
        <?php if (!empty($error)) { ?>
            <p class="error-message"><?php echo $error; ?></p>
        <?php } ?>
        <form method="post" action="admin_login.php">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required>
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>
            <input type="submit" value="Login">
        </form>
    </div>
</body>
</html>


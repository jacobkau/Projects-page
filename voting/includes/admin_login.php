<?php
session_start();
include("conn.php");

// Map $conn to $db if your conn.php file sets up the variable as $db
if (!isset($conn) && isset($db)) {
    $conn = $db;
}

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
            // 1. Prepare query using the PDO format (targeting 'users' table)
            $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = :username");
            
            // 2. Execute query by passing the value inside an array
            $stmt->execute([':username' => $username]);
            
            // 3. Fetch the row entry cleanly
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $admin_id = $row["id"];
                $db_username = $row["username"];
                $db_password = $row["password"];

                // 4. Verify the secure hashed password
                if (password_verify($password, $db_password)) {
                    $_SESSION['admin_id'] = $admin_id;
                    $_SESSION['username'] = $db_username;

                    try {
                        // 5. Log login event using secure PDO parameters (Create table if missing)
                        $conn->exec("CREATE TABLE IF NOT EXISTS event_log (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            username VARCHAR(50),
                            event_type VARCHAR(50),
                            event_description VARCHAR(255),
                            date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        );");

                        $eventQuery = "INSERT INTO event_log (username, event_type, event_description) VALUES (:user, :type, :desc)";
                        $eventStmt = $conn->prepare($eventQuery);
                        $eventStmt->execute([
                            ':user' => $db_username,
                            ':type' => "Admin Login",
                            ':desc' => "Admin logged in successfully."
                        ]);
                    } catch (Exception $logError) {
                        // If logging fails, we don't block the user from accessing the main dashboard
                        error_log("Event Log Error: " . $logError->getMessage());
                    }

                    header("Location: main.php");
                    exit();
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "Invalid username.";
            }
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


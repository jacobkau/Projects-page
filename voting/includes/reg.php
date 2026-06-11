<?php
include("conn.php");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    // Basic Validation
    if (empty($username) || empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if username or email already exists
        $check_query = "SELECT * FROM admin WHERE username = '$username' OR email = '$email'";
        $check_result = $conn->query($check_query);

        if ($check_result->num_rows > 0) {
            $error = "Username or email already exists.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user into the database
            $insert_query = "INSERT INTO admin (username, name, email, password) VALUES ('$username', '$name', '$email', '$hashed_password')";

            if ($conn->query($insert_query) === TRUE) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Error: " . $insert_query . "<br>" . $conn->error;
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
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

        .registration-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 600px;
        }

        .registration-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        .registration-container a{
            text-decoration:none;
        }

        .registration-container label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }

        .registration-container input[type="text"],
        .registration-container input[type="email"],
        .registration-container input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .registration-container input[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }

        .registration-container input[type="submit"]:hover {
            background-color: #0056b3;
        }

        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 10px;
        }

        .success-message {
            color: green;
            text-align: center;
            margin-bottom: 10px;
        }

    </style>
</head>
<body>
    <div class="registration-container">
        <h2>Admin | Register</h2>
        <?php if (!empty($error)) { ?>
            <p class="error-message"><?php echo $error; ?></p>
        <?php } ?>
        <?php if (!empty($success)) { ?>
            <p class="success-message"><?php echo $success; ?></p>
        <?php } ?>
        <form method="post" action="reg.php">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required>

            <label for="name">Name:</label>
            <input type="text" name="name" id="name" required>

            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required>

            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>

            <label for="confirm_password">Confirm Password:</label>
            <input type="password" name="confirm_password" id="confirm_password" required>

            <input type="submit" value="Register">
        </form>
        <p style="text-align:center;"><a href="admin_login.php">Already have an account? Login</a></p>
    </div>
</body>
</html>
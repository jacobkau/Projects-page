<?php
session_start();
include("conn.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $selectedElections = isset($_POST['elections']) ? $_POST['elections'] : [];
    $profilePhoto = $_FILES['profile_photo'];

    if (empty($username) || empty($name) || empty($email) || empty($password) || empty($selectedElections)) {
        echo "<script>alert('All fields are required, including selecting at least one election.'); window.history.back();</script>";
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $uploadDir = "uploads/";
    $profilePhotoPath = "";

    if (!empty($profilePhoto['name'])) {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $fileExt = strtolower(pathinfo($profilePhoto['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowedTypes)) {
            echo "<script>alert('Invalid image type. Only JPG, PNG, and GIF are allowed.'); window.history.back();</script>";
            exit;
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $profilePhotoPath = $uploadDir . uniqid("profile_", true) . "." . $fileExt;

        if (!move_uploaded_file($profilePhoto['tmp_name'], $profilePhotoPath)) {
            echo "<script>alert('Failed to upload profile photo.'); window.history.back();</script>";
            exit;
        }
    }

    try {
        $checkUserStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        if (!$checkUserStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $checkUserStmt->bind_param("ss", $username, $email);
        $checkUserStmt->execute();
        $checkUserStmt->store_result();

        if ($checkUserStmt->num_rows > 0) {
            echo "<script>alert('Username or Email already exists.'); window.history.back();</script>";
            exit;
        }
        $checkUserStmt->close();

        $insertUserStmt = $conn->prepare("INSERT INTO users (username, full_name, email, password_hash, profile_photo) VALUES (?, ?, ?, ?, ?)");
        if (!$insertUserStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $insertUserStmt->bind_param("sssss", $username, $name, $email, $passwordHash, $profilePhotoPath);

        if (!$insertUserStmt->execute()) {
            error_log("User insert failed: " . $insertUserStmt->error);
            throw new Exception("Execute failed: " . $insertUserStmt->error);
        }

        $userId = $insertUserStmt->insert_id;
        $insertUserStmt->close();

        $insertElectionStmt = $conn->prepare("INSERT IGNORE INTO user_elections (user_id, election_id) VALUES (?, ?)");
        if (!$insertElectionStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        foreach ($selectedElections as $electionId) {
            $insertElectionStmt->bind_param("ii", $userId, $electionId);
            if (!$insertElectionStmt->execute()) {
                error_log("Election insert failed: " . $insertElectionStmt->error);
                throw new Exception("Execute failed: " . $insertElectionStmt->error);
            }
        }
        $insertElectionStmt->close();

        echo "<script>alert('Registration successful! You have been registered for the selected elections.'); window.location.href = 'login.php';</script>";

    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        echo "<script>alert('Error: Registration failed. Please try again. " . htmlspecialchars($e->getMessage()) . "'); window.history.back();</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OVMS | Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob Witty">
    <link rel="icon" href="logo.jpg" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="registration-container">
        <div class="registration-form-wrapper">
            <h1 class="registration-title">User Registration</h1>
            <form method="post" action="register.php" enctype="multipart/form-data" class="registration-form">
                <div class="form-group">
                    <label for="username" class="form-label">Username:</label>
                    <input type="text" name="username" id="username" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="name" class="form-label">Full Names:</label>
                    <input type="text" name="name" id="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email" name="email" id="email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="password" class="form-label">Password:</label>
                    <input type="password" name="password" id="password" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="profile_photo" class="form-label">Profile Photo:</label>
                    <input type="file" name="profile_photo" id="profile_photo" class="form-input-file" accept="image/*">
                </div>
                <div class="form-group">
                    <label class="form-label">Select Election(s):</label>
                    <?php
                    try {
                        $stmt = $conn->prepare("SELECT id, title FROM elections WHERE registration_open = 1");
                        if (!$stmt) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }
                        $stmt->execute();
                        $stmt->bind_result($electionId, $electionName);

                        while ($stmt->fetch()) {
                            echo "<div class='form-check'><input type='checkbox' name='elections[]' value='$electionId' id='election_$electionId' class='form-check-input'><label class='form-check-label' for='election_$electionId'>" . htmlspecialchars($electionName) . "</label></div>";
                        }
                        $stmt->close();
                    } catch (Exception $e) {
                        error_log("Election fetch error: " . $e->getMessage());
                        echo "<p class='error-message'>Error fetching elections. Please try again.</p>";
                    }
                    ?>
                </div>
                <button type="submit" class="form-button">Register</button>
            </form>
        </div>
    </div>
</body>
</html>
<style>
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
   .registration-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background-color: #f4f4f4;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.registration-form-wrapper {
    background-color: #ffffff;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    width: 650px;
}

.registration-title {
    text-align: center;
    margin-bottom: 35px;
    color: #374151;
    font-size: 28px;
    font-weight: 600;
}

.registration-form {
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

.form-input,
.form-input-file {
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

.form-input:focus,
.form-input-file:focus {
    outline: none;
    border-color: #6366F1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

.form-check {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    justify-content: left; /* Align checkboxes to the left */
}

.form-check-input {
    margin-right: 12px;
    width: 20px;
    height: 20px;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    cursor: pointer;
}

.form-check-label {
    color: #4B5563;
    font-size: 16px;
    cursor: pointer;
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
}
</style>
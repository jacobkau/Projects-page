<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("conn.php");

// Function to create user_elections table if it doesn't exist
function createUserElectionsTable($conn) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS user_elections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            election_id INT NOT NULL,
            registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_registration (user_id, election_id)
        )";
        $conn->exec($sql);
        error_log("user_elections table checked/created successfully");
        return true;
    } catch (PDOException $e) {
        error_log("Error creating user_elections table: " . $e->getMessage());
        return false;
    }
}

// Create the table if it doesn't exist
createUserElectionsTable($conn);

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
    
    // Handle profile photo - convert to BLOB for database storage
    $profilePhotoBlob = null;
    $profilePhotoType = null;

    if (!empty($profilePhoto['name']) && $profilePhoto['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $fileExt = strtolower(pathinfo($profilePhoto['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowedTypes)) {
            echo "<script>alert('Invalid image type. Only JPG, PNG, and GIF are allowed.'); window.history.back();</script>";
            exit;
        }

        // Check file size (limit to 2MB)
        if ($profilePhoto['size'] > 2 * 1024 * 1024) {
            echo "<script>alert('File is too large. Maximum size is 2MB.'); window.history.back();</script>";
            exit;
        }

        // Read file content for database storage
        $profilePhotoBlob = file_get_contents($profilePhoto['tmp_name']);
        $profilePhotoType = $fileExt;
    }

    try {
        // Check if we need to add BLOB columns to users table
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo_blob'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE users ADD COLUMN profile_photo_blob LONGBLOB");
                $conn->exec("ALTER TABLE users ADD COLUMN profile_photo_type VARCHAR(10)");
                error_log("Added BLOB columns to users table");
            }
        } catch (PDOException $e) {
            error_log("Note: " . $e->getMessage());
        }

        // Check if user already exists
        $checkUserStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $checkUserStmt->execute([$username, $email]);
        
        if ($checkUserStmt->rowCount() > 0) {
            echo "<script>alert('Username or Email already exists.'); window.history.back();</script>";
            exit;
        }
        $checkUserStmt->closeCursor();

        // Insert user
        $insertUserStmt = $conn->prepare("INSERT INTO users (username, name, email, password, profile_photo_blob, profile_photo_type) VALUES (?, ?, ?, ?, ?, ?)");
        
        if (!$insertUserStmt->execute([$username, $name, $email, $passwordHash, $profilePhotoBlob, $profilePhotoType])) {
            $error = $insertUserStmt->errorInfo();
            throw new Exception("Failed to insert user: " . $error[2]);
        }

        $userId = $conn->lastInsertId();
        $insertUserStmt->closeCursor();

        // Insert user's election registrations
        $insertElectionStmt = $conn->prepare("INSERT IGNORE INTO user_elections (user_id, election_id) VALUES (?, ?)");
        
        $registeredCount = 0;
        foreach ($selectedElections as $electionId) {
            if ($insertElectionStmt->execute([$userId, $electionId])) {
                $registeredCount++;
            }
        }
        $insertElectionStmt->closeCursor();

        if ($registeredCount > 0) {
            echo "<script>alert('Registration successful! You have been registered for " . $registeredCount . " election(s).'); window.location.href = 'login.php';</script>";
        } else {
            throw new Exception("Failed to register for elections");
        }

    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        // Delete user if registration fails
        if (isset($userId)) {
            $deleteUser = $conn->prepare("DELETE FROM users WHERE id = ?");
            $deleteUser->execute([$userId]);
        }
        echo "<script>alert('Error: Registration failed. " . htmlspecialchars($e->getMessage()) . "'); window.history.back();</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OVMS | Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .registration-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .registration-form-wrapper {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            width: 650px;
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

        .form-input,
        .form-input-file {
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

        .form-input:focus,
        .form-input-file:focus {
            outline: none;
            border-color: #6366F1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            background-color: #ffffff;
        }

        .form-input-file {
            padding: 10px;
        }

        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            justify-content: left;
        }

        .form-check-input {
            margin-right: 12px;
            width: 20px;
            height: 20px;
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
            padding: 10px;
            background-color: #FEE2E2;
            border

<?php
session_start();
include("conn.php");

// Redirect if not logged in
if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$username = $_SESSION["username"];

// Fetch user data using PDO
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        throw new Exception("User not found.");
    }
    $stmt->closeCursor();
} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    echo "<h3 style='background-color:#ff784a;padding:10px'>Error fetching profile: " . htmlspecialchars($e->getMessage()) . "</h3>";
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    
    // Handle password update
    $password = $row['password']; // Default to existing password
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    // Handle profile photo upload using BLOB storage
    $profilePhotoBlob = $row['profile_photo_blob'] ?? null;
    $profilePhotoType = $row['profile_photo_type'] ?? null;

    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $fileExt = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));

        if (in_array($fileExt, $allowedTypes)) {
            // Check file size (limit to 2MB)
            if ($_FILES['profile_photo']['size'] <= 2 * 1024 * 1024) {
                $profilePhotoBlob = file_get_contents($_FILES['profile_photo']['tmp_name']);
                $profilePhotoType = $fileExt;
                $message = "<h3 style='background-color:#60964c;padding:10px'>Profile photo updated successfully</h3>";
            } else {
                $message = "<h3 style='background-color:#ff784a;padding:10px'>File is too large. Maximum size is 2MB.</h3>";
            }
        } else {
            $message = "<h3 style='background-color:#ff784a;padding:10px'>Invalid image type. Only JPG, PNG, and GIF are allowed.</h3>";
        }
    }

    // Update user data using PDO
    try {
        // First, check if BLOB columns exist
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo_blob'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE users ADD COLUMN profile_photo_blob LONGBLOB");
                $conn->exec("ALTER TABLE users ADD COLUMN profile_photo_type VARCHAR(10)");
            }
        } catch (PDOException $e) {
            error_log("Note: " . $e->getMessage());
        }
        
        // Update query based on your actual table columns
        $stmt_update = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, profile_photo_blob = ?, profile_photo_type = ? WHERE username = ?");
        
        if ($stmt_update->execute([$name, $email, $password, $profilePhotoBlob, $profilePhotoType, $username])) {
            $message = "<h3 style='background-color:#60964c;padding:10px'>Profile updated successfully</h3>";
            // Update session variables
            $_SESSION['full_name'] = $name;
            $_SESSION['email'] = $email;
        } else {
            throw new Exception("Update failed");
        }
        $stmt_update->closeCursor();
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        $message = "<h3 style='background-color:#ff784a;padding:10px'>Error updating profile: " . htmlspecialchars($e->getMessage()) . "</h3>";
    }
}

// Helper function to get profile image
function getProfileImage($row) {
    if (!empty($row['profile_photo_blob'])) {
        return 'data:image/' . $row['profile_photo_type'] . ';base64,' . base64_encode($row['profile_photo_blob']);
    }
    return 'default.jpg';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OVMS | Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob Witty">
    <link rel="icon" href="logo.jpg" type="image/x-icon">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: #f4f4f4; 
        }
        
        .navbar { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            padding: 15px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar .title h1 { 
            margin: 0; 
            font-size: 1.5rem; 
        }
        
        .navbar .links { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 10px;
        }
        
        .navbar a { 
            color: white; 
            text-decoration: none; 
            padding: 8px 15px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .navbar a:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        .profile-container {
            width: 90%;
            max-width: 600px;
            margin: 40px auto;
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .profile-container h1 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .profile-image {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .profile-container img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-form {
            display: flex;
            flex-direction: column;
        }

        .profile-form label {
            margin-top: 15px;
            font-weight: 600;
            color: #333;
        }

        .profile-form input[type="text"],
        .profile-form input[type="email"],
        .profile-form input[type="password"],
        .profile-form input[type="file"] {
            padding: 12px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .profile-form input[type="text"]:focus,
        .profile-form input[type="email"]:focus,
        .profile-form input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .profile-form input[readonly] {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }

        .profile-form input[type="submit"] {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 25px;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .profile-form input[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        footer { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            text-align: center; 
            padding: 20px; 
            margin-top: 40px; 
        }
        
        footer ul { 
            list-style: none; 
            padding: 0; 
            margin: 0 0 10px 0;
        }
        
        footer li { 
            display: inline; 
            margin: 0 15px; 
        }
        
        footer a { 
            color: white; 
            text-decoration: none; 
        }
        
        footer a:hover {
            text-decoration: underline;
        }
        
        .message {
            margin-bottom: 20px;
            border-radius: 6px;
        }
        
        @media (max-width: 768px) { 
            .navbar {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            .profile-container { 
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="navbar">
            <div class="title">
                <h1>Online Voting Management System</h1>
            </div>
            <div class="links">
                <a href="index.php">All Votes</a>
                <a href="vote.php">Vote</a>
                <a href="apply.php" class="active">Contest</a>
                <a href="contest.php">Contesters</a>
                <a href="members.php">Reg. Voters</a>
                <a href="my_applications.php">My applications</a>
                <a href="profile.php">My Profile</a>
                <a href="logout.php">Log out</a>
            </div>
        </div>
    </header>

    <div class="profile-container">
        <h1>Update Profile</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="profile-image">
            <img src="<?php echo getProfileImage($row); ?>" alt="Profile Photo">
        </div>
        
        <form method="post" action="profile.php" class="profile-form" enctype="multipart/form-data">
            <label for="profile_photo">Profile Photo:</label>
            <input type="file" name="profile_photo" id="profile_photo" accept="image/*">
            <small style="color: #666; margin-top: 5px;">Accepted formats: JPG, PNG, GIF (Max 2MB)</small>
            
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" readonly>
            
            <label for="name">Full Name:</label>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($row["name"] ?? ''); ?>" required>
            
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($row["email"] ?? ''); ?>" required>
            
            <label for="password">New Password (leave blank to keep current):</label>
            <input type="password" name="password" id="password" placeholder="Enter new password if you want to change it">
            
            <input type="submit" value="Update Profile">
        </form>
    </div>

    <footer>
        <div>
            <h3>Faster links</h3>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="vote.php">Vote</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Log out</a></li>
            </ul>
        </div>
        <div style="text-align: center; padding: 10px; background-color: rgba(0,0,0,0.2); font-size: 0.8em; margin-top: 10px;">
            &copy; <?php echo date("Y"); ?> Jacob witty. All rights reserved.
        </div>
    </footer>
</body>
</html>

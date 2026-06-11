<?php
session_start();
include("conn.php");

// Redirect if not logged in
if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}
$message= "";
$username = $_SESSION["username"];

// Fetch user data using prepared statement
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
    } else {
        throw new Exception("User not found.");
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    echo "<h3 style='background-color:#ff784a;padding:10px'>Error fetching profile: " . htmlspecialchars($e->getMessage()) . "</h3>";
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $row['password_hash']; // Use password_hash

    // Handle profile photo upload
    $profile_photo = $row['profile_photo']; // Default to existing photo

    if (!empty($_FILES['profile_photo']['name'])) {
        $fname = time() . '_' . basename($_FILES['profile_photo']['name']);
        $target_file = 'uploads/' . $fname;

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
            $profile_photo = $fname;
        } else {
            echo "<h3 style='background-color:#ff784a;padding:10px'>Error uploading profile photo.</h3>";
        }
    }

    // Update user data using prepared statement
    try {
        $stmt_update = $conn->prepare("UPDATE users SET full_name = ?, email = ?, password_hash = ?, profile_photo = ? WHERE username = ?");
        if (!$stmt_update) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt_update->bind_param("sssss", $name, $email, $password, $profile_photo, $username);

        if ($stmt_update->execute()) {
            echo "<h3 style='background-color:#60964c;padding:10px'>Profile updated successfully</h3>";
        } else {
            throw new Exception("Update failed: " . $stmt_update->error);
        }
        $stmt_update->close();
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        echo "<h3 style='background-color:#ff784a;padding:10px'>Error updating profile: " . htmlspecialchars($e->getMessage()) . "</h3>";
    }
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
         <div id="message-display"><?php echo $message; ?></div>
        <img src="<?php echo $row['profile_photo'] ? '' . htmlspecialchars($row['profile_photo']) : 'default.jpg'; ?>" alt="Profile Photo">
        <form method="post" action="profile.php" class="profile-form" enctype="multipart/form-data">
            <input type="file" name="profile_photo">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" readonly>
            <label for="name">Full Name:</label>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($row["full_name"]); ?>" required>
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($row["email"]); ?>" required>
            <label for="password">New Password (leave blank to keep current):</label>
            <input type="password" name="password" id="password">
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
<style>
  
 body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .votes-container { width: 80%; max-width: 1200px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .votes-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .votes-table th, .votes-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .votes-table th { background-color: #f2f2f2; }
        .navbar { background-color: #3498db; color: white; padding: 10px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin: 0 10px; }
        footer { background-color: #3498db; color: white; text-align: center; padding: 20px; margin-top: 20px; }
        footer ul { list-style: none; padding: 0; }
        footer li { display: inline; margin: 0 10px; }
        footer a { color: white; text-decoration: none; }
        .profile-container {
    width: 80%;
    max-width: 600px;
    margin: 20px auto;
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.profile-container h1 {
    color: #333;
    margin-bottom: 20px;
    text-align: center;
}

.profile-container img {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
    margin: 0 auto 20px;
}

.profile-form {
    display: flex;
    flex-direction: column;
}

.profile-form label {
    margin-top: 10px;
    font-weight: bold;
}

.profile-form input[type="text"],
.profile-form input[type="email"],
.profile-form input[type="password"],
.profile-form input[type="file"] {
    padding: 10px;
    margin-top: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.profile-form input[type="submit"] {
    background-color: #2ecc71;;
    color: white;
    padding: 10px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 20px;
}

.profile-form input[type="submit"]:hover {
    background-color: #2ecc71;
}
        @media (max-width: 768px) { .votes-container { width: 95%; } }

    </style>
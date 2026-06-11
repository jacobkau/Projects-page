<?php
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}
$message = "";
// Fetch Elections
$electionsStmt = $conn->prepare("SELECT id, title FROM elections WHERE voting_open = 1");
$electionsStmt->execute();
$electionsResult = $electionsStmt->get_result();
$electionsStmt->close();

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $electionId = isset($_POST['election_id']) ? intval($_POST['election_id']) : null;
    $postName = isset($_POST['postname']) ? $_POST['postname'] : null;
    $name = $_SESSION["username"];
    $bio = isset($_POST['bio']) ? $_POST['bio'] : "";

    // Handle profile photo upload
    $profile_photo = ""; // Initialize profile photo path

    // Check if a file was uploaded
    if (empty($_FILES['profile_photo']['name'])) {
        echo "<p style='color:red;'>Please upload a profile photo.</p>";
    } else {
        $uploadDir = 'faces/';
        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0755, true);
        }
        $fname = time() . '_' . basename($_FILES['profile_photo']['name']);
        $target_file = $uploadDir . $fname;

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
            $profile_photo = $fname;
        } else {
            echo "<p style='color:red;'>Error uploading profile photo.</p>";
        }

        // Validate inputs
        if ($electionId === null || $postName === null) {
            echo "<p style='color:red;'>Please select an election and a post.</p>";
        } else {
            // Check if user has already applied for the same post in the same election
            $checkStmt = $conn->prepare("SELECT 1 FROM contesters WHERE election_id = ? AND postname = ? AND name = ?");
            $checkStmt->bind_param("iss", $electionId, $postName, $name);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                echo "<p style='color:red;'>You have already applied for this post in this election.</p>";
            } else {
                $checkStmt->close();

                // Insert data into contesters table
                if (!empty($profile_photo)) {
                    $insertStmt = $conn->prepare("INSERT INTO contesters (election_id, postname, name, bio, profile_photo) VALUES (?, ?, ?, ?, ?)");
                    $insertStmt->bind_param("issss", $electionId, $postName, $name, $bio, $profile_photo);

                    if ($insertStmt->execute()) {
                        echo "<p style='color:green;'>Application submitted successfully!</p>";
                    } else {
                        echo "<p style='color:red;'>Error: " . $insertStmt->error . "</p>";
                    }
                    $insertStmt->close();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OVMS | Apply</title>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
    <link rel="icon" href="logo.jpg" type="image/x-icon">
    <style>
        /* (Your existing styles) */
        body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .apply-container { width: 80%; max-width: 600px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        select, textarea, input[type="submit"], input[type="file"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        input[type="submit"] { background-color: #2ecc71; color: white; cursor: pointer; }
        input[type="submit"]:hover { background-color: #27ae60; }
        h2 { color: #333; margin-bottom: 10px; }
        @media (max-width: 768px) { .apply-container { width: 95%; } }
        .navbar { background-color: #3498db; color: white; padding: 10px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin: 0 10px; }
        footer { background-color: #3498db; color: white; text-align: center; padding: 20px; margin-top: 20px; }
        footer ul { list-style: none; padding: 0; }
        footer li { display: inline; margin: 0 10px; }
        footer a { color: white; text-decoration: none; }
    </style>
    <script>
       function fetchPosts(electionId) {
    console.log("Fetching posts for election ID: " + electionId); // Log the election ID

    if (electionId === "") {
        document.getElementById('postname').innerHTML = '<option value="">Select Election First</option>';
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) {
            console.log("Response status: " + xhr.status); // Log the response status
            console.log("Response text: " + xhr.responseText); // Log the response text

            if (xhr.status == 200) {
                document.getElementById('postname').innerHTML = xhr.responseText;
            }
        }
    };
    xhr.open('GET', 'get_posts.php?election_id=' + electionId, true);
    xhr.send();
}
    </script>
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

    <div class="apply-container">
        <h2>Apply for a Post</h2>
        <form method="post" enctype="multipart/form-data">
        <div id="message-display"><?php echo $message; ?></div>
        <label for="profile_photo">Profile Photo:</label>
            <input type="file" name="profile_photo" id="profile_photo" required>
            <label for="election_id">Election:</label>
            <select name="election_id" id="election_id" onchange="fetchPosts(this.value);">
                <option value="">Select Election</option>
                <?php while ($row = $electionsResult->fetch_assoc()): ?>
                    <option value="<?php echo $row['id']; ?>"><?php echo $row['title']; ?></option>
                <?php endwhile; ?>
            </select>

            <label for="postname">Post Name:</label>
            <select name="postname" id="postname">
                <option value="">Select Election First</option>
            </select>

            <label for="bio">Bio and Manifesto:</label>
            <textarea name="bio" id="bio" rows="4"></textarea>
            

            <input type="submit" value="Apply">
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
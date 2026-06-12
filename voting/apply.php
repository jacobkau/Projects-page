<?php
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$username = $_SESSION["username"];

// Get user ID
$userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$userStmt->execute([$username]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$userId = $user ? $user['id'] : null;

// Fetch Elections - Fix: Use status instead of voting_open
$electionsStmt = $conn->prepare("SELECT id, title FROM elections WHERE status = 'active'");
$electionsStmt->execute();
$elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $electionId = isset($_POST['election_id']) ? intval($_POST['election_id']) : null;
    $postName = isset($_POST['postname']) ? $_POST['postname'] : null;
    $bio = isset($_POST['bio']) ? $_POST['bio'] : "";
    $profile_photo = "";

    // Validate inputs
    if ($electionId === null || $postName === null) {
        $message = "<p style='color:red;'>Please select an election and a post.</p>";
    } elseif (empty($_FILES['profile_photo']['name'])) {
        $message = "<p style='color:red;'>Please upload a profile photo.</p>";
    } else {
        // Handle profile photo upload
        $uploadDir = 'faces/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $fileExt = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, $allowedTypes)) {
            $message = "<p style='color:red;'>Invalid file type. Only JPG, PNG, and GIF are allowed.</p>";
        } elseif ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
            $message = "<p style='color:red;'>File is too large. Maximum size is 2MB.</p>";
        } else {
            $fname = time() . '_' . uniqid() . '.' . $fileExt;
            $target_file = $uploadDir . $fname;

            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                $profile_photo = $fname;
                
                // Check if user has already applied for the same post in the same election
                $checkStmt = $conn->prepare("SELECT 1 FROM contesters WHERE election_id = ? AND postname = ? AND user_id = ?");
                $checkStmt->execute([$electionId, $postName, $userId]);
                
                if ($checkStmt->rowCount() > 0) {
                    $message = "<p style='color:red;'>You have already applied for this post in this election.</p>";
                } else {
                    // Insert data into contesters table
                    $insertStmt = $conn->prepare("INSERT INTO contesters (election_id, postname, user_id, name, bio, profile_photo, votes) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    
                    if ($insertStmt->execute([$electionId, $postName, $userId, $username, $bio, $profile_photo])) {
                        $message = "<p style='color:green;'>✓ Application submitted successfully!</p>";
                    } else {
                        $message = "<p style='color:red;'>Error submitting application. Please try again.</p>";
                    }
                }
            } else {
                $message = "<p style='color:red;'>Error uploading profile photo.</p>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OVMS | Apply for Candidacy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
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
        
        .apply-container { 
            width: 90%;
            max-width: 600px; 
            margin: 40px auto; 
            background-color: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
        }
        
        .apply-container h2 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
        
        select, textarea, input[type="submit"], input[type="file"] { 
            width: 100%; 
            padding: 12px; 
            margin: 10px 0; 
            border: 1px solid #ddd; 
            border-radius: 6px; 
            box-sizing: border-box;
            font-size: 14px;
        }
        
        select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            resize: vertical;
            font-family: inherit;
        }
        
        input[type="submit"] { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            border: none;
            transition: transform 0.2s;
        }
        
        input[type="submit"]:hover { 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        label {
            font-weight: 600;
            color: #333;
            margin-top: 10px;
            display: block;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #28a745;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #dc3545;
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
        
        @media (max-width: 768px) { 
            .navbar {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            .apply-container { 
                width: 95%;
                padding: 20px;
            }
        }
    </style>
    <script>
    function fetchPosts(electionId) {
        console.log("Fetching posts for election ID: " + electionId);
        
        if (electionId === "") {
            document.getElementById('postname').innerHTML = '<option value="">Select Election First</option>';
            return;
        }
        
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4) {
                console.log("Response status: " + xhr.status);
                console.log("Response text: " + xhr.responseText);
                
                if (xhr.status == 200) {
                    document.getElementById('postname').innerHTML = xhr.responseText;
                } else {
                    document.getElementById('postname').innerHTML = '<option value="">Error loading posts</option>';
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
        <h2>🗳️ Apply for Candidacy</h2>
        
        <?php if ($message): ?>
            <div class="<?php echo strpos($message, 'green') !== false ? 'success-message' : 'error-message'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <label for="profile_photo">Profile Photo *:</label>
            <input type="file" name="profile_photo" id="profile_photo" accept="image/*" required>
            <small style="color: #666;">Accepted formats: JPG, PNG, GIF (Max 2MB)</small>
            
            <label for="election_id">Select Election *:</label>
            <select name="election_id" id="election_id" onchange="fetchPosts(this.value);" required>
                <option value="">-- Select Election --</option>
                <?php foreach ($elections as $election): ?>
                    <option value="<?php echo $election['id']; ?>">
                        <?php echo htmlspecialchars($election['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="postname">Select Position *:</label>
            <select name="postname" id="postname" required>
                <option value="">Select Election First</option>
            </select>

            <label for="bio">Bio and Manifesto:</label>
            <textarea name="bio" id="bio" rows="5" placeholder="Tell voters about yourself, your qualifications, and your goals..."></textarea>
            
            <input type="submit" value="Submit Application">
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
        <div>
            &copy; <?php echo date("Y"); ?> Jacob witty. All rights reserved.
        </div>
    </footer>
</body>
</html>

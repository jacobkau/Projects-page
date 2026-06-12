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

// Make sure contesters table has BLOB columns for profile photos
try {
    $checkColumns = $conn->query("SHOW COLUMNS FROM contesters LIKE 'profile_photo_blob'");
    if ($checkColumns->rowCount() == 0) {
    $conn->exec("ALTER TABLE contesters ADD COLUMN profile_photo_blob LONGBLOB");
    $conn->exec("ALTER TABLE contesters ADD COLUMN profile_photo_type VARCHAR(10)");
    $conn->exec("ALTER TABLE contesters ADD COLUMN profile_photo_name VARCHAR(255)");
    error_log("Added BLOB columns to contesters table");
    }
} catch (PDOException $e) {
    error_log("Note: " . $e->getMessage());
}

// Fetch Elections
$electionsStmt = $conn->prepare("SELECT id, title FROM elections WHERE status = 'active'");
$electionsStmt->execute();
$elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $electionId = isset($_POST['election_id']) ? intval($_POST['election_id']) : null;
    $postName = isset($_POST['postname']) ? $_POST['postname'] : null;
    $bio = isset($_POST['bio']) ? $_POST['bio'] : "";
    
    // Validate inputs
    if ($electionId === null || $postName === null) {
        $message = "<p style='color:red;'>Please select an election and a post.</p>";
    } elseif (empty($_FILES['profile_photo']['name'])) {
        $message = "<p style='color:red;'>Please upload a profile photo.</p>";
    } else {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $fileExt = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, $allowedTypes)) {
            $message = "<p style='color:red;'>Invalid file type. Only JPG, PNG, and GIF are allowed.</p>";
        } elseif ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
            $message = "<p style='color:red;'>File is too large. Maximum size is 2MB.</p>";
        } else {
            // Read file content for database storage
            $profilePhotoBlob = file_get_contents($_FILES['profile_photo']['tmp_name']);
            $profilePhotoType = $fileExt;
            $profilePhotoName = $_FILES['profile_photo']['name'];
            
            // Check if user has already applied for the same post in the same election
            $checkStmt = $conn->prepare("SELECT 1 FROM contesters WHERE election_id = ? AND postname = ? AND user_id = ?");
            $checkStmt->execute([$electionId, $postName, $userId]);
            
            if ($checkStmt->rowCount() > 0) {
                $message = "<p style='color:red;'>You have already applied for this post in this election.</p>";
            } else {
                // Insert data into contesters table with BLOB image
                $insertStmt = $conn->prepare("INSERT INTO contesters (election_id, postname, user_id, name, bio, profile_photo_blob, profile_photo_type, profile_photo_name, votes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
                
                if ($insertStmt->execute([$electionId, $postName, $userId, $username, $bio, $profilePhotoBlob, $profilePhotoType, $profilePhotoName])) {
                    $message = "<p style='color:green;'>✓ Application submitted successfully!</p>";
                } else {
                    $message = "<p style='color:red;'>Error submitting application. Please try again.</p>";
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
    <title>OVMS | Apply for Candidacy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.jpg" type="image/x-icon">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 0; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .navbar { 
            background: rgba(0,0,0,0.2);
            color: white; 
            padding: 15px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap;
            backdrop-filter: blur(10px);
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
        
        .navbar a:hover,
        .navbar a.active { 
            background-color: rgba(255,255,255,0.2);
        }
        
        .apply-container { 
            width: 90%;
            max-width: 600px; 
            margin: 40px auto; 
            background-color: white; 
            padding: 30px; 
            border-radius: 16px; 
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); 
        }
        
        .apply-container h2 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
            font-size: 28px;
        }
        
        .apply-container .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        .required:after {
            content: " *";
            color: red;
        }
        
        select, textarea, input[type="file"] { 
            width: 100%; 
            padding: 12px; 
            border: 2px solid #e0e0e0; 
            border-radius: 8px; 
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
            box-sizing: border-box;
        }
        
        select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        input[type="file"] {
            padding: 10px;
            background: #f9f9f9;
        }
        
        .file-hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .submit-btn { 
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            padding: 14px;
            border: none; 
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }
        
        .submit-btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .info-box {
            background-color: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            color: #0056b3;
            border-left: 4px solid #2196f3;
        }
        
        .info-box h4 {
            margin-bottom: 8px;
        }
        
        .info-box ul {
            margin-left: 20px;
            margin-top: 5px;
        }
        
        .info-box li {
            margin: 5px 0;
        }
        
        footer { 
            background: rgba(0,0,0,0.2);
            color: white; 
            text-align: center; 
            padding: 20px; 
            margin-top: 40px;
            backdrop-filter: blur(10px);
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
                margin: 20px;
                padding: 20px;
            }
            .apply-container h2 {
                font-size: 24px;
            }
        }
    </style>
    <script>
    function fetchPosts(electionId) {
        if (electionId === "") {
            document.getElementById('postname').innerHTML = '<option value="">Select Election First</option>';
            return;
        }
        
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                document.getElementById('postname').innerHTML = xhr.responseText;
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
        <div class="subtitle">Register as a candidate for an election position</div>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'green') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h4>📋 Important Information:</h4>
            <ul>
                <li>You must be registered for an election before applying</li>
                <li>Each candidate needs a profile photo (JPG, PNG, GIF, max 2MB)</li>
                <li>Provide a clear bio and manifesto for voters</li>
                <li>You can only apply for one position per election</li>
            </ul>
        </div>
        
        <form method="post" enctype="multipart/form-data" id="applicationForm">
            <div class="form-group">
                <label for="profile_photo" class="required">Profile Photo:</label>
                <input type="file" name="profile_photo" id="profile_photo" accept="image/*" required>
                <div class="file-hint">Accepted formats: JPG, PNG, GIF (Max 2MB). Your photo will be stored securely in the database.</div>
            </div>
            
            <div class="form-group">
                <label for="election_id" class="required">Select Election:</label>
                <select name="election_id" id="election_id" onchange="fetchPosts(this.value);" required>
                    <option value="">-- Select Election --</option>
                    <?php foreach ($elections as $election): ?>
                        <option value="<?php echo $election['id']; ?>">
                            <?php echo htmlspecialchars($election['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="postname" class="required">Select Position:</label>
                <select name="postname" id="postname" required>
                    <option value="">Select Election First</option>
                </select>
            </div>

            <div class="form-group">
                <label for="bio">Bio and Manifesto:</label>
                <textarea name="bio" id="bio" rows="5" placeholder="Tell voters about yourself, your qualifications, experience, and goals if elected..."></textarea>
                <div class="file-hint">Be clear and convincing. This will be visible to all voters.</div>
            </div>
            
            <button type="submit" class="submit-btn" id="submitBtn">
                Submit Application
            </button>
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

    <script>
    // File validation
    document.getElementById('profile_photo').addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                alert('Invalid file type. Only JPG, PNG, and GIF are allowed.');
                this.value = '';
            } else if (file.size > 2 * 1024 * 1024) {
                alert('File is too large. Maximum size is 2MB.');
                this.value = '';
            }
        }
    });
    </script>
</body>
</html>

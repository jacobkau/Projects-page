<?php
// vote.php (Full PHP Code - PDO Version)
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// Get user ID from username
$userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$userStmt->execute([$_SESSION["username"]]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$userId = $user ? $user['id'] : null;

// Fetch Open Elections (Fix: add quotes around 'active')
$openElectionsStmt = $conn->prepare("SELECT id, title FROM elections WHERE status = 'active'");
$openElectionsStmt->execute();
$openElections = $openElectionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get Election ID from URL or Session (if any)
$electionId = isset($_GET['election_id']) ? intval($_GET['election_id']) : (isset($_SESSION['selected_election']) ? $_SESSION['selected_election'] : null);

if (isset($_GET['election_id'])) {
    $_SESSION['selected_election'] = $electionId;
}

$showVoteForm = false;
$noPostsMessage = "";
$errorMessage = "";
$successMessage = "";

if ($electionId !== null) {
    // Check if Election Exists
    $electionExistsStmt = $conn->prepare("SELECT 1 FROM elections WHERE id = ?");
    $electionExistsStmt->execute([$electionId]);
    
    if ($electionExistsStmt->rowCount() === 0) {
        $errorMessage = "<p style='color:red;'>The selected election does not exist.</p>";
    } else {
        // Check if Voting is Open (using status column)
        $votingOpenStmt = $conn->prepare("SELECT status FROM elections WHERE id = ?");
        $votingOpenStmt->execute([$electionId]);
        $electionStatus = $votingOpenStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($electionStatus['status'] !== 'active') {
            $errorMessage = "<p style='color:red;'>Voting is currently closed for this election. Election status: " . $electionStatus['status'] . "</p>";
        } else {
            // Check if User is Registered for this election
            $userRegisteredStmt = $conn->prepare("SELECT 1 FROM user_elections WHERE user_id = ? AND election_id = ?");
            $userRegisteredStmt->execute([$userId, $electionId]);
            
            if ($userRegisteredStmt->rowCount() === 0) {
                $errorMessage = "<p style='color:red;'>You are not registered for this election.</p>";
            } else {
                // Check if User Already Voted (using new votes table structure)
                $alreadyVotedStmt = $conn->prepare("SELECT 1 FROM votes WHERE user_id = ? AND election_id = ?");
                $alreadyVotedStmt->execute([$userId, $electionId]);
                
                if ($alreadyVotedStmt->rowCount() > 0) {
                    $errorMessage = "<p style='color:red;'>You have already voted in this election.</p>";
                } else {
                    // Check if there are any posts/candidates for the election
                    $postCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM contesters WHERE election_id = ?");
                    $postCheckStmt->execute([$electionId]);
                    $postCount = $postCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($postCount['count'] === 0) {
                        $noPostsMessage = "<p style='color:red;'>There are no candidates available for this election.</p>";
                    } else {
                        $showVoteForm = true;
                    }
                }
            }
        }
    }
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit']) && $showVoteForm) {
    $votes = $_POST;
    unset($votes['submit']);
    
    // Get all posts for this election
    $postStmt = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ?");
    $postStmt->execute([$electionId]);
    $posts = $postStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $allPostsVoted = true;
    foreach ($posts as $post) {
        $postName = strtolower(str_replace(' ', '_', $post['postname']));
        if (!isset($votes[$postName]) || empty($votes[$postName])) {
            $allPostsVoted = false;
            $errorMessage = "<p style='color:red;'>Please vote for all available positions: " . htmlspecialchars($post['postname']) . "</p>";
            break;
        }
    }
    
    if ($allPostsVoted) {
        $voteSuccess = true;
        
        foreach ($votes as $postNameKey => $candidateName) {
            $originalPostName = str_replace('_', ' ', $postNameKey);
            
            // Insert vote into votes table
            $voteStmt = $conn->prepare("INSERT INTO votes (user_id, election_id, postname, candidate_name) VALUES (?, ?, ?, ?)");
            if ($voteStmt->execute([$userId, $electionId, $originalPostName, $candidateName])) {
                // Update vote count in contesters table
                $updateStmt = $conn->prepare("UPDATE contesters SET votes = votes + 1 WHERE name = ? AND election_id = ? AND postname = ?");
                if (!$updateStmt->execute([$candidateName, $electionId, $originalPostName])) {
                    $voteSuccess = false;
                    $errorMessage = "<p style='color:red;'>Error updating vote counts.</p>";
                    break;
                }
            } else {
                $voteSuccess = false;
                $errorMessage = "<p style='color:red;'>Error submitting your vote. Please try again.</p>";
                break;
            }
        }
        
        if ($voteSuccess) {
            $successMessage = "<p style='color:green;'>✓ Vote submitted successfully! Thank you for voting.</p>";
            $showVoteForm = false; // Hide form after successful vote
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OVMS | Vote</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        
        .vote-container {
            width: 90%;
            max-width: 700px;
            margin: 40px auto;
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .vote-container h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        select, input[type="submit"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
        }
        
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .vote-form label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        input[type="submit"] {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            border: none;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        
        input[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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
            padding: 20px 0;
            text-align: center;
            color: white;
            margin-top: 40px;
        }
        
        footer ul {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            list-style: none;
            padding: 0;
            margin: 0 0 10px 0;
        }
        
        footer li {
            margin: 0 15px;
        }
        
        footer a {
            text-decoration: none;
            color: white;
            font-size: 1em;
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
            .vote-container {
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
                <a href="apply.php">Contest</a>
                <a href="contest.php">Contesters</a>
                <a href="members.php">Reg. Voters</a>
                <a href="my_applications.php">My applications</a>
                <a href="profile.php">My Profile</a>
                <a href="logout.php">Log out</a>
            </div>
        </div>
    </header>

    <div class="vote-container">
        <h2>🗳️ Cast Your Vote</h2>
        
        <label for="electionSelect">Select an Election:</label>
        <select id="electionSelect" onchange="window.location.href='vote.php?election_id=' + this.value;">
            <option value="">-- Select Election --</option>
            <?php foreach ($openElections as $election): ?>
                <option value="<?php echo $election['id']; ?>" <?php echo ($electionId == $election['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($election['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-message"><?php echo $errorMessage; ?></div>
        <?php endif; ?>

        <?php if (!empty($noPostsMessage)): ?>
            <div class="error-message"><?php echo $noPostsMessage; ?></div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="success-message"><?php echo $successMessage; ?></div>
        <?php endif; ?>

        <?php if ($showVoteForm && $electionId): ?>
            <form method="post" action="vote.php?election_id=<?php echo $electionId; ?>" class="vote-form">
                <h3 style="color: #667eea; text-align: center;">Election ID: <?php echo $electionId; ?></h3>
                <p style="text-align: center; color: #666;">Please select your preferred candidate for each position</p>
                
                <?php
                // Fetch Posts and Candidates Dynamically
                $postStmt = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ? ORDER BY postname");
                $postStmt->execute([$electionId]);
                $posts = $postStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($posts) > 0) {
                    foreach ($posts as $post) {
                        $postName = $post['postname'];
                        $postKey = strtolower(str_replace(' ', '_', $postName));
                        
                        $candidateStmt = $conn->prepare("SELECT name FROM contesters WHERE postname = ? AND election_id = ? ORDER BY name");
                        $candidateStmt->execute([$postName, $electionId]);
                        $candidates = $candidateStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo "<label for='$postKey'><strong>" . htmlspecialchars($postName) . "</strong></label>";
                        echo "<select name='$postKey' id='$postKey' required>";
                        echo "<option value=''>-- Select Candidate --</option>";
                        foreach ($candidates as $candidate) {
                            echo "<option value='" . htmlspecialchars($candidate['name']) . "'>" . htmlspecialchars($candidate['name']) . "</option>";
                        }
                        echo "</select>";
                    }
                } else {
                    echo "<p style='color:red;'>No candidates available for this election.</p>";
                }
                ?>
                
                <input type="submit" name="submit" value="Submit Vote">
            </form>
        <?php endif; ?>
        
        <?php if (!$electionId && empty($openElections)): ?>
            <div class="error-message">No active elections available at this time.</div>
        <?php endif; ?>
    </div>
    
    <footer>
        <div>
            <h3>Faster Links</h3>
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

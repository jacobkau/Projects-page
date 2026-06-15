<?php
// vote.php - Final working version
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION["username"];

// Get user ID
$userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$userStmt->execute([$username]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$userId = $user ? $user['id'] : null;

// Fetch Open Elections
$openElectionsStmt = $conn->prepare("SELECT id, title FROM elections WHERE status = 'active'");
$openElectionsStmt->execute();
$openElections = $openElectionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get Election ID from URL
$electionId = isset($_GET['election_id']) ? intval($_GET['election_id']) : (isset($_SESSION['selected_election']) ? $_SESSION['selected_election'] : null);

if (isset($_GET['election_id'])) {
    $_SESSION['selected_election'] = $electionId;
}

$showVoteForm = false;
$errorMessage = "";
$successMessage = "";
$election = null;

if ($electionId !== null) {
    // Check if Election Exists and is Active
    $electionCheckStmt = $conn->prepare("SELECT id, title, status FROM elections WHERE id = ?");
    $electionCheckStmt->execute([$electionId]);
    $election = $electionCheckStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$election) {
        $errorMessage = "The selected election does not exist.";
    } elseif ($election['status'] !== 'active') {
        $errorMessage = "Voting is currently closed for this election. Status: " . $election['status'];
    } else {
        // Check if User is Registered for this election
        $userRegisteredStmt = $conn->prepare("SELECT 1 FROM user_elections WHERE user_id = ? AND election_id = ?");
        $userRegisteredStmt->execute([$userId, $electionId]);
        
        if ($userRegisteredStmt->rowCount() === 0) {
            $errorMessage = "You are not registered for this election. Please register first.";
        } else {
            // Check if User Already Voted
            $alreadyVotedStmt = $conn->prepare("SELECT 1 FROM votes WHERE username = ? AND election_id = ?");
            $alreadyVotedStmt->execute([$username, $electionId]);
            
            if ($alreadyVotedStmt->rowCount() > 0) {
                $errorMessage = "You have already voted in this election.";
            } else {
                // Get posts for this election from election_posts table
                $postsStmt = $conn->prepare("SELECT id, postname FROM election_posts WHERE election_id = ? ORDER BY postname");
                $postsStmt->execute([$electionId]);
                $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($posts)) {
                    $errorMessage = "No positions have been set up for this election yet. Please contact the administrator.";
                } else {
                    $showVoteForm = true;
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
    $postsStmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ?");
    $postsStmt->execute([$electionId]);
    $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Validate all posts were voted for
    $allPostsVoted = true;
    $missingPosts = [];
    
    foreach ($posts as $post) {
        $postKey = strtolower(str_replace(' ', '_', $post['postname']));
        if (!isset($votes[$postKey]) || empty($votes[$postKey])) {
            $allPostsVoted = false;
            $missingPosts[] = $post['postname'];
        }
    }
    
    if (!$allPostsVoted) {
        $errorMessage = "Please vote for all positions: " . implode(', ', $missingPosts);
    } else {
        $voteSuccess = true;
        $conn->beginTransaction();
        
        try {
            foreach ($votes as $postKey => $candidateName) {
                $originalPostName = str_replace('_', ' ', $postKey);
                
                // Insert vote into votes table
                $voteStmt = $conn->prepare("INSERT INTO votes (username, election_id, postname, candidate_name, voted_at) VALUES (?, ?, ?, ?, NOW())");
                if (!$voteStmt->execute([$username, $electionId, $originalPostName, $candidateName])) {
                    throw new Exception("Failed to insert vote");
                }
                
                // Update vote count in contesters table
                $updateStmt = $conn->prepare("UPDATE contesters SET votes = votes + 1 WHERE name = ? AND election_id = ? AND postname = ?");
                $updateStmt->execute([$candidateName, $electionId, $originalPostName]);
            }
            
            $conn->commit();
            $successMessage = "✓ Vote submitted successfully! Thank you for voting.";
            $showVoteForm = false;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errorMessage = "Error submitting your vote. Please try again.";
            error_log("Vote error: " . $e->getMessage());
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .navbar { background: rgba(0,0,0,0.2); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; backdrop-filter: blur(10px); }
        .navbar .title h1 { margin: 0; font-size: 1.5rem; }
        .navbar .links { display: flex; flex-wrap: wrap; gap: 10px; }
        .navbar a { color: white; text-decoration: none; padding: 8px 15px; border-radius: 5px; transition: background-color 0.3s; }
        .navbar a:hover, .navbar a.active { background-color: rgba(255,255,255,0.2); }
        .vote-container { width: 90%; max-width: 700px; margin: 40px auto; background-color: white; padding: 30px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .vote-container h2 { color: #333; margin-bottom: 10px; text-align: center; }
        .vote-container .subtitle { text-align: center; color: #666; margin-bottom: 25px; font-size: 14px; }
        label { font-weight: 600; color: #333; margin-top: 15px; margin-bottom: 5px; display: block; }
        select, input[type="submit"] { width: 100%; padding: 12px; margin: 5px 0 10px 0; border: 2px solid #e0e0e0; border-radius: 8px; box-sizing: border-box; font-size: 14px; transition: all 0.3s; }
        select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        input[type="submit"] { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; cursor: pointer; font-size: 16px; font-weight: 600; border: none; margin-top: 20px; transition: transform 0.2s, box-shadow 0.2s; }
        input[type="submit"]:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102,126,234,0.4); }
        .success-message { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #28a745; }
        .error-message { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #dc3545; }
        .info-message { background-color: #fff3cd; color: #856404; padding: 12px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ffc107; }
        footer { background: rgba(0,0,0,0.2); padding: 20px 0; text-align: center; color: white; margin-top: 40px; backdrop-filter: blur(10px); }
        footer ul { display: flex; flex-wrap: wrap; justify-content: center; list-style: none; padding: 0; margin: 0 0 10px 0; }
        footer li { margin: 0 15px; }
        footer a { text-decoration: none; color: white; }
        footer a:hover { text-decoration: underline; }
        @media (max-width: 768px) { .navbar { flex-direction: column; text-align: center; gap: 10px; } .vote-container { width: 95%; padding: 20px; } }
    </style>
</head>
<body>
    <header>
        <div class="navbar">
            <div class="title"><h1>Online Voting Management System</h1></div>
            <div class="links">
                <a href="index.php">All Votes</a>
                <a href="vote.php" class="active">Vote</a>
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
        <div class="subtitle">Select your preferred candidates for each position</div>

        <label for="electionSelect">Select an Election:</label>
        <select id="electionSelect" onchange="window.location.href='vote.php?election_id=' + this.value;">
            <option value="">-- Select Election --</option>
            <?php foreach ($openElections as $electionOption): ?>
                <option value="<?php echo $electionOption['id']; ?>" <?php echo ($electionId == $electionOption['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($electionOption['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-message"><?php echo $errorMessage; ?></div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="success-message"><?php echo $successMessage; ?></div>
        <?php endif; ?>

        <?php if ($showVoteForm && $electionId && $election): ?>
            <form method="post" action="vote.php?election_id=<?php echo $electionId; ?>" class="vote-form">
                <h3 style="color: #667eea; text-align: center; margin-bottom: 15px;"><?php echo htmlspecialchars($election['title']); ?></h3>
                
                <?php
                $postsStmt = $conn->prepare("SELECT id, postname FROM election_posts WHERE election_id = ? ORDER BY postname");
                $postsStmt->execute([$electionId]);
                $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($posts as $post):
                    $postName = $post['postname'];
                    $postKey = strtolower(str_replace(' ', '_', $postName));
                    
                    $candidateStmt = $conn->prepare("SELECT name FROM contesters WHERE postname = ? AND election_id = ? ORDER BY name");
                    $candidateStmt->execute([$postName, $electionId]);
                    $candidates = $candidateStmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <label for="<?php echo $postKey; ?>"><strong><?php echo htmlspecialchars($postName); ?></strong></label>
                    <select name="<?php echo $postKey; ?>" id="<?php echo $postKey; ?>" required>
                        <option value="">-- Select Candidate --</option>
                        <?php if (empty($candidates)): ?>
                            <option value="" disabled>No candidates available</option>
                        <?php else: ?>
                            <?php foreach ($candidates as $candidate): ?>
                                <option value="<?php echo htmlspecialchars($candidate['name']); ?>"><?php echo htmlspecialchars($candidate['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                <?php endforeach; ?>
                
                <input type="submit" name="submit" value="Submit Vote">
            </form>
        <?php endif; ?>
        
        <?php if (!$electionId && !empty($openElections)): ?>
            <div class="info-message">Please select an election from the dropdown above to cast your vote.</div>
        <?php endif; ?>
        
        <?php if (empty($openElections)): ?>
            <div class="info-message">No active elections available at this time.</div>
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
        <div>&copy; <?php echo date("Y"); ?> Jacob witty. All rights reserved.</div>
    </footer>
</body>
</html>

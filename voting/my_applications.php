<?php
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION["username"];

// Get user ID
$userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$userStmt->bind_param("s", $username);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

if ($user === null) {
    echo "<p style='color:red;'>User not found.</p>";
    exit();
}

$userId = $user['id'];

// Fetch User's Registered Elections
$registeredStmt = $conn->prepare("SELECT ue.election_id, e.title AS election_title FROM user_elections ue JOIN elections e ON ue.election_id = e.id WHERE ue.user_id = ?");
$registeredStmt->bind_param("i", $userId);
$registeredStmt->execute();
$registeredResult = $registeredStmt->get_result();
$registeredElections = $registeredResult->fetch_all(MYSQLI_ASSOC);
$registeredStmt->close();

// Fetch User's Contester Applications
$applicationsStmt = $conn->prepare("SELECT election_id, postname FROM contesters WHERE name = ?");
$applicationsStmt->bind_param("s", $username);
$applicationsStmt->execute();
$applicationsResult = $applicationsStmt->get_result();
$applications = $applicationsResult->fetch_all(MYSQLI_ASSOC);
$applicationsStmt->close();

// Merge Applications into Registered Elections (Prevent Duplicates)
$mergedElections = [];
foreach ($registeredElections as $election) {
    $electionId = $election['election_id'];
    if (!isset($mergedElections[$electionId])) {
        $mergedElections[$electionId] = $election;
        $mergedElections[$electionId]['contested_posts'] = [];
    }
}

foreach ($applications as $application) {
    $electionId = $application['election_id'];
    if (isset($mergedElections[$electionId])) {
        $mergedElections[$electionId]['contested_posts'][] = $application['postname'];
    }
}

// Convert merged elections to a simple array
$finalElections = array_values($mergedElections);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Applications and Registrations</title>
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
    <link rel="icon" href="logo.jpg" type="image/x-icon">
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .votes-container { width: 80%; max-width: 1200px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .section { border: 1px solid #ddd; padding: 10px; margin-bottom: 20px; border-radius: 4px; }
         .navbar { background-color: #3498db; color: white; padding: 10px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin: 0 10px; }
        footer { background-color: #3498db; color: white; text-align: center; padding: 20px; margin-top: 20px; }
        footer ul { list-style: none; padding: 0; }
        footer li { display: inline; margin: 0 10px; }
        footer a { color: white; text-decoration: none; }
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

     <div class="votes-container">
        <div class="section">
            <h2>Registered Elections</h2>
            <?php if (empty($finalElections)): ?>
                <p>You are not registered for any elections.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($finalElections as $election): ?>
                        <li>
                        <h3 style="color:#3498db">
                            <?php echo htmlspecialchars($election['election_title']); ?>
                            <?php if (!empty($election['contested_posts'])): ?></h3>
                                <ul>
                                    <?php foreach ($election['contested_posts'] as $post): ?>
                                        <li><h4><b>Contesting for : <span style="color:#2ecc71;"><?php echo htmlspecialchars($post); ?></span></b><h4></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
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

    
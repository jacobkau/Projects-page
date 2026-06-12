<?php
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION["username"];

try {
    // Get user ID using PDO
    $userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $userStmt->execute([$username]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<p style='color:red;'>User not found.</p>";
        exit();
    }
    
    $userId = $user['id'];
    
    // Fetch User's Registered Elections
    $registeredStmt = $conn->prepare("SELECT ue.election_id, e.title AS election_title FROM user_elections ue JOIN elections e ON ue.election_id = e.id WHERE ue.user_id = ?");
    $registeredStmt->execute([$userId]);
    $registeredElections = $registeredStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch User's Contester Applications
    $applicationsStmt = $conn->prepare("SELECT election_id, postname FROM contesters WHERE user_id = ?");
    $applicationsStmt->execute([$userId]);
    $applications = $applicationsStmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    
} catch (PDOException $e) {
    error_log("Error in my_applications.php: " . $e->getMessage());
    echo "<p style='color:red;'>Error loading your applications. Please try again later.</p>";
    $finalElections = [];
}
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
        
        .votes-container { 
            width: 90%;
            max-width: 800px; 
            margin: 40px auto; 
            background-color: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
        }
        
        .votes-container h1 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .section { 
            border: 1px solid #e0e0e0; 
            padding: 20px; 
            margin-bottom: 20px; 
            border-radius: 8px;
            background-color: #fafafa;
        }
        
        .section h2 {
            color: #667eea;
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .election-list {
            list-style: none;
            padding: 0;
        }
        
        .election-list li {
            background-color: white;
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .election-list h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .contest-posts {
            list-style: none;
            padding-left: 20px;
            margin-top: 10px;
        }
        
        .contest-posts li {
            background-color: #e8f5e9;
            border-left-color: #4caf50;
            margin-bottom: 8px;
            padding: 10px;
        }
        
        .contest-posts li span {
            color: #2e7d32;
            font-weight: bold;
        }
        
        .no-data {
            text-align: center;
            color: #999;
            padding: 40px;
            font-style: italic;
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
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: normal;
            margin-left: 10px;
        }
        
        .badge-contesting {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        @media (max-width: 768px) { 
            .navbar {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            .votes-container { 
                width: 95%;
                padding: 15px;
            }
            .election-list li {
                padding: 12px;
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
                <a href="my_applications.php" class="active">My applications</a>
                <a href="profile.php">My Profile</a>
                <a href="logout.php">Log out</a>
            </div>
        </div>
    </header>

    <div class="votes-container">
        <h1>📋 My Applications & Registrations</h1>
        
        <div class="section">
            <h2>Registered Elections</h2>
            <?php if (empty($finalElections)): ?>
                <div class="no-data">
                    <p>You are not registered for any elections.</p>
                    <p><a href="register.php" style="color: #667eea;">Register for elections here</a></p>
                </div>
            <?php else: ?>
                <ul class="election-list">
                    <?php foreach ($finalElections as $election): ?>
                        <li>
                            <h3>
                                🗳️ <?php echo htmlspecialchars($election['election_title']); ?>
                                <?php if (!empty($election['contested_posts'])): ?>
                                    <span class="badge badge-contesting">✓ Contesting</span>
                                <?php endif; ?>
                            </h3>
                            
                            <?php if (!empty($election['contested_posts'])): ?>
                                <div style="margin-top: 10px;">
                                    <strong style="color: #555;">Contesting for positions:</strong>
                                    <ul class="contest-posts">
                                        <?php foreach ($election['contested_posts'] as $post): ?>
                                            <li>🏆 <span><?php echo htmlspecialchars($post); ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <p style="color: #666; margin-top: 10px; font-size: 14px;">
                                    You are registered as a voter for this election but not contesting any position.
                                </p>
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
        <div>
            &copy; <?php echo date("Y"); ?> Jacob witty. All rights reserved.
        </div>
    </footer>
</body>
</html>

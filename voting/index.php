<?php
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// Fetch all elections using PDO
try {
    $electionQuery = "SELECT id, title FROM elections ORDER BY id DESC";
    $electionResult = $conn->query($electionQuery);
    $elections = $electionResult->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching elections: " . $e->getMessage());
    $elections = [];
}

// Helper function to get candidate image
function getCandidateImage($candidate) {
    // Check for BLOB image first
    if (!empty($candidate['profile_photo_blob'])) {
        return 'data:image/' . $candidate['profile_photo_type'] . ';base64,' . base64_encode($candidate['profile_photo_blob']);
    }
    // Check for file-based image
    elseif (!empty($candidate['profile_photo']) && file_exists('faces/' . $candidate['profile_photo'])) {
        return 'faces/' . htmlspecialchars($candidate['profile_photo']);
    }
    // Return default image
    else {
        return 'faces/default.jpg';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OVMS | Voting Results</title>
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
        
        .votes-container { 
            width: 90%; 
            max-width: 1200px; 
            margin: 20px auto; 
            background-color: white; 
            padding: 30px; 
            border-radius: 16px; 
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2); 
        }
        
        .votes-container h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 32px;
        }
        
        .votes-container h2 {
            color: #667eea;
            margin-top: 30px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .votes-container h3 {
            color: #764ba2;
            margin-top: 25px;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .votes-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .votes-table th, 
        .votes-table td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left; 
        }
        
        .votes-table th { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }
        
        .votes-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .candidate-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .winner-badge {
            display: inline-block;
            background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
            color: white;
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 20px;
            margin-left: 10px;
        }
        
        .vote-count {
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
        }
        
        .percentage-bar {
            background: #e5e7eb;
            border-radius: 10px;
            height: 8px;
            width: 100%;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .percentage-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
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
        
        .no-data {
            text-align: center;
            color: #999;
            padding: 60px;
            font-style: italic;
            background: white;
            border-radius: 16px;
        }
        
        .crown-icon {
            color: #f59e0b;
            margin-right: 5px;
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
            .votes-table th, 
            .votes-table td { 
                padding: 8px;
                font-size: 14px;
            }
            .candidate-image {
                width: 40px;
                height: 40px;
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
                <a href="index.php" class="active">All Votes</a>
                <a href="vote.php">Vote</a>
                <a href="apply.php">Candidacy Appli.</a>
                <a href="contest.php">Contesters</a>
                <a href="my_applications.php">My applications</a>
                <a href="profile.php">My Profile</a>
                <a href="logout.php">Log out</a>
            </div>
        </div>
    </header>

    <div class="votes-container">
        <h1><i class="fas fa-chart-bar"></i> Elections Results</h1>

        <?php if (empty($elections)): ?>
            <div class="no-data">
                <i class="fas fa-vote-yea" style="font-size: 48px; color: #cbd5e1;"></i>
                <p>No elections available at this time.</p>
                <p>Please check back later for upcoming elections.</p>
            </div>
        <?php else: ?>
            <?php foreach ($elections as $election): 
                $electionId = $election['id']; 
            ?>
                <h2><i class="fas fa-poll"></i> <?php echo htmlspecialchars($election['title']); ?></h2>

                <?php
                // Fetch distinct posts for the current election
                try {
                    // First try to get posts from election_posts table
                    $postQuery = $conn->prepare("SELECT DISTINCT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
                    $postQuery->execute([$electionId]);
                    $posts = $postQuery->fetchAll(PDO::FETCH_ASSOC);
                    
                    // If no posts in election_posts, get from contesters
                    if (empty($posts)) {
                        $postQuery2 = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ? ORDER BY postname");
                        $postQuery2->execute([$electionId]);
                        $posts = $postQuery2->fetchAll(PDO::FETCH_ASSOC);
                    }
                    
                    if (empty($posts)): 
                ?>
                        <p style="color: #999; font-style: italic;">No positions available for this election.</p>
                    <?php else: ?>
                        <?php foreach ($posts as $post): 
                            $postName = $post['postname']; 
                        ?>
                            <h3><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($postName); ?></h3>
                            <table class="votes-table">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">Photo</th>
                                        <th>Candidate Name</th>
                                        <th style="width: 150px;">Votes</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Fetch contestants and votes for the current post and election
                                    // Include BLOB columns for image retrieval
                                    $candidateQuery = $conn->prepare("
                                        SELECT id, name, votes, profile_photo, profile_photo_blob, profile_photo_type 
                                        FROM contesters 
                                        WHERE postname = ? AND election_id = ? 
                                        ORDER BY votes DESC
                                    ");
                                    $candidateQuery->execute([$postName, $electionId]);
                                    $candidates = $candidateQuery->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    // Calculate total votes for this position
                                    $totalVotes = array_sum(array_column($candidates, 'votes'));
                                    
                                    if (empty($candidates)):
                                    ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center;">No candidates for this position</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($candidates as $index => $candidate): 
                                            $isWinner = ($index === 0 && $totalVotes > 0);
                                            $percentage = ($totalVotes > 0) ? ($candidate['votes'] / $totalVotes) * 100 : 0;
                                        ?>
                                            <tr style="<?php echo $isWinner ? 'background: linear-gradient(90deg, #d1fae5 0%, #a7f3d0 100%);' : ''; ?>">
                                                <td style="text-align: center;">
                                                    <?php
                                                    // Display image from BLOB or file
                                                    if (!empty($candidate['profile_photo_blob'])) {
                                                        echo '<img src="data:image/' . $candidate['profile_photo_type'] . ';base64,' . base64_encode($candidate['profile_photo_blob']) . '" alt="Candidate" class="candidate-image">';
                                                    } elseif (!empty($candidate['profile_photo']) && file_exists('faces/' . $candidate['profile_photo'])) {
                                                        echo '<img src="faces/' . htmlspecialchars($candidate['profile_photo']) . '" alt="Candidate" class="candidate-image">';
                                                    } else {
                                                        echo '<img src="faces/default.jpg" alt="Default" class="candidate-image">';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($candidate['name']); ?></strong>
                                                    <?php if ($isWinner): ?>
                                                        <span class="winner-badge">
                                                            <i class="fas fa-crown"></i> Winner
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="vote-count"><?php echo number_format($candidate['votes']); ?></span> votes
                                                </td>
                                                <td>
                                                    <div style="display: flex; align-items: center; gap: 10px;">
                                                        <div class="percentage-bar" style="flex: 1;">
                                                            <div class="percentage-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                                        </div>
                                                        <span style="font-size: 14px; color: #666; min-width: 45px;"><?php echo number_format($percentage, 1); ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php } catch (PDOException $e) {
                    error_log("Error fetching posts/candidates: " . $e->getMessage());
                    echo "<p style='color: red;'>Error loading results for this election.</p>";
                } ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer>
        <div>
            <h3>Faster Links</h3>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="vote.php">Vote</a></li>
                <li><a href="apply.php">Contest</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Log out</a></li>
            </ul>
        </div>
        <div>
            &copy; <?php echo date("Y"); ?> Jacob Witty. All rights reserved.
        </div>
    </footer>
</body>
</html>

<?php
// Close PDO connection by setting to null
$conn = null;
?>

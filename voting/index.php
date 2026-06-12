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
            max-width: 1200px; 
            margin: 20px auto; 
            background-color: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
        }
        
        .votes-container h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
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
        }
        
        .votes-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
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
        
        .no-data {
            text-align: center;
            color: #999;
            padding: 40px;
            font-style: italic;
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

    <div class="votes-container">
        <h1>Voting Results</h1>

        <?php if (empty($elections)): ?>
            <div class="no-data">
                <p>No elections available at this time.</p>
                <p>Please check back later for upcoming elections.</p>
            </div>
        <?php else: ?>
            <?php foreach ($elections as $election): 
                $electionId = $election['id']; 
            ?>
                <h2><?php echo htmlspecialchars($election['title']); ?></h2>

                <?php
                // Fetch distinct posts for the current election using PDO
                try {
                    $postQuery = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ? ORDER BY postname");
                    $postQuery->execute([$electionId]);
                    $posts = $postQuery->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($posts)): 
                ?>
                        <p style="color: #999; font-style: italic;">No posts available for this election.</p>
                    <?php else: ?>
                        <?php foreach ($posts as $post): 
                            $postName = $post['postname']; 
                        ?>
                            <h3><?php echo htmlspecialchars($postName); ?></h3>
                            <table class="votes-table">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Candidate Name</th>
                                        <th>Votes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Fetch contestants and votes for the current post and election
                                    $candidateQuery = $conn->prepare("SELECT name, votes, profile_photo FROM contesters WHERE postname = ? AND election_id = ? ORDER BY votes DESC");
                                    $candidateQuery->execute([$postName, $electionId]);
                                    $candidates = $candidateQuery->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (empty($candidates)):
                                    ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center;">No candidates for this position</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($candidates as $candidate): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($candidate['profile_photo'])): ?>
                                                        <img src="faces/<?php echo htmlspecialchars($candidate['profile_photo']); ?>" alt="Candidate" class="candidate-image">
                                                    <?php else: ?>
                                                        <img src="faces/default.jpg" alt="Default" class="candidate-image">
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($candidate['name']); ?></td>
                                                <td>
                                                    <strong><?php echo intval($candidate['votes']); ?></strong>
                                                    <?php 
                                                    // Calculate total votes for this position to show percentage
                                                    $totalQuery = $conn->prepare("SELECT SUM(votes) as total FROM contesters WHERE postname = ? AND election_id = ?");
                                                    $totalQuery->execute([$postName, $electionId]);
                                                    $totalResult = $totalQuery->fetch(PDO::FETCH_ASSOC);
                                                    $totalVotes = $totalResult['total'] ?? 0;
                                                    if ($totalVotes > 0) {
                                                        $percentage = ($candidate['votes'] / $totalVotes) * 100;
                                                        echo " <span style='color: #666; font-size: 12px;'>(" . number_format($percentage, 1) . "%)</span>";
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php $postQuery->closeCursor(); ?>
                <?php } catch (PDOException $e) {
                    error_log("Error fetching posts/candidates: " . $e->getMessage());
                    echo "<p style='color: red;'>Error loading results for this election.</p>";
                } ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer>
        <div>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="vote.php">Vote</a></li>
                <li><a href="apply.php">Contest</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Log out</a></li>
            </ul>
        </div>
        <div style="text-align: center; padding: 10px; background-color: rgba(0,0,0,0.2); font-size: 0.8em; margin-top: 10px;">
            &copy; <?php echo date("Y"); ?> Jacob Witty. All rights reserved.
        </div>
    </footer>
</body>
</html>

<?php
// Close PDO connection by setting to null
$conn = null;
?>

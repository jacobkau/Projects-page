<?php
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// Fetch all elections using PDO
try {
    $electionsStmt = $conn->prepare("SELECT id, title FROM elections ORDER BY id DESC");
    $electionsStmt->execute();
    $elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching elections: " . $e->getMessage());
    $elections = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OVMS | Contestants</title>
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
        
        .contest-container { 
            width: 90%;
            max-width: 1200px; 
            margin: 40px auto; 
            background-color: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
        }
        
        .contest-container h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .election-section { 
            margin-bottom: 30px; 
            border: 1px solid #e0e0e0; 
            padding: 20px; 
            border-radius: 8px;
            background-color: #fafafa;
        }
        
        .election-section h2 {
            color: #667eea;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .post-section { 
            margin-bottom: 25px; 
            border-top: 1px solid #e0e0e0; 
            padding-top: 20px;
        }
        
        .post-section h3 {
            color: #764ba2;
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .contester-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .contester-table th, 
        .contester-table td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left; 
        }
        
        .contester-table th { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }
        
        .contester-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .contester-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
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
        
        @media (max-width: 768px) { 
            .navbar {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            .contest-container { 
                width: 95%;
                padding: 15px;
            }
            .contester-table th, 
            .contester-table td { 
                padding: 8px;
                font-size: 14px;
            }
            .contester-image {
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
                <a href="index.php">All Votes</a>
                <a href="vote.php">Vote</a>
                <a href="apply.php">Contest</a>
                <a href="contest.php" class="active">Contesters</a>
                <a href="members.php">Reg. Voters</a>
                <a href="my_applications.php">My applications</a>
                <a href="profile.php">My Profile</a>
                <a href="logout.php">Log out</a>
            </div>
        </div>
    </header>

    <div class="contest-container">
        <h1>🗳️ Election Contestants</h1>

        <?php if (empty($elections)): ?>
            <div class="no-data">
                <p>No elections found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($elections as $election): ?>
                <div class="election-section">
                    <h2><?php echo htmlspecialchars($election['title']); ?></h2>

                    <?php
                    // Fetch posts for the election from election_posts table
                    try {
                        $postsStmt = $conn->prepare("SELECT DISTINCT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
                        $postsStmt->execute([$election['id']]);
                        $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($posts)) {
                            // Try fetching from contesters table if no posts in election_posts
                            $postsStmt2 = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ? ORDER BY postname");
                            $postsStmt2->execute([$election['id']]);
                            $posts = $postsStmt2->fetchAll(PDO::FETCH_ASSOC);
                        }
                        
                        if (empty($posts)):
                    ?>
                            <p style="color: #999; font-style: italic;">No positions available for this election.</p>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                                <div class="post-section">
                                    <h3>📌 <?php echo htmlspecialchars($post['postname']); ?></h3>

                                    <table class="contester-table">
                                        <thead>
                                            <tr>
                                                <th width="80">Photo</th>
                                                <th width="200">Candidate Name</th>
                                                <th>Bio / Manifesto</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Fetch contesters for the post
                                            $contestersStmt = $conn->prepare("SELECT name, bio, profile_photo FROM contesters WHERE election_id = ? AND postname = ? ORDER BY name");
                                            $contestersStmt->execute([$election['id'], $post['postname']]);
                                            $contesters = $contestersStmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if (empty($contesters)):
                                            ?>
                                                <tr>
                                                    <td colspan="3" style="text-align: center;">No contestants for this position</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($contesters as $contester): ?>
                                                    <tr>
                                                        <td style="text-align: center;">
                                                            <?php if (!empty($contester['profile_photo']) && file_exists('faces/' . $contester['profile_photo'])): ?>
                                                                <img src="faces/<?php echo htmlspecialchars($contester['profile_photo']); ?>" alt="Contester" class="contester-image">
                                                            <?php else: ?>
                                                                <img src="faces/default.jpg" alt="Default" class="contester-image">
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><strong><?php echo htmlspecialchars($contester['name']); ?></strong></td>
                                                        <td><?php echo nl2br(htmlspecialchars($contester['bio'] ?? 'No bio provided')); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php 
                    } catch (PDOException $e) {
                        error_log("Error fetching posts/contesters: " . $e->getMessage());
                        echo "<p style='color: red;'>Error loading contestants for this election.</p>";
                    }
                    ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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

<?php
session_start();
include("conn.php");

// Fetch all elections
$electionsStmt = $conn->prepare("SELECT id, title FROM elections");
$electionsStmt->execute();
$electionsResult = $electionsStmt->get_result();
$electionsStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OVMS | Contests</title>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
    <link rel="icon" href="logo.jpg" type="image/x-icon">
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .contest-container { width: 80%; max-width: 800px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .election-section { margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; border-radius: 4px; }
        .post-section { margin-bottom: 10px; border-top: 1px solid #eee; padding-top: 10px; }
        .contester-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .contester-table th, .contester-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .contester-table th { background-color: #f2f2f2; }
        .navbar { background-color: #3498db; color: white; padding: 10px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin: 0 10px; }
        footer { background-color: #3498db; color: white; text-align: center; padding: 20px; margin-top: 20px; }
        footer ul { list-style: none; padding: 0; }
        footer li { display: inline; margin: 0 10px; }
        footer a { color: white; text-decoration: none; }
        .contester-image {
            width: 50px; /* Adjust as needed */
            height: 50px; /* Adjust as needed */
            border-radius: 50%;
            object-fit: cover;
        }
        @media (max-width: 768px) { .contest-container { width: 95%; } }
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

    <div class="contest-container">
        <h1>Election Contests</h1>

        <?php while ($election = $electionsResult->fetch_assoc()): ?>
            <div class="election-section">
                <h2><?php echo $election['title']; ?></h2>

                <?php
                // Fetch posts for the election
                $postsStmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ?");
                $postsStmt->bind_param("i", $election['id']);
                $postsStmt->execute();
                $postsResult = $postsStmt->get_result();

                while ($post = $postsResult->fetch_assoc()):
                ?>
                    <div class="post-section">
                        <h3 style="color:#3498db"><?php echo $post['postname']; ?></h3>

                        <table class="contester-table">
                            <thead>
                                <tr>
                                    <th>Face</th>
                                    <th>Username</th>
                                    <th>Bio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch contesters for the post
                                $contestersStmt = $conn->prepare("SELECT name, bio, profile_photo FROM contesters WHERE election_id = ? AND postname = ?");
                                $contestersStmt->bind_param("is", $election['id'], $post['postname']);
                                $contestersStmt->execute();
                                $contestersResult = $contestersStmt->get_result();

                                while ($contester = $contestersResult->fetch_assoc()):
                                ?>
                                    <tr>
                                     <td>
                                            <?php if (!empty($contester['profile_photo'])): ?>
                                                <img src="faces/<?php echo htmlspecialchars($contester['profile_photo']); ?>" alt="Contester Face" class="contester-image">
                                            <?php else: ?>
                                                <img src="faces/default.jpg" alt="contester-face" class="contester-image">
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($contester['name']); ?></td>
                                        <td><?php echo htmlspecialchars($contester['bio']); ?></td>
                                       
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endwhile; ?>
    </div>

    <footer>
        <div>
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
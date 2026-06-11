<?php
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// Fetch all elections
$electionQuery = "SELECT id, title FROM elections ORDER BY id";
$electionResult = $conn->query($electionQuery);
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
        body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .votes-container { width: 80%; max-width: 1200px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .votes-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .votes-table th, .votes-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .votes-table th { background-color: #f2f2f2; }
        .navbar { background-color: #3498db; color: white; padding: 10px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin: 0 10px; }
        footer { background-color: #3498db; color: white; text-align: center; padding: 20px; margin-top: 20px; }
        footer ul { list-style: none; padding: 0; }
        footer li { display: inline; margin: 0 10px; }
        footer a { color: white; text-decoration: none; }
        .candidate-image {
            width: 50px; /* Adjust as needed */
            height: 50px; /* Adjust as needed */
            border-radius: 50%;
            object-fit: cover;
        }
        @media (max-width: 768px) { .votes-container { width: 95%; } }
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
        <h1>Voting Results</h1>

        <?php if ($electionResult->num_rows > 0) { ?>
            <?php while ($election = $electionResult->fetch_assoc()) { 
                $electionId = $election['id']; 
            ?>
                <h2><?php echo htmlspecialchars($election['title']); ?></h2>

                <?php
                // Fetch distinct posts for the current election
                $postQuery = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ?");
                $postQuery->bind_param("i", $electionId);
                $postQuery->execute();
                $postResult = $postQuery->get_result();

                if ($postResult->num_rows > 0) {
                    while ($post = $postResult->fetch_assoc()) { 
                        $postName = $post['postname']; 
                ?>
                        <h3 style="color:#3498db"><?php echo htmlspecialchars($postName); ?></h3>
                        <table class="votes-table">
                            <thead>
                                <tr>
                                    <th>Candidate</th>
                                    <th>Face</th>
                                    <th>Votes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch contestants and votes for the current post and election
                                $candidateQuery = $conn->prepare("SELECT name, votes, profile_photo FROM contesters WHERE postname = ? AND election_id = ?");
                                $candidateQuery->bind_param("si", $postName, $electionId);
                                $candidateQuery->execute();
                                $candidateResult = $candidateQuery->get_result();

                                while ($candidate = $candidateResult->fetch_assoc()) { ?>
                                    <tr>
                                     <td>
                                            <?php if (!empty($candidate['profile_photo'])): ?>
                                                <img src="faces/<?php echo htmlspecialchars($candidate['profile_photo']); ?>" alt="Candidate Face" class="candidate-image">
                                            <?php else: ?>
                                                <img src="faces/default.jpg" alt="Default Face" class="candidate-image">
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($candidate['name']); ?></td>                                       
                                        <td><?php echo intval($candidate['votes']); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                <?php 
                        $candidateQuery->close();
                    }
                } else { 
                    echo "<p>No posts available for this election.</p>";
                }

                $postQuery->close();
                ?>
            <?php } ?>
        <?php } else { ?>
            <p>No elections available</p>
        <?php } ?>
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
$conn->close();
?>
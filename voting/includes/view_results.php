<?php
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Fetch Elections
$electionsStmt = $conn->prepare("SELECT id, title FROM elections");
$electionsStmt->execute();
$electionsResult = $electionsStmt->get_result();
$elections = $electionsResult->fetch_all(MYSQLI_ASSOC);
$electionsStmt->close();

function analyzeElectionResults($conn, $electionId) {
    $electionTitleStmt = $conn->prepare("SELECT title FROM elections WHERE id = ?");
    $electionTitleStmt->bind_param("i", $electionId);
    $electionTitleStmt->execute();
    $electionTitleResult = $electionTitleStmt->get_result();
    $electionTitle = $electionTitleResult->fetch_assoc()['title'];
    $electionTitleStmt->close();

    $postsStmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ?");
    $postsStmt->bind_param("i", $electionId);
    $postsStmt->execute();
    $postsResult = $postsStmt->get_result();
    $posts = $postsResult->fetch_all(MYSQLI_ASSOC);
    $postsStmt->close();

    $analysis = "<h3>Election: " . htmlspecialchars($electionTitle) . "</h3>";

    foreach ($posts as $post) {
        $position = $post['postname'];
        $analysis .= "<h4>Position: " . htmlspecialchars($position) . "</h4>";

        $sql = "SELECT " . $position . ", COUNT(*) as vote_count FROM votes WHERE election_id = ? GROUP BY " . $position . " ORDER BY vote_count DESC";
        $votesStmt = $conn->prepare($sql);
        $votesStmt->bind_param("i", $electionId);
        $votesStmt->execute();
        $votesResult = $votesStmt->get_result();
        $votes = $votesResult->fetch_all(MYSQLI_ASSOC);
        $votesStmt->close();

        if (count($votes) > 0) {
            $analysis .= "<ul>";
            foreach ($votes as $vote) {
                $candidate = $vote[$position];
                $count = $vote['vote_count'];
                $analysis .= "<li>" . htmlspecialchars($candidate) . ": " . $count . " votes</li>";
            }
            $analysis .= "</ul>";
        } else {
            $analysis .= "<p>No votes recorded for this position.</p>";
        }
    }
    return $analysis;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Analyze Voting Results</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .admin-container { width: 90%; max-width: 1200px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        
        @media (max-width: 768px) { .admin-container { width: 95%; } }
    </style>
</head>
<body>
   

    <div class="admin-container">
        <h2>Voting Results Analysis</h2>

        <?php foreach ($elections as $election): ?>
            <?php echo analyzeElectionResults($conn, $election['id']); ?>
        <?php endforeach; ?>
    </div>

   
</body>
</html>

<?php
$conn->close();
?>
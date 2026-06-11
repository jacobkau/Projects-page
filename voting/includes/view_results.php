<?php
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Fetch Elections
try {
    $electionsStmt = $conn->prepare("SELECT id, title FROM elections ORDER BY id DESC");
    $electionsStmt->execute();
    $elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching elections: " . $e->getMessage());
    $elections = [];
}

function analyzeElectionResults($conn, $electionId) {
    try {
        // Get election title
        $electionTitleStmt = $conn->prepare("SELECT title FROM elections WHERE id = ?");
        $electionTitleStmt->execute([$electionId]);
        $electionTitleRow = $electionTitleStmt->fetch(PDO::FETCH_ASSOC);
        $electionTitle = $electionTitleRow ? $electionTitleRow['title'] : 'Unknown Election';
        $electionTitleStmt->closeCursor();

        // Get posts for this election
        $postsStmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
        $postsStmt->execute([$electionId]);
        $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
        $postsStmt->closeCursor();

        $analysis = "<h3>Election: " . htmlspecialchars($electionTitle) . "</h3>";

        if (empty($posts)) {
            $analysis .= "<p>No positions defined for this election.</p>";
            return $analysis;
        }

        foreach ($posts as $post) {
            $position = $post['postname'];
            $analysis .= "<h4>Position: " . htmlspecialchars($position) . "</h4>";

            // Get vote counts for this position
            $sql = "SELECT candidate_name, COUNT(*) as vote_count 
                    FROM votes 
                    WHERE election_id = ? AND postname = ? 
                    GROUP BY candidate_name 
                    ORDER BY vote_count DESC";
            
            $votesStmt = $conn->prepare($sql);
            $votesStmt->execute([$electionId, $position]);
            $votes = $votesStmt->fetchAll(PDO::FETCH_ASSOC);
            $votesStmt->closeCursor();

            if (count($votes) > 0) {
                $analysis .= "<ul style='margin-bottom: 20px;'>";
                $totalVotes = 0;
                foreach ($votes as $vote) {
                    $candidate = $vote['candidate_name'];
                    $count = $vote['vote_count'];
                    $totalVotes += $count;
                    $percentage = ($count / $totalVotes) * 100;
                    $analysis .= "<li>" . htmlspecialchars($candidate) . ": " . $count . " votes (" . number_format($percentage, 1) . "%)</li>";
                }
                $analysis .= "</ul>";
                $analysis .= "<p><strong>Total votes for this position:</strong> " . $totalVotes . "</p>";
            } else {
                $analysis .= "<p>No votes recorded for this position.</p>";
            }
        }
        return $analysis;
    } catch (PDOException $e) {
        error_log("Error analyzing election results: " . $e->getMessage());
        return "<p>Error analyzing results for election ID: " . htmlspecialchars($electionId) . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Analyze Voting Results</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .admin-container { width: 90%; max-width: 1200px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        h2 { color: #333; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        h3 { color: #2c3e50; margin-top: 25px; }
        h4 { color: #34495e; margin-top: 20px; }
        ul { background-color: #f9f9f9; padding: 15px 15px 15px 35px; border-radius: 5px; }
        li { margin: 8px 0; }
        .no-data { color: #999; font-style: italic; padding: 10px; text-align: center; }
        @media (max-width: 768px) { .admin-container { width: 95%; } }
    </style>
</head>
<body>
    <div class="admin-container">
        <h2>Voting Results Analysis</h2>
        
        <?php if (empty($elections)): ?>
            <p class="no-data">No elections found. Please create an election first.</p>
        <?php else: ?>
            <?php foreach ($elections as $election): ?>
                <?php echo analyzeElectionResults($conn, $election['id']); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
// Close PDO connection by setting to null
$conn = null;
?>

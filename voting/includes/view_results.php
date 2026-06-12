<?php
session_start(); // Add session start
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fetch Elections
try {
    $electionsStmt = $conn->prepare("SELECT id, title FROM elections ORDER BY id DESC");
    $electionsStmt->execute();
    $elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching elections: " . $e->getMessage());
    $elections = [];
    echo "<div style='background:#f8d7da; color:#721c24; padding:15px; margin:10px; border-radius:5px;'>";
    echo "<strong>Database Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

function analyzeElectionResults($conn, $electionId) {
    try {
        // Get election title
        $electionTitleStmt = $conn->prepare("SELECT title FROM elections WHERE id = ?");
        $electionTitleStmt->execute([$electionId]);
        $electionTitleRow = $electionTitleStmt->fetch(PDO::FETCH_ASSOC);
        $electionTitle = $electionTitleRow ? $electionTitleRow['title'] : 'Unknown Election';
        $electionTitleStmt->closeCursor();

        // Check if election_posts table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'election_posts'");
        if ($tableCheck->rowCount() == 0) {
            return "<div style='background:#fff3cd; color:#856404; padding:15px; margin:10px; border-radius:5px;'>
                        <strong>⚠️ Table Missing:</strong> The 'election_posts' table doesn't exist. 
                        Please run: CREATE TABLE election_posts (id INT AUTO_INCREMENT PRIMARY KEY, election_id INT NOT NULL, postname VARCHAR(100) NOT NULL)
                    </div>";
        }
        
        // Get posts for this election
        $postsStmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
        $postsStmt->execute([$electionId]);
        $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
        $postsStmt->closeCursor();

        $analysis = "<h3>Election: " . htmlspecialchars($electionTitle) . "</h3>";

        if (empty($posts)) {
            $analysis .= "<div style='background:#fff3cd; color:#856404; padding:10px; margin:10px 0; border-radius:5px;'>";
            $analysis .= "No positions defined for this election. Add positions using the 'Manage Posts' button in elections management.";
            $analysis .= "</div>";
            return $analysis;
        }

        // Check if votes table exists
        $votesTableCheck = $conn->query("SHOW TABLES LIKE 'votes'");
        if ($votesTableCheck->rowCount() == 0) {
            return "<div style='background:#fff3cd; color:#856404; padding:15px; margin:10px; border-radius:5px;'>
                        <strong>⚠️ Table Missing:</strong> The 'votes' table doesn't exist.
                    </div>";
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
                }
                
                foreach ($votes as $vote) {
                    $candidate = $vote['candidate_name'];
                    $count = $vote['vote_count'];
                    $percentage = ($totalVotes > 0) ? ($count / $totalVotes) * 100 : 0;
                    $analysis .= "<li>" . htmlspecialchars($candidate) . ": " . $count . " votes (" . number_format($percentage, 1) . "%)</li>";
                }
                $analysis .= "</ul>";
                $analysis .= "<p><strong>Total votes for this position:</strong> " . $totalVotes . "</p>";
            } else {
                $analysis .= "<div style='background:#e2e3e5; color:#383d41; padding:10px; margin:10px 0; border-radius:5px;'>";
                $analysis .= "No votes recorded for this position yet.";
                $analysis .= "</div>";
            }
        }
        return $analysis;
    } catch (PDOException $e) {
        error_log("Error analyzing election results: " . $e->getMessage());
        return "<div style='background:#f8d7da; color:#721c24; padding:15px; margin:10px; border-radius:5px;'>
                    <strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "
                </div>";
    }
}

// Debug: Display table structure info
$debug = [];
try {
    $tables = ['elections', 'election_posts', 'votes', 'contesters'];
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        $debug[$table] = $check->rowCount() > 0;
    }
} catch (Exception $e) {
    $debug['error'] = $e->getMessage();
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
        .debug-panel { 
            background: #2c3e50; 
            color: #ecf0f1; 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
        }
        .debug-panel h4 { color: #3498db; margin-top: 0; }
        .debug-panel ul { background: none; padding: 0; margin: 0; list-style: none; }
        .debug-panel li { margin: 5px 0; }
        .debug-panel .good { color: #2ecc71; }
        .debug-panel .bad { color: #e74c3c; }
        @media (max-width: 768px) { .admin-container { width: 95%; } }
    </style>
</head>
<body>
    <div class="admin-container">
        <h2>Voting Results Analysis</h2>
        
        <!-- Debug Panel - Remove after fixing -->
        <div class="debug-panel">
            <h4>🔧 System Check</h4>
            <ul>
                <?php foreach ($debug as $table => $exists): ?>
                    <?php if ($table != 'error'): ?>
                        <li>Table '<?php echo $table; ?>': <?php echo $exists ? '<span class="good">✓ Exists</span>' : '<span class="bad">✗ Missing</span>'; ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (isset($debug['error'])): ?>
                    <li class="bad">Error: <?php echo $debug['error']; ?></li>
                <?php endif; ?>
            </ul>
            <p><small>If any tables are missing, run the necessary CREATE TABLE queries.</small></p>
        </div>
        
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
// Close PDO connection
$conn = null;
?>

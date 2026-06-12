<?php
// Remove session_start() since it's already started in main.php
// session_start(); // Comment this out - it's already started

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

        // Get posts for this election from election_posts table
        $postsStmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
        $postsStmt->execute([$electionId]);
        $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
        $postsStmt->closeCursor();

        $analysis = "<h3>Election: " . htmlspecialchars($electionTitle) . "</h3>";

        if (empty($posts)) {
            $analysis .= "<div style='background:#fff3cd; color:#856404; padding:10px; margin:10px 0; border-radius:5px;'>";
            $analysis .= "No positions defined for this election. Add positions using the 'Manage Posts' button.";
            $analysis .= "</div>";
            return $analysis;
        }

        foreach ($posts as $post) {
            $position = $post['postname'];
            $analysis .= "<h4>Position: " . htmlspecialchars($position) . "</h4>";

            // Get vote counts for this position based on your table structure
            // Since your votes table has columns like chairperson, vicechairperson, etc.
            // Convert position name to lowercase column name (remove spaces)
            $columnName = strtolower(str_replace(' ', '', $position));
            
            try {
                // First check if the column exists in votes table
                $columnCheck = $conn->query("SHOW COLUMNS FROM votes LIKE '$columnName'");
                if ($columnCheck->rowCount() == 0) {
                    $analysis .= "<div style='background:#e2e3e5; color:#383d41; padding:10px; margin:10px 0; border-radius:5px;'>";
                    $analysis .= "No votes recorded for position: " . htmlspecialchars($position) . " (column '$columnName' not found)";
                    $analysis .= "</div>";
                    continue;
                }
                
                // Query to get vote counts for this position
                $sql = "SELECT $columnName as candidate_name, COUNT(*) as vote_count 
                        FROM votes 
                        WHERE election_id = ? AND $columnName IS NOT NULL AND $columnName != ''
                        GROUP BY $columnName 
                        ORDER BY vote_count DESC";
                
                $votesStmt = $conn->prepare($sql);
                $votesStmt->execute([$electionId]);
                $votes = $votesStmt->fetchAll(PDO::FETCH_ASSOC);
                $votesStmt->closeCursor();

                if (count($votes) > 0) {
                    $analysis .= "<ul style='margin-bottom: 20px;'>";
                    $totalVotes = 0;
                    foreach ($votes as $vote) {
                        $totalVotes += $vote['vote_count'];
                    }
                    
                    foreach ($votes as $vote) {
                        $candidate = $vote['candidate_name'];
                        $count = $vote['vote_count'];
                        $percentage = ($totalVotes > 0) ? ($count / $totalVotes) * 100 : 0;
                        $analysis .= "<li><strong>" . htmlspecialchars($candidate) . "</strong>: " . $count . " votes (" . number_format($percentage, 1) . "%)</li>";
                    }
                    $analysis .= "</ul>";
                    $analysis .= "<p><strong>Total votes for this position:</strong> " . $totalVotes . "</p>";
                } else {
                    $analysis .= "<div style='background:#e2e3e5; color:#383d41; padding:10px; margin:10px 0; border-radius:5px;'>";
                    $analysis .= "No votes recorded for " . htmlspecialchars($position) . " yet.";
                    $analysis .= "</div>";
                }
            } catch (PDOException $e) {
                $analysis .= "<div style='background:#f8d7da; color:#721c24; padding:10px; margin:10px 0; border-radius:5px;'>";
                $analysis .= "Error fetching votes for " . htmlspecialchars($position) . ": " . $e->getMessage();
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

// Alternative approach - Get results from contesters table
function getResultsFromContesters($conn, $electionId) {
    try {
        $electionTitleStmt = $conn->prepare("SELECT title FROM elections WHERE id = ?");
        $electionTitleStmt->execute([$electionId]);
        $electionTitle = $electionTitleStmt->fetchColumn();
        
        $analysis = "<h3>Election: " . htmlspecialchars($electionTitle) . " (From Candidates Table)</h3>";
        
        // Get all distinct posts from contesters for this election
        $postsStmt = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ? ORDER BY postname");
        $postsStmt->execute([$electionId]);
        $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($posts)) {
            $analysis .= "<p>No candidates have applied for this election.</p>";
            return $analysis;
        }
        
        foreach ($posts as $post) {
            $position = $post['postname'];
            $analysis .= "<h4>Position: " . htmlspecialchars($position) . "</h4>";
            
            // Get candidates and their vote counts from contesters table
            $candidatesStmt = $conn->prepare("SELECT name, votes FROM contesters WHERE election_id = ? AND postname = ? ORDER BY votes DESC");
            $candidatesStmt->execute([$electionId, $position]);
            $candidates = $candidatesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($candidates) > 0) {
                $analysis .= "<ul style='margin-bottom: 20px;'>";
                $totalVotes = 0;
                foreach ($candidates as $candidate) {
                    $totalVotes += $candidate['votes'];
                }
                
                foreach ($candidates as $candidate) {
                    $percentage = ($totalVotes > 0) ? ($candidate['votes'] / $totalVotes) * 100 : 0;
                    $analysis .= "<li><strong>" . htmlspecialchars($candidate['name']) . "</strong>: " . $candidate['votes'] . " votes (" . number_format($percentage, 1) . "%)</li>";
                }
                $analysis .= "</ul>";
                $analysis .= "<p><strong>Total votes for this position:</strong> " . $totalVotes . "</p>";
            } else {
                $analysis .= "<p>No votes recorded for this position.</p>";
            }
        }
        
        return $analysis;
    } catch (PDOException $e) {
        return "<p>Error: " . $e->getMessage() . "</p>";
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
        h3 { color: #2c3e50; margin-top: 25px; border-left: 4px solid #3498db; padding-left: 15px; }
        h4 { color: #34495e; margin-top: 20px; background: #ecf0f1; padding: 8px 12px; border-radius: 5px; }
        ul { background-color: #f9f9f9; padding: 15px 15px 15px 35px; border-radius: 5px; }
        li { margin: 8px 0; }
        .no-data { color: #999; font-style: italic; padding: 10px; text-align: center; }
        .winner { 
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-weight: bold;
        }
        @media (max-width: 768px) { .admin-container { width: 95%; } }
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 10px 20px;
            background: #ecf0f1;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .tab-btn.active {
            background: #3498db;
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h2>Voting Results Analysis</h2>
        
        <div class="tab-buttons">
            <button class="tab-btn active" onclick="showTab('contesters')">View from Candidates</button>
            <button class="tab-btn" onclick="showTab('votes')">View from Votes</button>
        </div>
        
        <div id="contesters-tab" class="tab-content active">
            <?php if (empty($elections)): ?>
                <p class="no-data">No elections found. Please create an election first.</p>
            <?php else: ?>
                <?php foreach ($elections as $election): ?>
                    <?php echo getResultsFromContesters($conn, $election['id']); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div id="votes-tab" class="tab-content">
            <?php if (empty($elections)): ?>
                <p class="no-data">No elections found. Please create an election first.</p>
            <?php else: ?>
                <?php foreach ($elections as $election): ?>
                    <?php echo analyzeElectionResults($conn, $election['id']); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    function showTab(tabName) {
        // Hide all tabs
        document.getElementById('contesters-tab').classList.remove('active');
        document.getElementById('votes-tab').classList.remove('active');
        
        // Remove active class from all buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(tabName + '-tab').classList.add('active');
        
        // Add active class to clicked button
        event.target.classList.add('active');
    }
    </script>
</body>
</html>

<?php
// Close PDO connection
$conn = null;
?>

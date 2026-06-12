<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("conn.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Fetch Elections
$electionsStmt = $conn->prepare("SELECT id, title, status FROM elections ORDER BY id DESC");
$electionsStmt->execute();
$elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Voting Results</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 0 auto; }
        .election-card { background: white; margin-bottom: 30px; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .election-title { color: #667eea; margin-top: 0; }
        .post-section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 8px; }
        .post-title { color: #764ba2; margin: 0 0 10px 0; }
        .candidate-list { list-style: none; padding: 0; }
        .candidate-list li { padding: 8px; border-bottom: 1px solid #eee; }
        .winner { background: #d4edda; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Voting Results</h1>
        
        <?php foreach ($elections as $election): ?>
            <div class="election-card">
                <h2 class="election-title"><?php echo htmlspecialchars($election['title']); ?></h2>
                <p>Status: <strong><?php echo ucfirst($election['status']); ?></strong></p>
                
                <?php
                // Get posts for this election
                $postsStmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
                $postsStmt->execute([$election['id']]);
                $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($posts)) {
                    echo "<p>No positions defined for this election.</p>";
                } else {
                    foreach ($posts as $post):
                        $postName = $post['postname'];
                        
                        // Get vote counts for this position
                        $voteStmt = $conn->prepare("
                            SELECT candidate_name, COUNT(*) as vote_count 
                            FROM votes 
                            WHERE election_id = ? AND postname = ? 
                            GROUP BY candidate_name 
                            ORDER BY vote_count DESC
                        ");
                        $voteStmt->execute([$election['id'], $postName]);
                        $results = $voteStmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <div class="post-section">
                            <h3 class="post-title"><?php echo htmlspecialchars($postName); ?></h3>
                            
                            <?php if (empty($results)): ?>
                                <p>No votes recorded for this position.</p>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr><th>Candidate</th><th>Votes</th><th>Percentage</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $totalVotes = array_sum(array_column($results, 'vote_count'));
                                        foreach ($results as $index => $result):
                                            $percentage = ($totalVotes > 0) ? ($result['vote_count'] / $totalVotes) * 100 : 0;
                                            $isWinner = ($index === 0);
                                        ?>
                                            <tr class="<?php echo $isWinner ? 'winner' : ''; ?>">
                                                <td><?php echo htmlspecialchars($result['candidate_name']); ?></td>
                                                <td><?php echo $result['vote_count']; ?></td>
                                                <td><?php echo number_format($percentage, 1); ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php endforeach;
                }
                ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>

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
$electionsStmt = $conn->prepare("SELECT id, title, status, start_date, end_date FROM elections ORDER BY id DESC");
$electionsStmt->execute();
$elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get winner for a position
function getWinner($results) {
    if (empty($results)) return null;
    return $results[0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Voting Results</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
               
        /* Header */
        .header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 800;
            color: white;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .header p {
            color: rgba(255,255,255,0.9);
            font-size: 16px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 40px;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: #333;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Election Card */
        .election-card {
            background: white;
            border-radius: 20px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .election-card:hover {
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .election-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px 30px;
            color: white;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .election-header:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46a0 100%);
        }
        
        .election-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .election-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-active {
            background: #10b981;
            color: white;
        }
        
        .badge-upcoming {
            background: #f59e0b;
            color: white;
        }
        
        .badge-completed {
            background: #6b7280;
            color: white;
        }
        
        .election-dates {
            font-size: 14px;
            opacity: 0.9;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .election-dates i {
            margin-right: 5px;
        }
        
        .election-content {
            padding: 30px;
            display: none;
        }
        
        .election-card.active .election-content {
            display: block;
        }
        
        .toggle-icon {
            font-size: 24px;
            transition: transform 0.3s ease;
        }
        
        .election-card.active .toggle-icon {
            transform: rotate(180deg);
        }
        
        /* Post Section */
        .post-section {
            margin-bottom: 30px;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            background: #f9fafb;
        }
        
        .post-header {
            background: #f3f4f6;
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .post-title {
            font-size: 18px;
            font-weight: 700;
            color: #374151;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .winner-badge {
            background: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* Table Styles */
        .results-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .results-table th {
            background: #f3f4f6;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .results-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .results-table tr:last-child td {
            border-bottom: none;
        }
        
        .results-table tr:hover {
            background: #fef3c7;
        }
        
        .winner-row {
            background: linear-gradient(90deg, #d1fae5 0%, #a7f3d0 100%);
            font-weight: 600;
        }
        
        .candidate-name {
            font-weight: 600;
            color: #1f2937;
        }
        
        .vote-count {
            font-weight: 700;
            color: #667eea;
            font-size: 18px;
        }
        
        .percentage-bar {
            background: #e5e7eb;
            border-radius: 10px;
            height: 8px;
            width: 100%;
            overflow: hidden;
        }
        
        .percentage-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        
        .percentage-text {
            font-size: 14px;
            color: #6b7280;
            margin-left: 10px;
        }
        
        .crown-icon {
            color: #f59e0b;
            margin-right: 8px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }
        
        .empty-state p {
            color: #6b7280;
            font-size: 16px;
        }
        
        /* No votes state */
        .no-votes {
            padding: 20px;
            text-align: center;
            color: #9ca3af;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .election-header {
                padding: 15px 20px;
            }
            
            .election-title {
                font-size: 18px;
            }
            
            .election-content {
                padding: 15px;
            }
            
            .results-table th,
            .results-table td {
                padding: 8px 10px;
                font-size: 14px;
            }
            
            .stats-grid {
                gap: 10px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-value {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Voting Results Dashboard</h1>
            <p>Real-time election results and analytics</p>
        </div>
        
        <?php if (empty($elections)): ?>
            <div class="empty-state">
                <i class="fas fa-vote-yea"></i>
                <p>No elections found. Please create an election first.</p>
            </div>
        <?php else: ?>
            <!-- Statistics Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-value"><?php echo count($elections); ?></div>
                    <div class="stat-label">Total Elections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value">
                        <?php 
                        $activeCount = count(array_filter($elections, function($e) { return $e['status'] == 'active'; }));
                        echo $activeCount;
                        ?>
                    </div>
                    <div class="stat-label">Active Elections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                    <div class="stat-value">
                        <?php 
                        $completedCount = count(array_filter($elections, function($e) { return $e['status'] == 'completed'; }));
                        echo $completedCount;
                        ?>
                    </div>
                    <div class="stat-label">Completed Elections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value">
                        <?php
                        $totalVotes = $conn->query("SELECT COUNT(*) as count FROM votes")->fetch(PDO::FETCH_ASSOC);
                        echo number_format($totalVotes['count']);
                        ?>
                    </div>
                    <div class="stat-label">Total Votes Cast</div>
                </div>
            </div>
            
            <!-- Elections List -->
            <?php foreach ($elections as $index => $election): ?>
                <?php
                // Get posts for this election
                $postsStmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ? ORDER BY postname");
                $postsStmt->execute([$election['id']]);
                $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <div class="election-card" id="election-<?php echo $election['id']; ?>">
                    <div class="election-header" onclick="toggleElection(<?php echo $election['id']; ?>)">
                        <div class="election-title">
                            <span><i class="fas fa-poll"></i> <?php echo htmlspecialchars($election['title']); ?></span>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <span class="election-badge badge-<?php echo $election['status']; ?>">
                                    <i class="fas <?php echo $election['status'] == 'active' ? 'fa-play' : ($election['status'] == 'upcoming' ? 'fa-clock' : 'fa-flag-checkered'); ?>"></i>
                                    <?php echo ucfirst($election['status']); ?>
                                </span>
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </div>
                        </div>
                        <div class="election-dates">
                            <span><i class="far fa-calendar-alt"></i> Start: <?php echo date('M d, Y H:i', strtotime($election['start_date'])); ?></span>
                            <span><i class="far fa-calendar-check"></i> End: <?php echo date('M d, Y H:i', strtotime($election['end_date'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="election-content">
                        <?php if (empty($posts)): ?>
                            <div class="no-votes">
                                <i class="fas fa-info-circle"></i> No positions defined for this election.
                            </div>
                        <?php else: ?>
                            <?php foreach ($posts as $post):
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
                                $totalVotes = array_sum(array_column($results, 'vote_count'));
                                $winner = !empty($results) ? $results[0] : null;
                                ?>
                                
                                <div class="post-section">
                                    <div class="post-header">
                                        <div class="post-title">
                                            <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($postName); ?></span>
                                            <?php if ($winner && $totalVotes > 0): ?>
                                                <span class="winner-badge">
                                                    <i class="fas fa-crown"></i> Winner: <?php echo htmlspecialchars($winner['candidate_name']); ?> (<?php echo $winner['vote_count']; ?> votes)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (empty($results) || $totalVotes == 0): ?>
                                        <div class="no-votes">
                                            <i class="fas fa-chart-simple"></i> No votes recorded for this position yet.
                                        </div>
                                    <?php else: ?>
                                        <table class="results-table">
                                            <thead>
                                                <tr>
                                                    <th>Rank</th>
                                                    <th>Candidate</th>
                                                    <th>Votes</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($results as $index => $result):
                                                    $percentage = ($totalVotes > 0) ? ($result['vote_count'] / $totalVotes) * 100 : 0;
                                                    $isWinner = ($index === 0);
                                                ?>
                                                    <tr class="<?php echo $isWinner ? 'winner-row' : ''; ?>">
                                                        <td width="60">
                                                            <?php if ($isWinner): ?>
                                                                <i class="fas fa-crown crown-icon"></i> #<?php echo $index + 1; ?>
                                                            <?php else: ?>
                                                                #<?php echo $index + 1; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="candidate-name">
                                                            <?php echo htmlspecialchars($result['candidate_name']); ?>
                                                        </td>
                                                        <td width="100">
                                                            <span class="vote-count"><?php echo number_format($result['vote_count']); ?></span>
                                                        </td>
                                                        <td>
                                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                                <div class="percentage-bar" style="flex: 1;">
                                                                    <div class="percentage-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                                                </div>
                                                                <span class="percentage-text"><?php echo number_format($percentage, 1); ?>%</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleElection(electionId) {
            const card = document.getElementById('election-' + electionId);
            card.classList.toggle('active');
        }
        
        // Auto-open first election card
        const firstCard = document.querySelector('.election-card');
        if (firstCard) {
            firstCard.classList.add('active');
        }
        
        // Animate percentage bars on load
        document.addEventListener('DOMContentLoaded', function() {
            const fills = document.querySelectorAll('.percentage-fill');
            fills.forEach(fill => {
                const width = fill.style.width;
                fill.style.width = '0%';
                setTimeout(() => {
                    fill.style.width = width;
                }, 100);
            });
        });
    </script>
</body>
</html>

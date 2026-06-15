<?php
session_start();
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$message = "";
$messageType = "";
$selectedElection = null;
$electionStats = null;

// Fetch all elections
$electionsStmt = $conn->prepare("SELECT id, title, status, start_date, end_date FROM elections ORDER BY id DESC");
$electionsStmt->execute();
$elections = $electionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Election Selection
if (isset($_GET['election_id'])) {
    $electionId = intval($_GET['election_id']);
    $selectedElection = array_filter($elections, function($e) use ($electionId) {
        return $e['id'] == $electionId;
    });
    $selectedElection = !empty($selectedElection) ? array_values($selectedElection)[0] : null;
    
    if ($selectedElection) {
        // Get vote statistics
        $voteStats = $conn->prepare("
            SELECT 
                COUNT(*) as total_votes,
                COUNT(DISTINCT username) as unique_voters,
                COUNT(DISTINCT postname) as total_positions
            FROM votes 
            WHERE election_id = ?
        ");
        $voteStats->execute([$electionId]);
        $electionStats = $voteStats->fetch(PDO::FETCH_ASSOC);
        
        // Get votes per position
        $positionStmt = $conn->prepare("
            SELECT postname, COUNT(*) as vote_count 
            FROM votes 
            WHERE election_id = ? 
            GROUP BY postname 
            ORDER BY vote_count DESC
        ");
        $positionStmt->execute([$electionId]);
        $positionStats = $positionStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle Delete All Votes for Election
if (isset($_POST['delete_all_votes'])) {
    $electionId = intval($_POST['election_id']);
    
    try {
        $conn->beginTransaction();
        
        // Get vote count before deletion
        $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM votes WHERE election_id = ?");
        $countStmt->execute([$electionId]);
        $voteCount = $countStmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete all votes for this election
        $deleteStmt = $conn->prepare("DELETE FROM votes WHERE election_id = ?");
        $deleteStmt->execute([$electionId]);
        
        // Reset vote counts in contesters table
        $resetStmt = $conn->prepare("UPDATE contesters SET votes = 0 WHERE election_id = ?");
        $resetStmt->execute([$electionId]);
        
        // Log the action
        $logStmt = $conn->prepare("
            INSERT INTO event_log (username, event_type, event_description) 
            VALUES (?, 'Delete Votes', ?)
        ");
        $logStmt->execute([$_SESSION['username'], "Deleted all {$voteCount['count']} votes for election ID: $electionId"]);
        
        $conn->commit();
        
        $message = "Successfully deleted {$voteCount['count']} votes from this election. Vote counts have been reset.";
        $messageType = "success";
        
        // Refresh stats
        if ($selectedElection) {
            $voteStats = $conn->prepare("SELECT COUNT(*) as total_votes FROM votes WHERE election_id = ?");
            $voteStats->execute([$electionId]);
            $electionStats = $voteStats->fetch(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $message = "Error deleting votes: " . $e->getMessage();
        $messageType = "error";
    }
}

// Handle Reset Election (Complete Reset - Votes and Candidate Votes)
if (isset($_POST['reset_election'])) {
    $electionId = intval($_POST['election_id']);
    
    if (!isset($_POST['confirm_reset']) || $_POST['confirm_reset'] !== 'yes') {
        $message = "Please confirm election reset by checking the confirmation box.";
        $messageType = "error";
    } else {
        try {
            $conn->beginTransaction();
            
            // Get vote count before deletion
            $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM votes WHERE election_id = ?");
            $countStmt->execute([$electionId]);
            $voteCount = $countStmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete all votes
            $deleteStmt = $conn->prepare("DELETE FROM votes WHERE election_id = ?");
            $deleteStmt->execute([$electionId]);
            
            // Reset contesters vote counts to 0
            $resetContesters = $conn->prepare("UPDATE contesters SET votes = 0 WHERE election_id = ?");
            $resetContesters->execute([$electionId]);
            
            // Optionally: Set election status back to 'upcoming' for re-election
            if (isset($_POST['reset_status'])) {
                $updateStatus = $conn->prepare("UPDATE elections SET status = 'upcoming' WHERE id = ?");
                $updateStatus->execute([$electionId]);
            }
            
            // Log the action
            $logStmt = $conn->prepare("
                INSERT INTO event_log (username, event_type, event_description) 
                VALUES (?, 'Reset Election', ?)
            ");
            $logStmt->execute([$_SESSION['username'], "Reset election ID: $electionId. Deleted {$voteCount['count']} votes and reset candidate votes."]);
            
            $conn->commit();
            
            $message = "Election has been reset successfully! All votes have been cleared and candidate vote counts reset to 0.";
            $messageType = "success";
            
            // Refresh stats
            if ($selectedElection) {
                $voteStats = $conn->prepare("SELECT COUNT(*) as total_votes FROM votes WHERE election_id = ?");
                $voteStats->execute([$electionId]);
                $electionStats = $voteStats->fetch(PDO::FETCH_ASSOC);
            }
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $message = "Error resetting election: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Handle Delete Single Vote
if (isset($_POST['delete_single_vote']) && isset($_POST['vote_id'])) {
    $voteId = intval($_POST['vote_id']);
    $electionId = intval($_POST['election_id']);
    
    try {
        // Get vote details before deletion
        $getVoteStmt = $conn->prepare("SELECT * FROM votes WHERE id = ?");
        $getVoteStmt->execute([$voteId]);
        $vote = $getVoteStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vote) {
            $conn->beginTransaction();
            
            // Delete the vote
            $deleteStmt = $conn->prepare("DELETE FROM votes WHERE id = ?");
            $deleteStmt->execute([$voteId]);
            
            // Decrement vote count in contesters table
            $updateContester = $conn->prepare("
                UPDATE contesters 
                SET votes = votes - 1 
                WHERE name = ? AND election_id = ? AND postname = ?
            ");
            $updateContester->execute([$vote['candidate_name'], $vote['election_id'], $vote['postname']]);
            
            // Log the action
            $logStmt = $conn->prepare("
                INSERT INTO event_log (username, event_type, event_description) 
                VALUES (?, 'Delete Single Vote', ?)
            ");
            $logStmt->execute([$_SESSION['username'], "Deleted vote ID: $voteId for candidate: {$vote['candidate_name']}"]);
            
            $conn->commit();
            
            $message = "Vote deleted successfully!";
            $messageType = "success";
            
            // Refresh stats
            $voteStats = $conn->prepare("SELECT COUNT(*) as total_votes FROM votes WHERE election_id = ?");
            $voteStats->execute([$electionId]);
            $electionStats = $voteStats->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = "Vote not found.";
            $messageType = "error";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $message = "Error deleting vote: " . $e->getMessage();
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Manage Votes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #6b7280;
            font-size: 16px;
        }
        
        /* Message */
        .message {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        
        .message i {
            font-size: 20px;
        }
        
        /* Election Selector */
        .election-selector {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .election-selector label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .election-selector select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }
        
        .election-selector select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            flex: 1;
            min-width: 200px;
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card.warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        }
        
        .stat-card.danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        }
        
        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 800;
            color: #1f2937;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6b7280;
            margin-top: 5px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        
        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }
        
        /* Votes Table */
        .votes-table-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .votes-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .votes-table th {
            background: #f3f4f6;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .votes-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .votes-table tr:hover {
            background: #f9fafb;
        }
        
        .delete-vote-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .delete-vote-btn:hover {
            background: #b91c1c;
        }
        
        .confirm-checkbox {
            margin-left: 10px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
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
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #9ca3af;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .stats-grid {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
            
            .votes-table {
                font-size: 12px;
            }
            
            .votes-table th,
            .votes-table td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-vote-yea"></i> Manage Votes</h1>
            <p>Delete or reset votes for elections</p>
        </div>
        
        <!-- Election Selector -->
        <div class="election-selector">
            <label for="electionSelect"><i class="fas fa-calendar-alt"></i> Select Election:</label>
            <select id="electionSelect" onchange="window.location.href='refreshdb.php?election_id=' + this.value;">
                <option value="">-- Select Election --</option>
                <?php foreach ($elections as $election): ?>
                    <option value="<?php echo $election['id']; ?>" <?php echo ($selectedElection && $selectedElection['id'] == $election['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($election['title']); ?> (<?php echo ucfirst($election['status']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($selectedElection): ?>
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="stat-value"><?php echo number_format($electionStats['total_votes'] ?? 0); ?></div>
                    <div class="stat-label">Total Votes Cast</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo number_format($electionStats['unique_voters'] ?? 0); ?></div>
                    <div class="stat-label">Unique Voters</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                    <div class="stat-value"><?php echo number_format($electionStats['total_positions'] ?? 0); ?></div>
                    <div class="stat-label">Positions Voted</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-value"><?php echo number_format($electionStats['total_votes'] ?? 0); ?></div>
                    <div class="stat-label">Votes to Delete</div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <form method="post" style="display: inline-block;" onsubmit="return confirm('⚠️ WARNING: This will delete ALL votes for this election. This action cannot be undone! Are you sure?')">
                    <input type="hidden" name="election_id" value="<?php echo $selectedElection['id']; ?>">
                    <button type="submit" name="delete_all_votes" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Delete All Votes
                    </button>
                </form>
                
                <form method="post" style="display: inline-block;" id="resetForm" onsubmit="return confirmReset()">
                    <input type="hidden" name="election_id" value="<?php echo $selectedElection['id']; ?>">
                    <label style="display: inline-flex; align-items: center; margin-left: 15px;">
                        <input type="checkbox" name="confirm_reset" value="yes" id="confirmReset">
                        <span style="margin-left: 5px; font-size: 12px;">I confirm reset</span>
                    </label>
                    <label style="display: inline-flex; align-items: center; margin-left: 15px;">
                        <input type="checkbox" name="reset_status" value="yes" checked>
                        <span style="margin-left: 5px; font-size: 12px;">Set status to 'Upcoming'</span>
                    </label>
                    <button type="submit" name="reset_election" class="btn btn-warning">
                        <i class="fas fa-sync-alt"></i> Reset Election for Re-Vote
                    </button>
                </form>
            </div>
            
            <!-- Votes List -->
            <div class="votes-table-container">
                <h3 style="padding: 20px 20px 0 20px;">
                    <i class="fas fa-list"></i> All Votes Cast
                    <span style="font-size: 14px; font-weight: normal; color: #6b7280;">(Click Delete to remove individual votes)</span>
                </h3>
                <?php
                // Fetch all votes for this election
                $votesListStmt = $conn->prepare("
                    SELECT v.*, u.name as voter_name 
                    FROM votes v 
                    LEFT JOIN users u ON v.username = u.username 
                    WHERE v.election_id = ? 
                    ORDER BY v.voted_at DESC
                ");
                $votesListStmt->execute([$selectedElection['id']]);
                $votesList = $votesListStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (empty($votesList)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No votes have been cast for this election yet.</p>
                    </div>
                <?php else: ?>
                    <table class="votes-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Voter</th>
                                <th>Position</th>
                                <th>Candidate Voted</th>
                                <th>Voted At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($votesList as $vote): ?>
                                <tr>
                                    <td><?php echo $vote['id']; ?></td>
                                    <td><?php echo htmlspecialchars($vote['voter_name'] ?? $vote['username']); ?></td>
                                    <td><?php echo htmlspecialchars($vote['postname']); ?></td>
                                    <td><?php echo htmlspecialchars($vote['candidate_name']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($vote['voted_at'])); ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Delete this vote?')">
                                            <input type="hidden" name="vote_id" value="<?php echo $vote['id']; ?>">
                                            <input type="hidden" name="election_id" value="<?php echo $selectedElection['id']; ?>">
                                            <button type="submit" name="delete_single_vote" class="delete-vote-btn">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php elseif (isset($_GET['election_id']) && !$selectedElection): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Election not found. Please select a valid election.</span>
            </div>
        <?php elseif (empty($elections)): ?>
            <div class="message error">
                <i class="fas fa-info-circle"></i>
                <span>No elections found. Please create an election first.</span>
            </div>
        <?php else: ?>
            <div class="message" style="background: #e0e7ff; color: #3730a3;">
                <i class="fas fa-info-circle"></i>
                <span>Please select an election from the dropdown above to manage its votes.</span>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function confirmReset() {
            const confirmCheckbox = document.getElementById('confirmReset');
            if (!confirmCheckbox.checked) {
                alert('Please confirm that you want to reset this election by checking the "I confirm reset" checkbox.');
                return false;
            }
            return confirm('⚠️ WARNING: This will DELETE ALL VOTES and reset candidate vote counts to 0. This action cannot be undone! Are you absolutely sure?');
        }
    </script>
</body>
</html>

<?php
session_start();
include("conn.php");

// Admin Authentication (optional - remove if not needed)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Handle AJAX request for user info - MUST be at the top and exit properly
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'get_user') {
    header('Content-Type: application/json');
    
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if ($userId <= 0) {
        echo json_encode(['error' => 'Invalid user ID']);
        exit();
    }
    
    try {
        // Fetch user basic info
        $userStmt = $conn->prepare("SELECT id, username, name as fullname, email, profile_photo, profile_photo_blob, profile_photo_type, date as registered_date FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['error' => 'User not found']);
            exit();
        }
        
        // Fetch user's election registrations
        $registrations = [];
        try {
            $regStmt = $conn->prepare("SELECT e.id, e.title, e.status FROM user_elections ue JOIN elections e ON ue.election_id = e.id WHERE ue.user_id = ?");
            $regStmt->execute([$userId]);
            $registrations = $regStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { 
            $registrations = []; 
        }

        // Fetch user's votes
        $votes = [];
        try {
            $votesStmt = $conn->prepare("SELECT election_id, postname, candidate_name, voted_at FROM votes WHERE user_id = ? ORDER BY voted_at DESC");
            $votesStmt->execute([$userId]);
            $votes = $votesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get election titles for votes
            foreach ($votes as &$vote) {
                $electionStmt = $conn->prepare("SELECT title FROM elections WHERE id = ?");
                $electionStmt->execute([$vote['election_id']]);
                $election = $electionStmt->fetch(PDO::FETCH_ASSOC);
                $vote['title'] = $election['title'] ?? 'Unknown Election';
            }
        } catch (Exception $e) { 
            $votes = []; 
        }
        
        // Fetch user's contest applications
        $contests = [];
        try {
            $contestsStmt = $conn->prepare("SELECT c.postname, e.title as election FROM contesters c JOIN elections e ON c.election_id = e.id WHERE c.user_id = ?");
            $contestsStmt->execute([$userId]);
            $contests = $contestsStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { 
            $contests = []; 
        }
        
        // Prepare profile photo
        $profilePhoto = null;
        $profilePhotoBlob = null;
        $profilePhotoType = null;
        
        if (!empty($user['profile_photo_blob'])) {
            $profilePhotoBlob = base64_encode($user['profile_photo_blob']);
            $profilePhotoType = $user['profile_photo_type'];
        } elseif (!empty($user['profile_photo'])) {
            $profilePhoto = $user['profile_photo'];
        }
        
        // Send JSON response
        echo json_encode([
            'success' => true,
            'id' => $user['id'],
            'username' => $user['username'],
            'fullname' => $user['fullname'] ?? 'N/A',
            'email' => $user['email'],
            'profile_photo' => $profilePhoto,
            'profile_photo_blob' => $profilePhotoBlob,
            'profile_photo_type' => $profilePhotoType,
            'registered_date' => $user['registered_date'],
            'registrations' => $registrations,
            'votes' => $votes,
            'contests' => $contests
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// If not AJAX request, continue with normal page display
// Fetch all users from the database
$users = [];
$errorMessage = "";

try {
    $stmt = $conn->prepare("SELECT id, username, name as full_name, email, profile_photo, date as registered_date FROM users ORDER BY id");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $errorMessage = "Error loading users. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | Voting System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>     

        .header {
            text-align: center;
            padding: 30px 20px;
            color: black;
            background: #333;
        }

        .header h2 {
            margin: 0;
            font-size: 32px;
            font-weight: 600;
        }

        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 16px;
        }

        .container {
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .content {
            padding: 30px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .view-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 8px 16px;
            text-align: center;
            display: inline-block;
            font-size: 13px;
            cursor: pointer;
            border-radius: 6px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .view-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s;
            overflow: hidden;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h4 {
            margin: 0;
            font-size: 20px;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.3s;
            line-height: 20px;
        }

        .close:hover {
            opacity: 0.8;
        }

        .modal-body {
            padding: 25px;
            max-height: 65vh;
            overflow-y: auto;
        }

        .profile-photo {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-photo img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .info-section {
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-section:last-child {
            border-bottom: none;
        }

        .info-section strong {
            color: #667eea;
            display: inline-block;
            min-width: 140px;
            font-size: 14px;
        }

        .info-list {
            margin: 8px 0 0 20px;
            padding-left: 20px;
        }

        .info-list li {
            margin: 8px 0;
            color: #555;
        }

        .election-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-upcoming {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #f8d7da;
            color: #721c24;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #667eea;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
        }

        @media screen and (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            th, td {
                padding: 10px 12px;
            }
            
            .info-section strong {
                display: block;
                margin-bottom: 5px;
                min-width: auto;
            }
            
            .modal-content {
                margin: 10% auto;
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Manage Users</h2>
        <p>View and manage all registered users in the system</p>
    </div>
    
    <div class="container">               
        <div class="content">
            <?php if ($errorMessage): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php elseif (empty($users)): ?>
                <div class="empty-state">
                    <div style="font-size: 48px;">👥</div>
                    <p>No users found in the database.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo $user['registered_date'] ? date('M d, Y', strtotime($user['registered_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <button class="view-btn" onclick="showUserInfo(<?php echo $user['id']; ?>)">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- User Info Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 id="modalTitle">User Information</h4>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="loading">
                    <div class="spinner"></div>
                    Loading user information...
                </div>
            </div>
        </div>
    </div>

    <script>
    async function showUserInfo(userId) {
        const modal = document.getElementById('userModal');
        const modalBody = document.getElementById('modalBody');
        const modalTitle = document.getElementById('modalTitle');
        
        modal.style.display = 'block';
        modalBody.innerHTML = `
            <div class="loading">
                <div class="spinner"></div>
                Loading user information...
            </div>
        `;
        modalTitle.textContent = `User Information - ID: ${userId}`;
        
        try {
            // IMPORTANT: Use the actual PHP file path, not the main.php router
            // Change 'users' to whatever your actual filename is (without .php)
            const response = await fetch('manage_users.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_user&user_id=' + userId
            });
            
            const text = await response.text();
            console.log('Raw response:', text);
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                modalBody.innerHTML = `<div class="error-message">❌ Server returned invalid response. Please check error logs.</div>`;
                return;
            }
            
            if (data.error) {
                modalBody.innerHTML = `<div class="error-message">❌ ${data.error}</div>`;
            } else if (data.success) {
                displayUserInfo(data);
            } else {
                modalBody.innerHTML = `<div class="error-message">❌ Failed to load user information.</div>`;
            }
        } catch (error) {
            console.error('Error fetching user info:', error);
            modalBody.innerHTML = `<div class="error-message">❌ Failed to load user information. Please try again.</div>`;
        }
    }
    
    function displayUserInfo(data) {
        const modalBody = document.getElementById('modalBody');
        
        // Handle profile photo
        let profilePhotoHtml = '';
        if (data.profile_photo_blob) {
            profilePhotoHtml = `<img src="data:image/${data.profile_photo_type};base64,${data.profile_photo_blob}" alt="Profile Photo">`;
        } else if (data.profile_photo && data.profile_photo !== '') {
            profilePhotoHtml = `<img src="uploads/${data.profile_photo}" alt="Profile Photo" onerror="this.src='faces/default.jpg'">`;
        } else {
            profilePhotoHtml = `<img src="faces/default.jpg" alt="Default Profile Photo">`;
        }
        
        // Format registrations with status
        let registrationsHtml = '';
        if (data.registrations && data.registrations.length > 0) {
            registrationsHtml = '<ul class="info-list">';
            data.registrations.forEach(reg => {
                let statusClass = '';
                if (reg.status === 'active') statusClass = 'status-active';
                else if (reg.status === 'upcoming') statusClass = 'status-upcoming';
                else if (reg.status === 'completed') statusClass = 'status-completed';
            
                registrationsHtml += `<li> ${escapeHtml(reg.title)} <span class="election-status ${statusClass}">${escapeHtml(reg.status)}</span></li>`;
            });
            registrationsHtml += '</ul>';
        } else {
            registrationsHtml = '<p style="color:#999; margin:5px 0 0 20px;">No election registrations</p>';
        }
        
        // Format votes
        let votesHtml = '';
        if (data.votes && data.votes.length > 0) {
            votesHtml = '<ul class="info-list">';
            data.votes.forEach(vote => {
                votesHtml += `<li>${escapeHtml(vote.title)} - ${escapeHtml(vote.postname)}${vote.voted_at ? ` <span style="color:#999; font-size:12px;">(${escapeHtml(vote.voted_at)})</span>` : ''}</li>`;
            });
            votesHtml += '</ul>';
        } else {
            votesHtml = '<p style="color:#999; margin:5px 0 0 20px;">No votes cast</p>';
        }
        
        // Format contests
        let contestsHtml = '';
        if (data.contests && data.contests.length > 0) {
            contestsHtml = '<ul class="info-list">';
            data.contests.forEach(contest => {
                contestsHtml += `<li> ${escapeHtml(contest.postname)} - ${escapeHtml(contest.election)}</li>`;
            });
            contestsHtml += '</ul>';
        } else {
            contestsHtml = '<p style="color:#999; margin:5px 0 0 20px;">Not contesting any position</p>';
        }
        
        modalBody.innerHTML = `
            <div class="profile-photo">
                ${profilePhotoHtml}
            </div>
            <div class="info-section">
                <strong> Username:</strong> ${escapeHtml(data.username)}
            </div>
            <div class="info-section">
                <strong>Full Name:</strong> ${escapeHtml(data.fullname)}
            </div>
            <div class="info-section">
                <strong> Email:</strong> ${escapeHtml(data.email)}
            </div>
            <div class="info-section">
                <strong> Registered:</strong> ${escapeHtml(data.registered_date) || 'N/A'}
            </div>
            <div class="info-section">
                <strong> Election Registrations:</strong>
                ${registrationsHtml}
            </div>
            <div class="info-section">
                <strong>Votes Cast:</strong>
                ${votesHtml}
            </div>
            <div class="info-section">
                <strong> Contesting For:</strong>
                ${contestsHtml}
            </div>
        `;
    }
    
    function closeModal() {
        document.getElementById('userModal').style.display = 'none';
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
    
    window.onclick = function(event) {
        const modal = document.getElementById('userModal');
        if (event.target === modal) {
            closeModal();
        }
    }
    
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
    </script>
</body>
</html>

<?php
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Function to get election data
function getElections($conn) {
    try {
        $sql = "SELECT * FROM elections ORDER BY id DESC";
        $result = $conn->query($sql);
        $elections = [];
        if ($result && $result->rowCount() > 0) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $elections[] = $row;
            }
        }
        return $elections;
    } catch (PDOException $e) {
        error_log("Error fetching elections: " . $e->getMessage());
        return [];
    }
}

// Function to update election status
function updateElectionStatus($conn, $electionId, $status) {
    try {
        $stmt = $conn->prepare("UPDATE elections SET status = ? WHERE id = ?");
        $stmt->execute([$status, $electionId]);
        return true;
    } catch (PDOException $e) {
        error_log("Error updating election status: " . $e->getMessage());
        return false;
    }
}

// Function to get candidate count for an election
function getCandidateCountForElection($conn, $electionId) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(c.id) as count FROM contesters c WHERE c.election_id = ?");
        $stmt->execute([$electionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['count'] : 0;
    } catch (PDOException $e) {
        error_log("Error getting candidate count: " . $e->getMessage());
        return 0;
    }
}

// Function to get registered voter count for an election
function getRegisteredVoterCountForElection($conn, $electionId) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as count FROM user_elections WHERE election_id = ?");
        $stmt->execute([$electionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['count'] : 0;
    } catch (PDOException $e) {
        error_log("Error getting voter count: " . $e->getMessage());
        return 0;
    }
}

// Handle election status updates
if (isset($_POST['update_election_status'])) {
    $electionId = $_POST['election_id'];
    $status = $_POST['status'];
    
    if (updateElectionStatus($conn, $electionId, $status)) {
        echo "<p style='color:green;'>Election status updated successfully.</p>";
    } else {
        echo "<p style='color:red;'>Error updating election status.</p>";
    }
}

// Add Election
if (isset($_POST['add_election'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'] ?? 'upcoming';
    
    // Validate dates
    if (empty($title)) {
        echo "<p style='color:red;'>Please enter an election title.</p>";
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        echo "<p style='color:red;'>End date cannot be earlier than start date.</p>";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO elections (title, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$title, $description, $start_date, $end_date, $status])) {
                echo "<p style='color:green;'>Election added successfully.</p>";
            } else {
                echo "<p style='color:red;'>Error adding election.</p>";
            }
        } catch (PDOException $e) {
            error_log("Error adding election: " . $e->getMessage());
            echo "<p style='color:red;'>Error adding election: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

// Get elections
$elections = getElections($conn);
?>

<div class="election-form-container">
    <h2>Manage Elections</h2>

    <form method="post">
        <input type="text" name="title" placeholder="Election Title" required>
        <textarea name="description" placeholder="Election Description" rows="3"></textarea>
        <input type="datetime-local" name="start_date" required>
        <input type="datetime-local" name="end_date" required>
        <select name="status" required>
            <option value="upcoming">Upcoming</option>
            <option value="active">Active</option>
            <option value="completed">Completed</option>
        </select>
        <button type="submit" name="add_election">Add Election</button>
    </form>
</div>

<?php if (empty($elections)): ?>
    <p style="text-align: center; color: #666;">No elections found. Create your first election above.</p>
<?php else: ?>
    <h2>Existing Elections</h2>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Candidates</th>
                    <th>Registered Voters</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($elections as $election) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($election['title']); ?></td>
                        <td><?php echo htmlspecialchars(substr($election['description'] ?? '', 0, 50)) . (strlen($election['description'] ?? '') > 50 ? '...' : ''); ?></td>
                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($election['start_date']))); ?></td>
                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($election['end_date']))); ?></td>
                        <td>
                            <span style="color: 
                                <?php 
                                echo $election['status'] == 'active' ? 'green' : ($election['status'] == 'completed' ? 'red' : 'orange'); 
                                ?>; 
                                font-weight: bold;">
                                <?php echo ucfirst(htmlspecialchars($election['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo getCandidateCountForElection($conn, $election['id']); ?></td>
                        <td><?php echo getRegisteredVoterCountForElection($conn, $election['id']); ?></td>
                        <td>
                            <form method="post" style="margin: 0;">
                                <input type="hidden" name="election_id" value="<?php echo $election['id']; ?>">
                                <select name="status" style="padding: 5px; margin-right: 5px;">
                                    <option value="upcoming" <?php echo $election['status'] == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="active" <?php echo $election['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo $election['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                                <button type="submit" name="update_election_status" style="padding: 5px 10px;">Update</button>
                            </form>
                        </td>
                                   </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<style>
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    th, td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }

    th {
        background-color: #f2f2f2;
        font-weight: bold;
    }
    
    tr:hover {
        background-color: #f5f5f5;
    }
    
    .election-form-container {
        width: 80%;
        max-width: 600px;
        margin: 20px auto;
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .election-form-container h2 {
        text-align: center;
        margin-bottom: 20px;
        color: #333;
    }

    .election-form-container form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .election-form-container input[type="text"],
    .election-form-container input[type="datetime-local"],
    .election-form-container textarea,
    .election-form-container select {
        flex: 1 1 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 16px;
    }

    .election-form-container textarea {
        resize: vertical;
        font-family: inherit;
    }

    .election-form-container button[type="submit"] {
        flex: 1 1 100%;
        background-color: #3498db;
        color: white;
        padding: 12px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        transition: background-color 0.3s ease;
    }

    .election-form-container button[type="submit"]:hover {
        background-color: #2980b9;
    }
    
    /* Style for the update buttons in the table */
    td form button {
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    
    td form button:hover {
        background-color: #45a049;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .election-form-container {
            width: 95%;
        }
        
        table {
            font-size: 12px;
        }
        
        th, td {
            padding: 5px;
        }
        
        td form select {
            font-size: 11px;
            padding: 3px;
        }
    }
</style>

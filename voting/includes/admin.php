<?php
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Fetch Summary Data
$electionsStmt = $conn->prepare("SELECT COUNT(*) FROM elections");
$electionsStmt->execute();
$electionsResult = $electionsStmt->get_result();
$totalElections = $electionsResult->fetch_row()[0];
$electionsStmt->close();

$usersStmt = $conn->prepare("SELECT COUNT(*) FROM users");
$usersStmt->execute();
$usersResult = $usersStmt->get_result();
$totalUsers = $usersResult->fetch_row()[0];
$usersStmt->close();

$votesStmt = $conn->prepare("SELECT COUNT(*) FROM votes");
$votesStmt->execute();
$votesResult = $votesStmt->get_result();
$totalVotes = $votesResult->fetch_row()[0];
$votesStmt->close();

$contestersStmt = $conn->prepare("SELECT COUNT(*) FROM contesters");
$contestersStmt->execute();
$contestersResult = $contestersStmt->get_result();
$totalContesters = $contestersResult->fetch_row()[0];
$contestersStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .admin-container { width: 90%; max-width: 1200px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .dashboard-item { background-color: #e6f7ff; padding: 20px; border-radius: 4px; text-align: center; }
        @media (max-width: 768px) { .admin-container { width: 95%; } }
    </style>
</head>
<body>
    <div class="admin-container">
        <h2>Admin Dashboard</h2>
        <div class="dashboard-grid">
            <div class="dashboard-item">
                <h3>Total Elections</h3>
                <p><?php echo $totalElections; ?></p>
            </div>
            <div class="dashboard-item">
                <h3>Total Registered Users</h3>
                <p><?php echo $totalUsers; ?></p>
            </div>
            <div class="dashboard-item">
                <h3>Total Votes</h3>
                <p><?php echo $totalVotes; ?></p>
            </div>
            <div class="dashboard-item">
                <h3>Total Contesters</h3>
                <p><?php echo $totalContesters; ?></p>
            </div>
        </div>
            </div>

   
</body>
</html>
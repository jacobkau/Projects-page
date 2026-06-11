<?php
session_start();
include("conn.php");

$sql = "SELECT id, username, full_name, email, profile_photo, created_at FROM users ORDER BY id";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OVMS | Registered Voters</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
    <link rel="icon" href="logo.jpg" type="image/x-icon">
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .members-container { width: 80%; max-width: 1000px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .members-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .members-table th, .members-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .members-table th { background-color: #f2f2f2; }
        .members-table img { max-height: 50px; }
        .navbar { background-color: #3498db; color: white; padding: 10px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin: 0 10px; }
        footer { background-color: #3498db; color: white; text-align: center; padding: 20px; margin-top: 20px; }
        footer ul { list-style: none; padding: 0; }
        footer li { display: inline; margin: 0 10px; }
        footer a { color: white; text-decoration: none; }
        @media (max-width: 768px) { .members-container { width: 95%; } }
        .election-list{padding-left: 15px;}
    </style>
</head>
<body>
    <header>
        <div class="navbar">
            <div class="title">
                <h1>Online Voting Management System</h1>
            </div>
            <div class="links">
                <a href="index.php">All Votes</a>
                <a href="vote.php">Vote</a>
                <a href="apply.php" class="active">Contest</a>
                <a href="contest.php">Contesters</a>
                <a href="members.php">Reg. Voters</a>
                <a href="my_applications.php">My applications</a>
                <a href="profile.php">My Profile</a>
                <a href="logout.php">Log out</a>
            </div>
        </div>
    </header>
    <div class="members-container">
        <h1>Registered Members</h1>
        <table class="members-table">
            <thead>
                <tr>
                    <th>Member ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Profile Photo</th>
                    <th>Date Registered</th>
                    <th>Registered Elections</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td data-label='Member ID'>" . $row['id'] . "</td>";
                        echo "<td data-label='Username'>" . $row['username'] . "</td>";
                        echo "<td data-label='Full Name'>" . $row['full_name'] . "</td>";
                        echo "<td data-label='Email'>" . $row['email'] . "</td>";
                        echo "<td data-label='Profile Photo'><img src='uploads/" . $row['profile_photo'] . "' alt='Profile Photo'></td>";
                        echo "<td data-label='Date Registered'>" . $row['created_at'] . "</td>";
                        echo "<td>";

                        // Fetch registered elections for the user
                        $electionsStmt = $conn->prepare("SELECT e.title FROM elections e JOIN user_elections ue ON e.id = ue.election_id WHERE ue.user_id = ?");
                        $electionsStmt->bind_param("i", $row['id']);
                        $electionsStmt->execute();
                        $electionsResult = $electionsStmt->get_result();

                        if ($electionsResult->num_rows > 0) {
                            echo "<ul class='election-list'>";
                            while ($electionRow = $electionsResult->fetch_assoc()) {
                                echo "<li>" . $electionRow['title'] . "</li>";
                            }
                            echo "</ul>";
                        } else {
                            echo "None";
                        }
                        $electionsStmt->close();

                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7'>No registered users yet.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <footer>
        <div>
        <h3>Faster links</h3>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="vote.php">Vote</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Log out</a></li>
            </ul>
        </div>
        <div style="text-align: center; padding: 10px; background-color: rgba(0,0,0,0.2); font-size: 0.8em; margin-top: 10px;">
            &copy; <?php echo date("Y"); ?> Jacob witty. All rights reserved.
        </div>
    </footer>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
<?php
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Function to get election data
function getElections($conn) {
    $sql = "SELECT * FROM elections";
    $result = $conn->query($sql);
    $elections = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $elections[] = $row;
        }
    }
    return $elections;
}

// Function to update election status
function updateElectionStatus($conn, $electionId, $votingOpen, $registrationOpen) {
    $stmt = $conn->prepare("UPDATE elections SET voting_open = ?, registration_open = ? WHERE id = ?");
    $stmt->bind_param("iii", $votingOpen, $registrationOpen, $electionId);
    $stmt->execute();
    $stmt->close();
}

// Function to get candidate count for an election
function getCandidateCountForElection($conn, $electionId) {
    $stmt = $conn->prepare("SELECT COUNT(c.id) FROM contesters c JOIN elections e ON c.id = e.id WHERE e.id = ?");
    $stmt->bind_param("i", $electionId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count;
}

// Function to get registered voter count for an election (You will most likely need to modify this query based on how voters are registered to elections.)
function getRegisteredVoterCountForElection($conn, $electionId) {
    //This is a simplified example. You will most likely need to modify this query based on how voters are registered to elections.
    //For example, if you have a table called voter_election that links voters to elections, you would use that table.
    $stmt = $conn->prepare("SELECT COUNT(u.id) FROM users u JOIN elections e ON 1=1 WHERE e.id = ?"); //This is a simple example.
    $stmt->bind_param("i", $electionId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count;
}

// Handle election status updates
if (isset($_POST['update_election_status'])) {
    $electionId = $_POST['election_id'];
    $votingOpen = isset($_POST['voting_open']) ? 1 : 0;
    $registrationOpen = isset($_POST['registration_open']) ? 1 : 0;
    updateElectionStatus($conn, $electionId, $votingOpen, $registrationOpen);
    echo "<p style='color:green;'>Election status updated.</p>";
}

// Get elections
$elections = getElections($conn);


// Add Election
if (isset($_POST['add_election'])) {
    $title = $_POST['title'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    $stmt = $conn->prepare("INSERT INTO elections (title, start_date, end_date) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $start_date, $end_date);

    if ($stmt->execute()) {
        echo "<p style='color:green;'>Election added successfully.</p>";
    } else {
        echo "<p style='color:red;'>Error adding election: " . $stmt->error . "</p>";
    }

    $stmt->close();
}

// List Elections
$sql = "SELECT * FROM elections";
$result = $conn->query($sql);
?>

<div class="election-form-container">
    <h2>Manage Elections</h2>

    <form method="post">
        <input type="text" name="title" placeholder="Election Title" required>
        <input type="date" name="start_date" required>
        <input type="date" name="end_date" required>
        <button type="submit" name="add_election">Add Election</button>
    </form>
</div>



<h2>Types of Elections</h2>
<table>
    <thead>
        <tr>
            <th>Title</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Voting Open</th>
            <th>Registration Open</th>
            <th>Candidates</th>
            <th>Registered Voters</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($elections as $election) : ?>
            <tr>
                <td><?php echo $election['title']; ?></td>
                <td><?php echo $election['start_date']; ?></td>
                <td><?php echo $election['end_date']; ?></td>
                <td><?php echo $election['voting_open'] ? 'Yes' : 'No'; ?></td>
                <td><?php echo $election['registration_open'] ? 'Yes' : 'No'; ?></td>
                <td><?php echo getCandidateCountForElection($conn, $election['id']); ?></td>
                <td><?php echo getRegisteredVoterCountForElection($conn, $election['id']); ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="election_id" value="<?php echo $election['id']; ?>">
                        <label><input type="checkbox" name="voting_open" <?php if ($election['voting_open']) echo 'checked'; ?>> Voting Open</label><br>
                        <label><input type="checkbox" name="registration_open" <?php if ($election['registration_open']) echo 'checked'; ?>> Registration Open</label><br>
                        <button type="submit" name="update_election_status">Update</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

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
    .election-form-container input[type="date"] {
        flex: 1 1 45%; /* Distribute inputs evenly */
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 16px;
    }

    .election-form-container button[type="submit"] {
        flex: 1 1 100%; /* Full width for the button */
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

    /* Responsive adjustments */
    @media (max-width: 600px) {
        .election-form-container input[type="text"],
        .election-form-container input[type="date"] {
            flex: 1 1 100%; /* Stack inputs on smaller screens */
        }
    }
</style>


<?php
include("conn.php");

// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Function to get election type data
function getElectionTypes($conn) {
    $sql = "SELECT * FROM election_types";
    $result = $conn->query($sql);
    $electionTypes = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $electionTypes[] = $row;
        }
    }
    return $electionTypes;
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

// Function to get registered member count
function getRegisteredMemberCount($conn) {
    $sql = "SELECT COUNT(*) as count FROM users";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Function to get candidate count
function getCandidateCount($conn) {
    $sql = "SELECT COUNT(*) as count FROM contesters";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Get Data
$electionTypes = getElectionTypes($conn);
$elections = getElections($conn);
$memberCount = getRegisteredMemberCount($conn);
$candidateCount = getCandidateCount($conn);

?>

<h2>Voting System Overview</h2>

<h3>Election Types</h3>
<table>
    <thead>
        <tr>
            <th>Type Name</th>
            <th>Voting Enabled</th>
            <th>Registration Enabled</th>
            <th>Results Visible</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($electionTypes as $type) : ?>
            <tr>
                <td><?php echo $type['type_name']; ?></td>
                <td><?php echo $type['voting_enabled'] ? 'Yes' : 'No'; ?></td>
                <td><?php echo $type['registration_enabled'] ? 'Yes' : 'No'; ?></td>
                <td><?php echo $type['results_visible'] ? 'Yes' : 'No'; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h3>Elections</h3>
<table>
    <thead>
        <tr>
            <th>Title</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Election Type ID</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($elections as $election) : ?>
            <tr>
                <td><?php echo $election['title']; ?></td>
                <td><?php echo $election['start_date']; ?></td>
                <td><?php echo $election['end_date']; ?></td>
                <td><?php echo $election['election_type_id']; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h3>System Statistics</h3>
<p>Registered Members: <?php echo $memberCount; ?></p>
<p>Registered Candidates: <?php echo $candidateCount; ?></p>

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
</style>
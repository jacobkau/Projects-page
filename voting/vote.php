<?php
// vote.php (Full PHP Code)
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// Fetch Open Elections
$openElectionsStmt = $conn->prepare("SELECT id, title FROM elections WHERE status = active");
$openElectionsStmt->execute();
$openElectionsResult = $openElectionsStmt->get_result();
$openElectionsStmt->close();

// Get Election ID from URL or Session (if any)
$electionId = isset($_GET['election_id']) ? intval($_GET['election_id']) : (isset($_SESSION['selected_election']) ? $_SESSION['selected_election'] : null);

if (isset($_GET['election_id'])) {
    $_SESSION['selected_election'] = $electionId; // Store selected election in session
}

$showVoteForm = false; // Flag to control form display
$noPostsMessage = "";
$errorMessage = "";
$successMessage = "";

if ($electionId !== null) {
    // Check if Election Exists
    $electionExistsStmt = $conn->prepare("SELECT 1 FROM elections WHERE id = ?");
    $electionExistsStmt->bind_param("i", $electionId);
    $electionExistsStmt->execute();
    $electionExistsStmt->store_result();

    if ($electionExistsStmt->num_rows === 0) {
        $errorMessage = "<p style='color:red;'>The selected election does not exist.</p>";
    } else {
        $electionExistsStmt->close();

        // Check if Voting is Open
        $votingOpenStmt = $conn->prepare("SELECT voting_open FROM elections WHERE id = ?");
        $votingOpenStmt->bind_param("i", $electionId);
        $votingOpenStmt->execute();
        $votingOpenStmt->bind_result($votingOpen);
        $votingOpenStmt->fetch();
        $votingOpenStmt->close();

        if ($votingOpen !== 1) {
            $errorMessage = "<p style='color:red;'>Voting is currently closed for this election.</p>";
        } else {
            // Check if User is Registered
            $userRegisteredStmt = $conn->prepare("SELECT 1 FROM users WHERE username = ?");
            $userRegisteredStmt->bind_param("s", $_SESSION["username"]);
            $userRegisteredStmt->execute();
            $userRegisteredStmt->store_result();

            if ($userRegisteredStmt->num_rows === 0) {
                $errorMessage = "<p style='color:red;'>You are not a registered voter.</p>";
            } else {
                $userRegisteredStmt->close();

                // Check if User Already Voted
                $alreadyVotedStmt = $conn->prepare("SELECT 1 FROM votes WHERE username = ? AND election_id = ?");
                $alreadyVotedStmt->bind_param("si", $_SESSION["username"], $electionId);
                $alreadyVotedStmt->execute();
                $alreadyVotedStmt->store_result();

                if ($alreadyVotedStmt->num_rows > 0) {
                    $errorMessage = "<p style='color:red;'>You have already voted in this election.</p>";
                } else {
                    $alreadyVotedStmt->close();

                    // Check if there are any posts for the election
                    $postCheckStmt = $conn->prepare("SELECT COUNT(*) FROM contesters WHERE election_id = ?");
                    $postCheckStmt->bind_param("i", $electionId);
                    $postCheckStmt->execute();
                    $postCheckStmt->bind_result($postCount);
                    $postCheckStmt->fetch();
                    $postCheckStmt->close();

                    if ($postCount === 0) {
                        $noPostsMessage = "<p style='color:red;'>There are no posts available for this election.</p>";
                    } else {
                        $showVoteForm = true; // Show the form
                    }
                }
            }
        }
    }
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && $showVoteForm) {
    $votes = $_POST;
    unset($votes['submit']); // Remove the submit button value

    // Check if all posts were voted for
    $postStmt = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ?");
    $postStmt->bind_param("i", $electionId);
    $postStmt->execute();
    $postResult = $postStmt->get_result();

    $allPostsVoted = true;
    while ($postRow = $postResult->fetch_assoc()) {
        $postName = strtolower(str_replace(' ', '_', $postRow['postname']));
        if (!isset($votes[$postName]) || empty($votes[$postName])) {
            $allPostsVoted = false;
            $errorMessage = "<p style='color:red;'>Please vote for all available posts.</p>";
            break;
        }
    }
    $postStmt->close();

    if ($allPostsVoted) {
        $voteSuccess = true;
        foreach ($votes as $postName => $candidateName) {
            $voteStmt = $conn->prepare("INSERT INTO votes (username, election_id, postname, candidate_name) VALUES (?, ?, ?, ?)");
            $replacedPostName = str_replace('_', ' ', $postName);
            $voteStmt->bind_param("siss", $_SESSION["username"], $electionId, $replacedPostName, $candidateName);

            if ($voteStmt->execute()) {
                $voteStmt->close();

                $updateStmt = $conn->prepare("UPDATE contesters SET votes = votes + 1 WHERE name = ? AND election_id = ? AND postname = ?");
                $updateStmt->bind_param("sis", $candidateName, $electionId, $replacedPostName);

                if ($updateStmt->execute()) {
                    $updateStmt->close();
                } else {
                    error_log("Update contesters failed: " . $updateStmt->error);
                    $voteSuccess = false;
                    $updateStmt->close();
                    break;
                }
            } else {
                error_log("Vote insertion failed: " . $voteStmt->error);
                $voteSuccess = false;
                $voteStmt->close();
                break;
            }
        }

        if ($voteSuccess) {
            $successMessage = "<p style='color:green;'>Vote submitted successfully!</p>";
        } else {
            $errorMessage = "<p style='color:red;'>Error submitting vote. Please try again.</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OVMS | Vote</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
    <link rel="icon" href="logo.jpg" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
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
                <a href="apply.php">Contest</a>
                <a href="contest.php">Contesters</a>
                <a href="members.php">Reg. Voters</a>
                <a href="my_applications.php">My applications</a>
                <a href="profile.php">My Profile</a>
                <a href="logout.php">Log out</a>
            </div>
        </div>
    </header>

    <div class="vote-container">
        <h2>Select an Election to Vote</h2>
        <select id="electionSelect" onchange="window.location.href='vote.php?election_id=' + this.value;">
            <option value="">Select Election</option>
            <?php
            while ($row = $openElectionsResult->fetch_assoc()) {
                echo "<option value='" . $row['id'] . "'" . ($electionId == $row['id'] ? ' selected' : '') . ">" . $row['title'] . "</option>";
            }
            ?>
        </select>

        <?php if (!empty($errorMessage)): ?>
            <?php echo $errorMessage; ?>
        <?php endif; ?>

        <?php if (!empty($noPostsMessage)): ?>
            <?php echo $noPostsMessage; ?>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <?php echo $successMessage; ?>
        <?php endif; ?>

        <?php if ($showVoteForm): ?>
            <form method="post" action="vote.php?election_id=<?php echo $electionId; ?>" class="vote-form">
                <center><h1 style="color:grey">Vote for Your Favorite Option</h1></center>
                <?php
                // Fetch Posts and Candidates Dynamically
                $postStmt = $conn->prepare("SELECT DISTINCT postname FROM contesters WHERE election_id = ?");
                $postStmt->bind_param("i", $electionId);
                $postStmt->execute();
                $postResult = $postStmt->get_result();

                if ($postResult->num_rows > 0) {
                    while ($postRow = $postResult->fetch_assoc()) {
                        $postName = $postRow['postname'];
                        $candidateStmt = $conn->prepare("SELECT name FROM contesters WHERE postname = ? AND election_id = ?");
                        $candidateStmt->bind_param("si", $postName, $electionId);
                        $candidateStmt->execute();
                        $candidateResult = $candidateStmt->get_result();

                        echo "<label style='color:black' for='" . strtolower(str_replace(' ', '_', $postName)) . "'>" . $postName . "</label>";
                        echo "<select name='" . strtolower(str_replace(' ', '_', $postName)) . "' id='" . strtolower(str_replace(' ', '_', $postName)) . "'>";
                        echo "<option value=''>Select</option>";
                        while ($row = $candidateResult->fetch_assoc()) {
                            echo "<option value='" . $row['name'] . "'>" . $row['name'] . "</option>";
                        }
                        echo "</select>";
                        $candidateStmt->close();
                    }
                } else {
                    echo "<p>No posts available for this election.</p>";
                }
                $postStmt->close();
                ?>
                <center>
                    <input type="submit" name="submit" value="Vote">
                </center>
            </form>
        <?php endif; ?>
    </div>
    <footer>
    <div>
        <h2>Faster Links</h2>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="vote.php">Vote</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="logout.php">Log out</a></li>
        </ul>
    </div>
    <div style="text-align: center; padding: 10px; background-color: #f0f0f0; font-size: 0.8em; color: #555; margin-top: 20px;">
        &copy; <?php echo date("Y"); ?> Jacob witty. All rights reserved.
    </div>
</footer>

</body>
</html>
<style>
/* style.css (CSS Styles) */

body {
    font-family: sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f4f4f4;
}

.navbar {
    background-color: #3498db;
    color: white;
    padding: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.navbar a {
    color: white;
    text-decoration: none;
    margin: 0 10px;
}

.vote-container {
    width: 80%;
    max-width: 600px;
    margin: 20px auto;
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

select, input[type="submit"] {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
}

input[type="submit"] {
    background-color: #2ecc71;
    color: white;
    cursor: pointer;
}

input[type="submit"]:hover {
    background-color: #27ae60;
}

h2, h3 {
    color: #333;
    margin-bottom: 10px;
}

p {
    color: #555;
}

@media (max-width: 768px) {
    .vote-container {
        width: 95%;
    }
}

/* Footer Styles */
footer {
    background-color: #708896;
    padding: 20px 0;
    text-align: center;
    color:white;
}

footer ul {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    list-style: none;
    padding:0;
}

footer li {
    margin: 0 10px;
}

footer a {
    text-decoration: none;
    color: #fff;
    font-size: 1em;
}

footer div:last-child{
    background-color: rgba(0,0,0,0.2);
    margin-top:20px;
    padding:10px;
    font-size:0.8em;
}
</style>

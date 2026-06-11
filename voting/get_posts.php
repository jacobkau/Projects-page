<?php
include("conn.php");

if (isset($_GET['election_id']) && is_numeric($_GET['election_id'])) {
    $electionId = intval($_GET['election_id']);

    $stmt = $conn->prepare("SELECT id, postname FROM election_posts WHERE election_id = ?");
    $stmt->bind_param("i", $electionId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<option value="' . htmlspecialchars($row['postname']) . '">' . htmlspecialchars($row['postname']) . '</option>';
        }
    } else {
        echo '<option value="">No posts available</option>';
    }
    $stmt->close();
} else {
    echo '<option value="">Invalid election ID</option>';
}
?>
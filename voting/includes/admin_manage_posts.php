<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("conn.php");
// Admin Authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Fetch Elections and their Posts
try {
    $electionsStmt = $conn->prepare("SELECT id, title FROM elections");
    if (!$electionsStmt) throw new Exception("Prepare failed: " . $conn->error);
    $electionsStmt->execute();
    $electionsResult = $electionsStmt->get_result();
    $elections = $electionsResult->fetch_all(MYSQLI_ASSOC);
    $electionsStmt->close();

    $postsByElection = [];
    foreach ($elections as $election) {
        $electionId = $election['id'];
        $postsStmt = $conn->prepare("SELECT id, postname FROM election_posts WHERE election_id = ?");
        if (!$postsStmt) throw new Exception("Prepare failed: " . $conn->error);
        $postsStmt->bind_param("i", $electionId);
        $postsStmt->execute();
        $postsResult = $postsStmt->get_result();
        $postsByElection[$electionId] = $postsResult->fetch_all(MYSQLI_ASSOC);
        $postsStmt->close();
    }
} catch (Exception $e) {
    error_log("Election/Post fetch error: " . $e->getMessage());
    echo "Error fetching election data. Please try again.";
    exit();
}

// Handle AJAX Requests for Adding and Deleting Posts
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] == "add_post") {
        $electionId = intval($_POST['election_id']);
        $postName = trim($_POST['postname']);

        if (!empty($postName)) {
            try {
                $addStmt = $conn->prepare("INSERT INTO election_posts (election_id, postname) VALUES (?, ?)");
                if (!$addStmt) throw new Exception("Prepare failed: " . $conn->error);
                $addStmt->bind_param("is", $electionId, $postName);
                if ($addStmt->execute()) {
                    echo json_encode(["status" => "success", "id" => $addStmt->insert_id, "postname" => htmlspecialchars($postName)]);
                } else {
                    throw new Exception("Execute failed: " . $addStmt->error);
                }
                $addStmt->close();
            } catch (Exception $e) {
                error_log("Post insert error: " . $e->getMessage());
                echo json_encode(["status" => "error", "message" => "Error adding post. Please try again."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Post name cannot be empty."]);
        }
        exit();
    }

    if ($_POST['action'] == "delete_post") {
        $deleteId = intval($_POST['delete_id']);
        if ($deleteId > 0) {
            try {
                $deleteStmt = $conn->prepare("DELETE FROM election_posts WHERE id = ?");
                if (!$deleteStmt) throw new Exception("Prepare failed: " . $conn->error);
                $deleteStmt->bind_param("i", $deleteId);
                if ($deleteStmt->execute()) {
                    echo json_encode(["status" => "success"]);
                } else {
                    throw new Exception("Execute failed: " . $deleteStmt->error);
                }
                $deleteStmt->close();
            } catch (Exception $e) {
                error_log("Post delete error: " . $e->getMessage());
                echo json_encode(["status" => "error", "message" => "Database error"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid ID"]);
        }
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Manage Election Posts</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .admin-container { width: 80%; max-width: 1000px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .election-section { margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; border-radius: 4px; }
        .post-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .post-table th, .post-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .post-table th { background-color: #3498db; color: white; }
        input[type="text"], button { padding: 8px; margin-top: 5px; }
        .delete-btn { color: red; text-decoration: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="admin-container">
        <h2>Manage Election Posts</h2>
        <?php foreach ($elections as $election): ?>
            <div class="election-section">
                <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                <table class="post-table">
                    <thead>
                        <tr>
                            <th>Post Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="post-list-<?php echo $election['id']; ?>">
                        <?php foreach ($postsByElection[$election['id']] as $post): ?>
                            <tr id="post-<?php echo $post['id']; ?>">
                                <td><?php echo htmlspecialchars($post['postname']); ?></td>
                                <td>
                                    <span class="delete-btn" data-id="<?php echo $post['id']; ?>">Delete</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <form class="add-post-form" data-election="<?php echo $election['id']; ?>">
                    <input type="text" name="postname" placeholder="Enter new post name" required>
                    <button type="submit">Add Post</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

   <script>
$(document).ready(function() {
    // Delete Post
    $(document).on("click", ".delete-btn", function() {
        if (!confirm("Delete this post?")) return;
        let postId = $(this).data("id");
        $.post("admin_manage_posts.php", { action: "delete_post", delete_id: postId }, function(response) {
            if (response.status === "success") {
                $("#post-" + postId).fadeOut(500, function() { $(this).remove(); });
            } else {
                alert("Error: " + response.message);
            }
        }, "json").fail(function(xhr) {
            alert("Request failed: " + xhr.responseText);
        });
    });

    // Add Post
    $(".add-post-form").on("submit", function(e) {
        e.preventDefault(); // Prevent default form submission
        let electionId = $(this).data("election");
        let postName = $(this).find("input[name='postname']").val();

        $.post("admin_manage_posts.php", { action: "add_post", election_id: electionId, postname: postName }, function(response) {
            if (response.status === "success") {
                let newRow = `<tr id="post-${response.id}"><td>${response.postname}</td><td><span class="delete-btn" data-id="${response.id}">Delete</span></td></tr>`;
                $("#post-list-" + electionId).append(newRow);
                $(e.target).find("input[name='postname']").val(""); // Clear input
            } else {
                alert("Error: " + response.message);
            }
        }, "json").fail(function(xhr) {
            alert("Request failed: " + xhr.responseText);
        });
    });
});
</script>
</body>
</html>

<?php
include("conn.php"); // Ensure database connection is included

// Ensure $users is always initialized
$users = [];

try {
    // Fetch users from the database
    $stmt = $conn->prepare("SELECT id,full_name, username, email FROM users");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $users = $result->fetch_all(MYSQLI_ASSOC);
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
}
?>

<h2>Manage Users</h2>

<?php if (empty($users)): ?>
    <p style="color: red;">No users found.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
            <th>No.</th>
                <th>Username</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                 <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <button onclick="showUserInfo(<?php echo $user['id']; ?>)">View Info</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="modal" id="user-info-modal">
    <div class="modal-content">
        <span class="close" onclick="hideUserInfo()">&times;</span>
        <h4 id="modal-title">User Info</h4>
        <div id="modal-content"></div>
    </div>
</div>

<script>
function showUserInfo(userId) {
    var modal = document.getElementById('user-info-modal');
    var contentDiv = document.getElementById('modal-content');
    var title = document.getElementById('modal-title');

    fetch('get_user_info.php?id=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                contentDiv.innerHTML = `<p style="color:red;">${data.error}</p>`;
            } else {
                let registrations = data.registrations.length > 0 ? data.registrations.map(reg => `<li>${reg}</li>`).join('') : "<li>No registrations.</li>";
                let votes = data.votes.length > 0 ? data.votes.map(vote => `<li>${vote.title} (${vote.date})</li>`).join('') : "<li>No votes.</li>";
                let contests = data.contests.length > 0 ? data.contests.map(contest => `<li>${contest.postname} (${contest.election})</li>`).join('') : "<li>No contests.</li>";

                // Add profile photo to the top of the modal
                let profilePhotoHtml = '';
                if (data.profile_photo && data.profile_photo !== '') { // Check if profile_photo exists and is not empty
                    profilePhotoHtml = `<img src="uploads/${data.profile_photo}" alt="Profile Photo" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; display: block; margin: 0 auto 10px;">`;
                } else {
                    profilePhotoHtml = `<img src="faces/default.jpg" alt="Default Profile Photo" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; display: block; margin: 0 auto 10px;">`;
                }

                contentDiv.innerHTML = `
                    ${profilePhotoHtml}
                    <p><strong>Username:</strong> ${data.username}</p>
                    <p><strong>Full Name:</strong> ${data.fullname}</p>
                    <p><strong>Email:</strong> ${data.email}</p>
                    <p><strong>Registrations:</strong></p>
                    <ul>${registrations}</ul>
                    <p><strong>Votes:</strong></p>
                    <ul>${votes}</ul>
                    <p><strong>Contests:</strong></p>
                    <ul>${contests}</ul>
                `;
            }
            modal.style.display = 'block';
            title.textContent = "User Info for User ID: " + userId;
        })
        .catch(error => {
            console.error('Error fetching user info:', error);
            contentDiv.innerHTML = "<p style='color:red;'>Failed to load user info.</p>";
        });
}
function hideUserInfo() {
    document.getElementById('user-info-modal').style.display = 'none';
}
</script>
<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: white;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 50%;
}

.close {
    color: red;
    float: right;
    font-size: 28px;
    font-weight: bold;
}

.close:hover,
close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    font-family: Arial, sans-serif;
    border: 1px solid #ddd; /* Light border around the table */
}

th, td {
    border: 1px solid #ddd; /* Light borders for cells */
    padding: 12px 15px;
    text-align: left;
}

th {
    background-color: #f2f2f2; /* Light gray background for headers */
    font-weight: bold;
    color: #333; /* Darker text for headers */
}

tr:nth-child(even) {
    background-color: #f9f9f9; /* Slightly different background for even rows */
}

tr:hover {
    background-color: #e0f7fa; /* Light blue on hover */
}

/* Optional: Style for the "Actions" column buttons */
td button {
    background-color: #4CAF50; /* Green background */
    border: none;
    color: white;
    padding: 8px 16px;
    text-align: center;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
    margin: 4px 2px;
    cursor: pointer;
    border-radius: 4px; /* Rounded corners for buttons */
}

td button:hover {
    background-color: #3e8e41; /* Darker green on hover */
}

/* Optional: Responsive table styling */
@media screen and (max-width: 600px) {
    table, thead, tbody, th, td, tr {
        display: block;
    }

    thead tr {
        position: absolute;
        top: -9999px;
        left: -9999px;
    }

    tr { border: 1px solid #ccc; }

    td {
        border: none;
        border-bottom: 1px solid #eee;
        position: relative;
        padding-left: 50%;
    }

    td:before {
        position: absolute;
        top: 6px;
        left: 6px;
        width: 45%;
        padding-right: 10px;
        white-space: nowrap;
        content: attr(data-label);
        font-weight: bold;
    }
}
</style>
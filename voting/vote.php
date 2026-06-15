<?php
// vote.php - With candidate images in dropdown
session_start();
include("conn.php");

if (empty($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION["username"];

// Get user ID
$userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$userStmt->execute([$username]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$userId = $user ? $user['id'] : null;

// Fetch Open Elections
$openElectionsStmt = $conn->prepare("SELECT id, title FROM elections WHERE status = 'active'");
$openElectionsStmt->execute();
$openElections = $openElectionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get Election ID from URL
$electionId = isset($_GET['election_id']) ? intval($_GET['election_id']) : (isset($_SESSION['selected_election']) ? $_SESSION['selected_election'] : null);

if (isset($_GET['election_id'])) {
    $_SESSION['selected_election'] = $electionId;
}

$showVoteForm = false;
$errorMessage = "";
$successMessage = "";
$election = null;

if ($electionId !== null) {
    // Check if Election Exists and is Active
    $electionCheckStmt = $conn->prepare("SELECT id, title, status FROM elections WHERE id = ?");
    $electionCheckStmt->execute([$electionId]);
    $election = $electionCheckStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$election) {
        $errorMessage = "The selected election does not exist.";
    } elseif ($election['status'] !== 'active') {
        $errorMessage = "Voting is currently closed for this election. Status: " . $election['status'];
    } else {
        // Check if User is Registered for this election
        $userRegisteredStmt = $conn->prepare("SELECT 1 FROM user_elections WHERE user_id = ? AND election_id = ?");
        $userRegisteredStmt->execute([$userId, $electionId]);
        
        if ($userRegisteredStmt->rowCount() === 0) {
            $errorMessage = "You are not registered for this election. Please register first.";
        } else {
            // Check if User Already Voted
            $alreadyVotedStmt = $conn->prepare("SELECT 1 FROM votes WHERE username = ? AND election_id = ?");
            $alreadyVotedStmt->execute([$username, $electionId]);
            
            if ($alreadyVotedStmt->rowCount() > 0) {
                $errorMessage = "You have already voted in this election.";
            } else {
                // Get posts for this election from election_posts table
                $postsStmt = $conn->prepare("SELECT id, postname FROM election_posts WHERE election_id = ? ORDER BY postname");
                $postsStmt->execute([$electionId]);
                $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($posts)) {
                    $errorMessage = "No positions have been set up for this election yet. Please contact the administrator.";
                } else {
                    $showVoteForm = true;
                }
            }
        }
    }
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit']) && $showVoteForm) {
    $votes = $_POST;
    unset($votes['submit']);
    
    // Get all posts for this election
    $postsStmt = $conn->prepare("SELECT postname FROM election_posts WHERE election_id = ?");
    $postsStmt->execute([$electionId]);
    $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Validate all posts were voted for
    $allPostsVoted = true;
    $missingPosts = [];
    
    foreach ($posts as $post) {
        $postKey = strtolower(str_replace(' ', '_', $post['postname']));
        if (!isset($votes[$postKey]) || empty($votes[$postKey])) {
            $allPostsVoted = false;
            $missingPosts[] = $post['postname'];
        }
    }
    
    if (!$allPostsVoted) {
        $errorMessage = "Please vote for all positions: " . implode(', ', $missingPosts);
    } else {
        $voteSuccess = true;
        $conn->beginTransaction();
        
        try {
            foreach ($votes as $postKey => $candidateId) {
                // Get candidate name from ID
                $candidateStmt = $conn->prepare("SELECT name FROM contesters WHERE id = ?");
                $candidateStmt->execute([$candidateId]);
                $candidate = $candidateStmt->fetch(PDO::FETCH_ASSOC);
                $candidateName = $candidate ? $candidate['name'] : '';
                
                $originalPostName = str_replace('_', ' ', $postKey);
                
                // Insert vote into votes table
                $voteStmt = $conn->prepare("INSERT INTO votes (username, election_id, postname, candidate_name, voted_at) VALUES (?, ?, ?, ?, NOW())");
                if (!$voteStmt->execute([$username, $electionId, $originalPostName, $candidateName])) {
                    throw new Exception("Failed to insert vote");
                }
                
                // Update vote count in contesters table
                $updateStmt = $conn->prepare("UPDATE contesters SET votes = votes + 1 WHERE id = ?");
                $updateStmt->execute([$candidateId]);
            }
            
            $conn->commit();
            $successMessage = "✓ Vote submitted successfully! Thank you for voting.";
            $showVoteForm = false;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errorMessage = "Error submitting your vote. Please try again.";
            error_log("Vote error: " . $e->getMessage());
        }
    }
}

// Helper function to get candidate image
function getCandidateImage($candidate) {
    if (!empty($candidate['profile_photo_blob'])) {
        return 'data:image/' . $candidate['profile_photo_type'] . ';base64,' . base64_encode($candidate['profile_photo_blob']);
    } elseif (!empty($candidate['profile_photo']) && file_exists('faces/' . $candidate['profile_photo'])) {
        return 'faces/' . htmlspecialchars($candidate['profile_photo']);
    }
    return 'faces/default.jpg';
}
?>

<?php include("header.php"); ?>

<style>
    .vote-container { 
        width: 90%; 
        max-width: 800px; 
        margin: 40px auto; 
        background-color: white; 
        padding: 30px; 
        border-radius: 16px; 
        box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
    }
    .vote-container h2 { color: #333; margin-bottom: 10px; text-align: center; }
    .vote-container .subtitle { text-align: center; color: #666; margin-bottom: 25px; font-size: 14px; }
    label { font-weight: 600; color: #333; margin-top: 15px; margin-bottom: 5px; display: block; }
    
    /* Custom Select with Images */
    .candidate-select {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 14px;
        font-family: inherit;
        background-color: white;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .candidate-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }
    
    /* Styled option with image (works in most modern browsers) */
    .candidate-option {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px;
    }
    
    .candidate-option img {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        object-fit: cover;
        vertical-align: middle;
        margin-right: 10px;
    }
    
    /* Card-based candidate selection for better visual */
    .candidate-cards {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin: 10px 0;
    }
    
    .candidate-card {
        flex: 1;
        min-width: 150px;
        background: #f9fafb;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .candidate-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .candidate-card.selected {
        border-color: #667eea;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .candidate-card img {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 10px;
        border: 3px solid white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .candidate-card .candidate-name {
        font-weight: 600;
        margin-top: 8px;
    }
    
    .candidate-card.selected .candidate-name {
        color: white;
    }
    
    input[type="submit"] { 
        width: 100%; 
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
        color: white; 
        padding: 14px; 
        border: none; 
        border-radius: 12px; 
        font-size: 16px; 
        font-weight: 600; 
        cursor: pointer; 
        margin-top: 25px; 
        transition: transform 0.2s, box-shadow 0.2s; 
    }
    input[type="submit"]:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102,126,234,0.4); }
    .success-message { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #28a745; }
    .error-message { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #dc3545; }
    .info-message { background-color: #fff3cd; color: #856404; padding: 12px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ffc107; }
    
    .position-title {
        background: #f3f4f6;
        padding: 12px 15px;
        border-radius: 10px;
        margin: 20px 0 10px 0;
        font-size: 18px;
        font-weight: 700;
        color: #374151;
    }
    
    @media (max-width: 768px) {
        .vote-container { width: 95%; padding: 20px; }
        .candidate-card { min-width: 120px; }
        .candidate-card img { width: 60px; height: 60px; }
    }
</style>

<div class="vote-container">
    <h2>🗳️ Cast Your Vote</h2>
    <div class="subtitle">Click on a candidate card to select your choice for each position</div>

    <label for="electionSelect">Select an Election:</label>
    <select id="electionSelect" onchange="window.location.href='vote.php?election_id=' + this.value;">
        <option value="">-- Select Election --</option>
        <?php foreach ($openElections as $electionOption): ?>
            <option value="<?php echo $electionOption['id']; ?>" <?php echo ($electionId == $electionOption['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($electionOption['title']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if (!empty($errorMessage)): ?>
        <div class="error-message"><?php echo $errorMessage; ?></div>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
        <div class="success-message"><?php echo $successMessage; ?></div>
    <?php endif; ?>

    <?php if ($showVoteForm && $electionId && $election): ?>
        <form method="post" action="vote.php?election_id=<?php echo $electionId; ?>" id="voteForm">
            <h3 style="color: #667eea; text-align: center; margin: 20px 0 10px;"><?php echo htmlspecialchars($election['title']); ?></h3>
            
            <?php
            $postsStmt = $conn->prepare("SELECT id, postname FROM election_posts WHERE election_id = ? ORDER BY postname");
            $postsStmt->execute([$electionId]);
            $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($posts as $post):
                $postName = $post['postname'];
                $postKey = strtolower(str_replace(' ', '_', $postName));
                
                // Fetch candidates with their images
                $candidateStmt = $conn->prepare("
                    SELECT id, name, profile_photo, profile_photo_blob, profile_photo_type 
                    FROM contesters 
                    WHERE postname = ? AND election_id = ? 
                    ORDER BY name
                ");
                $candidateStmt->execute([$postName, $electionId]);
                $candidates = $candidateStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <div class="position-title">
                    <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($postName); ?>
                </div>
                
                <div class="candidate-cards" id="candidate-group-<?php echo $postKey; ?>">
                    <?php if (empty($candidates)): ?>
                        <p style="color: #999;">No candidates available for this position.</p>
                    <?php else: ?>
                        <?php foreach ($candidates as $candidate): ?>
                            <div class="candidate-card" data-post="<?php echo $postKey; ?>" data-candidate-id="<?php echo $candidate['id']; ?>" onclick="selectCandidate('<?php echo $postKey; ?>', <?php echo $candidate['id']; ?>, this)">
                                <?php
                                // Display candidate image
                                if (!empty($candidate['profile_photo_blob'])) {
                                    echo '<img src="data:image/' . $candidate['profile_photo_type'] . ';base64,' . base64_encode($candidate['profile_photo_blob']) . '" alt="' . htmlspecialchars($candidate['name']) . '">';
                                } elseif (!empty($candidate['profile_photo']) && file_exists('faces/' . $candidate['profile_photo'])) {
                                    echo '<img src="faces/' . htmlspecialchars($candidate['profile_photo']) . '" alt="' . htmlspecialchars($candidate['name']) . '">';
                                } else {
                                    echo '<img src="faces/default.jpg" alt="' . htmlspecialchars($candidate['name']) . '">';
                                }
                                ?>
                                <div class="candidate-name"><?php echo htmlspecialchars($candidate['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Hidden input to store selected candidate ID -->
                <input type="hidden" name="<?php echo $postKey; ?>" id="hidden-<?php echo $postKey; ?>" value="">
                
            <?php endforeach; ?>
            
            <input type="submit" name="submit" value="Submit Vote">
        </form>
    <?php endif; ?>
    
    <?php if (!$electionId && !empty($openElections)): ?>
        <div class="info-message">Please select an election from the dropdown above to cast your vote.</div>
    <?php endif; ?>
    
    <?php if (empty($openElections)): ?>
        <div class="info-message">No active elections available at this time.</div>
    <?php endif; ?>
</div>

<script>
    // Store selected candidates
    let selectedCandidates = {};
    
    function selectCandidate(postKey, candidateId, element) {
        // Remove selected class from all cards in this post group
        const container = document.getElementById('candidate-group-' + postKey);
        const cards = container.querySelectorAll('.candidate-card');
        cards.forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selected class to clicked card
        element.classList.add('selected');
        
        // Store selected candidate ID
        selectedCandidates[postKey] = candidateId;
        
        // Update hidden input
        const hiddenInput = document.getElementById('hidden-' + postKey);
        if (hiddenInput) {
            hiddenInput.value = candidateId;
        }
    }
    
    // Form validation before submit
    document.getElementById('voteForm')?.addEventListener('submit', function(e) {
        const hiddenInputs = document.querySelectorAll('input[type="hidden"][name^="hidden-"]');
        let allSelected = true;
        let missingSelections = [];
        
        hiddenInputs.forEach(input => {
            if (!input.value) {
                allSelected = false;
                const postKey = input.id.replace('hidden-', '');
                missingSelections.push(postKey.replace('_', ' '));
            }
        });
        
        if (!allSelected) {
            e.preventDefault();
            alert('Please select a candidate for all positions: ' + missingSelections.join(', '));
            return false;
        }
        return true;
    });
</script>

<?php include("footer.php"); ?>

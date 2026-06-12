<?php
// setup-database.php - Temporary database setup script
include 'conn.php'; // Pulls your working Aiven connection

try {
    // We combine all your SQL commands into one big string blocks
    $sqlCommands = "
-- First, backup existing votes if any
CREATE TABLE votes_backup AS SELECT * FROM votes;

-- Drop the old votes table
DROP TABLE votes;

-- Create new votes table with proper structure
CREATE TABLE votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    election_id INT NOT NULL,
    postname VARCHAR(100) NOT NULL,
    candidate_name VARCHAR(255) NOT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (username, election_id, postname),
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
);
    ";

    // Run the text block directly into the database engine
    $db->exec($sqlCommands);
    
    echo "<div style='font-family: Arial; padding: 40px; text-align: center;'>";
    echo "<h2 style='color: green;'>🎉 Database Tables Built Successfully!</h2>";
    echo "<p>The following table is now live in Aiven:</p>";
    echo "<b>• election_posts </b><br><br>";
    echo "<p style='color:red;'>⚠️ For security, please delete setup-database.php or remove it from GitHub now.</p>";
    echo "</div>";

} catch (Exception $e) {
    die("<h2 style='color:red;'>Database Setup Failed:</h2> " . $e->getMessage());
}
?>

<?php
// setup-database.php - Temporary database setup script
include 'conn.php'; // Pulls your working Aiven connection

try {
    // We combine all your SQL commands into one big string blocks
    $sqlCommands = "
CREATE TABLE IF NOT EXISTS election_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    postname VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_election_post (election_id, postname),
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

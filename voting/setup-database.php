<?php
// setup-database.php - Temporary database setup script
include 'conn.php'; // Pulls your working Aiven connection

try {
    // We combine all your SQL commands into one big string blocks
    $sqlCommands = "
ALTER TABLE contesters 
ADD COLUMN user_id INT AFTER id,
ADD COLUMN election_id INT AFTER user_id,
ADD COLUMN profile_photo VARCHAR(255) AFTER name,
ADD COLUMN votes INT DEFAULT 0 AFTER profile_photo,
ADD COLUMN bio TEXT AFTER votes,
MODIFY COLUMN postname VARCHAR(100) NOT NULL,
MODIFY COLUMN name VARCHAR(255) NOT NULL;

-- Add foreign key constraints
ALTER TABLE contesters 
ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
ADD FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE;

    ";

    // Run the text block directly into the database engine
    $db->exec($sqlCommands);
    
    echo "<div style='font-family: Arial; padding: 40px; text-align: center;'>";
    echo "<h2 style='color: green;'>🎉 Database Tables Built Successfully!</h2>";
    echo "<p>The following tables are now live in Aiven:</p>";
    echo "<b>• elections and election types </b><br><br>";
    echo "<p style='color:red;'>⚠️ For security, please delete setup-database.php or remove it from GitHub now.</p>";
    echo "</div>";

} catch (Exception $e) {
    die("<h2 style='color:red;'>Database Setup Failed:</h2> " . $e->getMessage());
}
?>

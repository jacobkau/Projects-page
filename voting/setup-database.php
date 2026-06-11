<?php
// setup-database.php - Temporary database setup script
include 'conn.php'; // Pulls your working Aiven connection

try {
    // We combine all your SQL commands into one big string blocks
    $sqlCommands = "
CREATE TABLE IF NOT EXISTS elections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATETIME,
    end_date DATETIME,
    status ENUM('upcoming', 'active', 'completed') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS election_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL,
    voting_enabled BOOLEAN DEFAULT TRUE,
    registration_enabled BOOLEAN DEFAULT TRUE,
    results_visible BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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

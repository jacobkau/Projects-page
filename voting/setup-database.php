<?php
// setup-database.php - Temporary database setup script
include 'conn.php'; // Pulls your working Aiven connection

try {
    // We combine all your SQL commands into one big string blocks
    $sqlCommands = "
    CREATE TABLE IF NOT EXISTS `contesters` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `postname` text NOT NULL,
      `name` text NOT NULL,
      `date` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `posts` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `name` text NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `users` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) NOT NULL,
      `name` text NOT NULL,
      `email` varchar(100) NOT NULL,
      `profile_photo` varchar(255) DEFAULT NULL,
      `password` text NOT NULL,
      `date` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `votes` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `username` text NOT NULL,
      `date` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
      `chairperson` text NOT NULL,
      `vicechairperson` text NOT NULL,
      `secretary` text NOT NULL,
      `vicesecretary` text NOT NULL,
      `treasurer` text NOT NULL,
      `coordinator` text NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    // Run the text block directly into the database engine
    $db->exec($sqlCommands);
    
    echo "<div style='font-family: Arial; padding: 40px; text-align: center;'>";
    echo "<h2 style='color: green;'>🎉 Database Tables Built Successfully!</h2>";
    echo "<p>The following tables are now live in Aiven:</p>";
    echo "<b>• contesters<br>• posts<br>• users<br>• votes</b><br><br>";
    echo "<p style='color:red;'>⚠️ For security, please delete setup-database.php or remove it from GitHub now.</p>";
    echo "</div>";

} catch (Exception $e) {
    die("<h2 style='color:red;'>Database Setup Failed:</h2> " . $e->getMessage());
}
?>

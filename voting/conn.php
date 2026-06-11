<?php
// conn.php - Robust database connection

// 1. Check if Render has the background variable. If not, use your raw link directly.
$uri = getenv('AIVEN_DATABASE_URL');

if (!$uri) {
    // Paste your exact raw string here as a backup
    $uri = "mysql://avnadmin:<redacted>@mysql-207a0fc7-jw-all-projects-databases.c.aivencloud.com:27643/defaultdb?ssl-mode=REQUIRED";
}

// 2. Safely break down the URL link
$fields = parse_url($uri);

if (!$fields || !isset($fields["host"])) {
    die("Database Connection Error: The connection link string is broken or invalid.");
}

// 3. Build the connection string using your ca.pem file
$conn = "mysql:";
$conn .= "host=" . $fields["host"];
$conn .= ";port=" . ($fields["port"] ?? '27643'); // Uses 27643 if port is missing
$conn .= ";dbname=defaultdb";
$conn .= ";sslmode=verify-ca;sslrootcert=/var/www/html/ca.pem"; 
// Note: We used an absolute path above so your subfolders like /voting/ can find ca.pem!

try {
    // 4. Connect to Aiven MySQL
    $user = $fields["user"] ?? 'avnadmin';
    $pass = $fields["pass"] ?? '';
    
    $db = new PDO($conn, $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>

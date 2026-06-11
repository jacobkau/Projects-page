<?php
// conn.php - Completely hidden credentials

// 1. Fetch the secret link from Render's hidden environment variables
$uri = getenv('AIVEN_DATABASE_URL');

// If the background variable is missing, stop the script safely
if (!$uri) {
    die("Database Connection Error: Secure configuration string is missing.");
}

// 2. Break down the hidden URL link
$fields = parse_url($uri);

if (!$fields || !isset($fields["host"])) {
    die("Database Connection Error: Secure configuration string is corrupted.");
}

// 3. Build the connection string using the absolute path to your certificate
$conn = "mysql:";
$conn .= "host=" . $fields["host"];
$conn .= ";port=" . ($fields["port"] ?? '27643');
$conn .= ";dbname=defaultdb";
$conn .= ";sslmode=verify-ca;sslrootcert=/var/www/html/ca.pem";

try {
    $user = $fields["user"] ?? 'avnadmin';
    $pass = $fields["pass"] ?? '';
    
    // 4. Connect securely
    $db = new PDO($conn, $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (Exception $e) {
    // We hide the raw error message so it never prints your password on screen
    die("Database Connection Error: Could not connect to database securely.");
}
?>

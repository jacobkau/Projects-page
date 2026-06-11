<?php
// db.php - Safe database connection for Render

// 1. Fetch the secret URL from Render's background environment variables
$uri = getenv('AIVEN_DATABASE_URL');

if (!$uri) {
    die("Error: Database connection string is missing in Render settings.");
}

$fields = parse_url($uri);

// 2. Build the connection string using the ca.pem file
$conn = "mysql:";
$conn .= "host=" . $fields["host"];
$conn .= ";port=" . $fields["port"];
$conn .= ";dbname=defaultdb";
$conn .= ";sslmode=verify-ca;sslrootcert=ca.pem";

try {
    // 3. Connect to the database
    $db = new PDO($conn, $fields["user"], $fields["pass"]);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // You can remove these lines once you verify it works:
    $stmt = $db->query("SELECT VERSION()");
    // echo "Connected successfully! MySQL Version: " . $stmt->fetch()[0];
    
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>

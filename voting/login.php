<?php
include("conn.php");

echo "<h2>Login System Test</h2>";

// Test 1: Check connection
echo "<h3>1. Database Connection:</h3>";
if ($conn) {
    echo "✓ Connection successful<br>";
} else {
    echo "✗ Connection failed<br>";
}

// Test 2: Check users table
echo "<h3>2. Users Table:</h3>";
try {
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() > 0) {
        echo "✓ Users table exists<br>";
        
        // Show table structure
        $columns = $conn->query("DESCRIBE users");
        echo "<strong>Table columns:</strong><br>";
        while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
            echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
        }
    } else {
        echo "✗ Users table does not exist!<br>";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

// Test 3: Check users
echo "<h3>3. User Accounts:</h3>";
try {
    $users = $conn->query("SELECT id, username, email, LEFT(password_hash, 20) as hash_preview FROM users");
    $count = $users->rowCount();
    echo "Total users: $count<br>";
    
    if ($count > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Hash Preview</th></tr>";
        while ($user = $users->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['hash_preview']) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

// Test 4: Test password hashing
echo "<h3>4. Password Hash Test:</h3>";
$testPassword = "test123";
$hash = password_hash($testPassword, PASSWORD_DEFAULT);
echo "Test password: $testPassword<br>";
echo "Generated hash: $hash<br>";
echo "Verification: " . (password_verify($testPassword, $hash) ? "✓ Works" : "✗ Fails") . "<br>";
?>

<style>
    body { font-family: monospace; padding: 20px; background: #f4f4f4; }
    h2 { color: #333; }
    h3 { color: #666; margin-top: 20px; }
    table { border-collapse: collapse; margin-top: 10px; }
    th, td { padding: 5px 10px; }
</style>

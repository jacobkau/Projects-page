<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$servername = "sql308.infinityfree.com";
$username = "if0_38412078";
$password = "g4fqHIbzkrYxxT";
$dbname = "if0_38412078_witty";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
//echo "Connected successfully"; //for debugging.
?>
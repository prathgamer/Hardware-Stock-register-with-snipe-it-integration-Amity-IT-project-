<?php
// Enable full error reporting to screen
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Server Check</h1>";

// 1. Check MySQL Extension
if (!class_exists('mysqli')) {
    die("CRITICAL ERROR: MySQLi extension is missing on this server.");
}
echo "MySQLi extension: OK<br>";

// 2. Check Database Connection
// REPLACE 'root' and '' with your real username/password if different
$conn = new mysqli("localhost", "root", "", "hardware_db");

if ($conn->connect_error) {
    die("DB Connection FAILED: " . $conn->connect_error);
}
echo "DB Connection: OK<br>";

// 3. Check Users Table
$result = $conn->query("SELECT * FROM users LIMIT 1");
if (!$result) {
    die("Table Check FAILED: " . $conn->error);
}
echo "Users Table: OK (Found " . $result->num_rows . " rows)<br>";

// 4. Check 'role' column
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($result->num_rows == 0) {
    echo "<b>WARNING: 'role' column is MISSING in 'users' table!</b><br>";
    echo "Run this SQL: ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'user';";
} else {
    echo "Role Column: OK<br>";
}

echo "<h3>Everything looks good! The 500 error is likely a typo in register_admin.php</h3>";
?>

<?php
// api_check_install.php
require 'config.php';
header('Content-Type: application/json');

// Check if any users exist
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    echo json_encode(["status" => "fresh", "message" => "No users found"]);
} else {
    echo json_encode(["status" => "installed", "message" => "System ready"]);
}
?>

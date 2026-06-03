<?php
// register_admin.php
header("Content-Type: application/json");
$conn = new mysqli("localhost", "root", "", "hardware_db");

if ($conn->connect_error) { die(json_encode(["success" => false, "message" => "DB Fail"])); }

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) { echo json_encode(["success" => false, "message" => "No Data"]); exit; }

$u = $input['username'];
$p = password_hash($input['password'], PASSWORD_DEFAULT);
$r = isset($input['role']) ? $input['role'] : 'user';

// 1. Check Duplicate First
$check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check->bind_param("s", $u);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Username already taken!"]);
    exit;
}

// 2. Insert with correct column name
$stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $u, $p, $r);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Insert Fail"]);
}
?>

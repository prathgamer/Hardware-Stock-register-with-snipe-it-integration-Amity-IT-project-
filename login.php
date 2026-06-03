<?php
session_start(); // <--- CRITICAL

error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data["username"]) || !isset($data["password"])) {
    echo json_encode(["success" => false, "message" => "Missing credentials"]);
    exit;
}

$username = $data["username"];
$password = $data["password"];

$stmt = $conn->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Invalid username or password"]);
    exit;
}

$user = $res->fetch_assoc();

if (password_verify($password, $user["password_hash"])) {
    
    // --- THIS IS THE FIX ---
    $_SESSION['userId'] = $user['id'];
    $_SESSION['hardwareUser'] = $user['username'];
    $_SESSION['userRole'] = $user['role']; // <--- NOW api_admin.php WILL WORK
    // -----------------------

    if (function_exists('writeLog')) {
        writeLog($conn, $user['id'], $user['username'], "LOGIN", "User logged in successfully");
    }

    echo json_encode([
        "success" => true,
        "message" => "Login successful",
        "userid" => $user["id"],
        "username" => $user["username"],
        "role" => $user["role"]
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid username or password"]);
}
?>

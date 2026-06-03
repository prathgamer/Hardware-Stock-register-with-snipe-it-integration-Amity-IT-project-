<?php
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

function out($arr){ echo json_encode($arr); exit; }

$method = $_SERVER['REQUEST_METHOD'];

/* Admin only */
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
  out(["success" => false, "message" => "Access Denied"]);
}

if ($method === 'GET') {
  $result = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY id ASC");
  $users = [];
  if ($result) while($row = $result->fetch_assoc()) $users[] = $row;
  out(["success" => true, "data" => $users]);
}

if ($method === 'POST') {
  $data = json_decode(file_get_contents("php://input"), true);
  if (!$data) out(["success" => false, "message" => "Invalid JSON"]);

  $u = trim($data['username'] ?? '');
  $p = $data['password'] ?? '';
  $r = ($data['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

  if ($u === '' || $p === '') out(["success" => false, "message" => "Username & password required"]);

  $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
  $check->bind_param("s", $u);
  $check->execute();
  $checkRes = $check->get_result();
  if ($checkRes && $checkRes->num_rows > 0) out(["success" => false, "message" => "Username already exists"]);

  $hash = password_hash($p, PASSWORD_DEFAULT);
  $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
  $stmt->bind_param("sss", $u, $hash, $r);

  if ($stmt->execute()) {
    if (function_exists('writeLog')) writeLog($conn, $_SESSION['userId'], $_SESSION['hardwareUser'], "CREATE_USER", "Created user: $u ($r)");
    out(["success" => true, "message" => "User created"]);
  }

  out(["success" => false, "message" => "Insert failed"]);
}

// --- 3. DELETE USER (Fixed to log Name) ---
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = intval($data['id'] ?? 0);

    if ($id <= 0) out(["success" => false, "message" => "Invalid ID"]);
    if ($id === 1) out(["success" => false, "message" => "Cannot delete Super Admin"]);

    // 1. GET NAME BEFORE DELETING
    $deletedName = "Unknown ID:$id";
    $query = $conn->query("SELECT username FROM users WHERE id = $id");
    if ($query && $row = $query->fetch_assoc()) {
        $deletedName = $row['username'];
    }

    // 2. PERFORM DELETE
    $conn->query("DELETE FROM users WHERE id = $id");

    // 3. LOG WITH NAME
    if (function_exists('writeLog')) {
        writeLog($conn, $_SESSION['userId'], $_SESSION['hardwareUser'], "DELETE_USER", "Deleted user: <strong>$deletedName</strong>");
    }

    out(["success" => true, "message" => "User deleted successfully"]);
}


<?php
// api_stock.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$conn = new mysqli("localhost", "root", "", "hardware_db");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB Fail"]);
    exit;
}

$type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : 'hardware';
$where = "WHERE inventory_type = '$type'";

// 🚨 FIX: Smart Admin "View All" Logic
$role = isset($_SESSION['userRole']) ? $_SESSION['userRole'] : 'user';
$sessionUserId = isset($_SESSION['userId']) ? intval($_SESSION['userId']) : 1;

if ($role === 'admin') {
    // If Admin selects a user, filter it. If it is empty ("View All"), fetch everything!
    if (isset($_GET['user_id']) && $_GET['user_id'] !== "") {
        $uid = intval($_GET['user_id']);
        $where .= " AND user_id = $uid";
    }
} else {
    // Normal users ONLY see their own stock
    $where .= " AND user_id = $sessionUserId";
}

$sql = "SELECT * FROM stock $where ORDER BY item_name ASC";
$result = $conn->query($sql);

$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode(["success" => true, "data" => $data]);
$conn->close();
?>
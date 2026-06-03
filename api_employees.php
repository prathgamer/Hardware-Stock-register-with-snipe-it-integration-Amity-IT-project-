<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') { http_response_code(200); exit; }

function sendJson($data) { echo json_encode($data); exit; }

// --- 1. GET: Fetch Employees ---
if ($method === 'GET') {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if($user_id === 0) sendJson(["success" => true, "data" => []]);

    $query = "SELECT * FROM employees WHERE user_id = $user_id ORDER BY emp_id ASC";
    $result = $conn->query($query);
    
    $employees = [];
    if($result){
        while($row = $result->fetch_assoc()) $employees[] = $row;
    }
    sendJson(["success" => true, "data" => $employees]);
}

// --- 2. POST: Add/Update Employee (WITH SNIPE-IT AUTO-MAPPING) ---
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    $user_id = isset($data->user_id) ? intval($data->user_id) : 0;
    $emp_id = isset($data->emp_id) ? trim($data->emp_id) : '';
    $location = isset($data->location_name) ? trim($data->location_name) : '';

    if(!empty($emp_id) && !empty($location) && $user_id > 0) {
        $emp_id = $conn->real_escape_string($emp_id);
        $location = $conn->real_escape_string($location);
        
        // 🚀 NEW: Auto-Fetch Snipe-IT User ID in the background!
        $snipeUserId = 'NULL';
        if (function_exists('getSnipeITConfig')) {
            $snipe = getSnipeITConfig($conn);
            if (!empty($snipe['snipeit_url']) && !empty($snipe['snipeit_token'])) {
                // Ask Snipe-IT to search for this Employee Code
                $userData = snipeit_get("/users?search=" . urlencode($emp_id), $snipe['snipeit_token'], $snipe['snipeit_url']);
                
                // If Snipe-IT finds them, grab their hidden ID!
                if (!empty($userData['rows']) && isset($userData['rows'][0]['id'])) {
                    $snipeUserId = intval($userData['rows'][0]['id']);
                }
            }
        }
        
        // Save the Employee and the Snipe-IT ID to the database
        $sql = "INSERT INTO employees (emp_id, location_name, user_id, snipeit_user_id) 
                VALUES ('$emp_id', '$location', $user_id, $snipeUserId) 
                ON DUPLICATE KEY UPDATE location_name='$location', snipeit_user_id=$snipeUserId";
        
        if($conn->query($sql)){
            sendJson(["success" => true, "message" => "Saved"]);
        } else {
            sendJson(["success" => false, "message" => "DB Error: " . $conn->error]);
        }
    } else {
        sendJson(["success" => false, "message" => "Missing Data or User ID"]);
    }
}

// --- 3. DELETE: Remove Employee ---
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"));
    $user_id = isset($data->user_id) ? intval($data->user_id) : 0;
    $emp_id = isset($data->emp_id) ? trim($data->emp_id) : '';

    if($user_id > 0 && !empty($emp_id)) {
        $emp_id = $conn->real_escape_string($emp_id);
        $sql = "DELETE FROM employees WHERE emp_id='$emp_id' AND user_id=$user_id";
        
        if($conn->query($sql)) sendJson(["success" => true, "message" => "Deleted"]);
        else sendJson(["success" => false, "message" => "DB Error: " . $conn->error]);
    } else {
        sendJson(["success" => false, "message" => "Invalid Request"]);
    }
}

sendJson(["success" => false, "message" => "Method not allowed"]);
?>
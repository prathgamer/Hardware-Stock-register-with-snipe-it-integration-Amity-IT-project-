<?php
// api_admin.php
header("Content-Type: application/json");
session_start();
include 'config.php';

$action = $_GET['action'] ?? '';

// 🚨 SMART SECURITY: Let everyone read settings, but block all other actions for normal users!
if ($action !== 'get_settings') {
    if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
        die(json_encode(["success" => false, "message" => "Access Denied"]));
    }
}

// --- 1. FETCH DASHBOARD STATS ---
if ($action === 'stats') {
    $users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
    $items = $conn->query("SELECT COUNT(*) as c FROM stock")->fetch_assoc()['c'];
    $logs  = $conn->query("SELECT COUNT(*) as c FROM system_logs")->fetch_assoc()['c'];
    
    echo json_encode(["success" => true, "stats" => [
        "users" => $users, "items" => $items, "logs" => $logs
    ]]);
    exit;
}

// --- 2. FETCH LOGS (With Date Filters & Colors) ---
if ($action === 'get_logs') {
    $uid = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $start_date = isset($_GET['start_date']) ? $conn->real_escape_string($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? $conn->real_escape_string($_GET['end_date']) : '';
    
    // Select specific columns and format the date using MySQL
    $sql = "SELECT 
                l.id, 
                DATE_FORMAT(l.created_at, '%d-%b-%Y %h:%i %p') as nice_date,
                l.username, 
                l.action_type, 
                l.description
            FROM system_logs l
            WHERE 1=1"; // WHERE 1=1 makes adding filters easy

    // Apply Filters
    if ($uid > 0) {
        $sql .= " AND l.user_id = $uid";
    }
    if (!empty($start_date)) {
        $sql .= " AND l.created_at >= '$start_date 00:00:00'";
    }
    if (!empty($end_date)) {
        $sql .= " AND l.created_at <= '$end_date 23:59:59'";
    }
    
    $sql .= " ORDER BY l.id DESC LIMIT 1000"; // Show last 1000 logs
    
    $result = $conn->query($sql);
    $logs = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // --- NEW: Dynamic Color Logic ---
            $actionType = strtoupper($row['action_type']); 
            $color = "#94a3b8"; // Default Grey
            
            if (strpos($actionType, 'DELETE') !== false || strpos($actionType, 'OUT') !== false) {
                $color = "#ef4444"; // Red for Danger/Out
            } elseif (strpos($actionType, 'ADD') !== false || strpos($actionType, 'IN') !== false) {
                $color = "#10b981"; // Green for Success/In
            } elseif (strpos($actionType, 'IMPORT') !== false || strpos($actionType, 'EDIT') !== false) {
                $color = "#38bdf8"; // Blue for System actions
            } elseif (strpos($actionType, 'WARNING') !== false || strpos($actionType, 'RESET') !== false) {
                $color = "#f59e0b"; // Orange for Warnings/Passwords
            }
            
            $row['color'] = $color; // Attach the color to the row
            $logs[] = $row;
        }
    }
    echo json_encode(["success" => true, "data" => $logs]);
    exit;
}

// --- 3. GET SETTINGS ---
if ($action === 'get_settings') {
    $result = $conn->query("SELECT * FROM system_settings");
    $settings = [];
    if($result) {
        $first = true;
        while($row = $result->fetch_assoc()) {
            // Grab the row-based settings (App Name, Low Stock)
            if (!empty($row['setting_key'])) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            // Grab the column-based Snipe-IT settings from the first row
            if ($first) {
                $settings['snipeit_url'] = $row['snipeit_url'] ?? '';
                $settings['snipeit_token'] = $row['snipeit_token'] ?? '';
                $first = false;
            }
        }
    }
    echo json_encode(["success" => true, "data" => $settings]);
    exit;
}

// --- 4. SAVE SETTINGS ---
if ($action === 'save_settings') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $snipeUrl = "";
    $snipeToken = "";

    foreach($data as $key => $val) {
        // Separate Snipe-IT settings since they are saved as columns, not rows
        if ($key === 'snipeit_url') {
            $snipeUrl = $conn->real_escape_string($val);
        } elseif ($key === 'snipeit_token') {
            $snipeToken = $conn->real_escape_string($val);
        } else {
            // Save standard settings into their specific rows
            $k = $conn->real_escape_string($key);
            $v = $conn->real_escape_string($val);
            $conn->query("UPDATE system_settings SET setting_value='$v' WHERE setting_key='$k'");
        }
    }
    
    // Update the Snipe-IT columns directly
    $conn->query("UPDATE system_settings SET snipeit_url='$snipeUrl', snipeit_token='$snipeToken'");
    
    // Log this action
    logAction($conn, "SETTINGS_UPDATE", "Admin updated system settings and API Keys");
    echo json_encode(["success" => true, "message" => "Settings Saved"]);
    exit;
}

// --- 5. USER MANAGEMENT (Reset Password) ---
if ($action === 'reset_pass') {
    $data = json_decode(file_get_contents("php://input"), true);
    $uid = intval($data['user_id']);

    // 1. FETCH USERNAME FIRST (For the Log)
    $targetName = "Unknown";
    $userQuery = $conn->query("SELECT username FROM users WHERE id = $uid");
    if($userQuery && $row = $userQuery->fetch_assoc()) {
        $targetName = $row['username'];
    }

    // 2. Update Password
    $raw_pass = isset($data['new_password']) && !empty($data['new_password']) 
                ? $data['new_password'] 
                : "123456";
    
    $new_pass = password_hash($raw_pass, PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password_hash='$new_pass' WHERE id=$uid");

    // 3. Log with Name
    logAction($conn, "USER_RESET", "Reset password for user: <strong>$targetName</strong>");

    echo json_encode(["success" => true, "message" => "Password updated successfully"]);
    exit;
}

// --- 6. GET USERS LIST (For Admin Dropdowns) ---
if ($action === 'get_users') {
    $sql = "SELECT id, username, role FROM users ORDER BY username ASC";
    $result = $conn->query($sql);
    $users = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    echo json_encode(["success" => true, "data" => $users]);
    exit;
}

// Helper Function to Write Log
function logAction($conn, $type, $desc) {
    $uid = $_SESSION['userId'] ?? 0;
    $user = $_SESSION['hardwareUser'] ?? 'System';
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action_type, description, ip_address) VALUES (?, ?, ?, ?, ?)");
    if($stmt) {
        $stmt->bind_param("issss", $uid, $user, $type, $desc, $ip);
        $stmt->execute();
    }
}

// If no valid action matches
echo json_encode(["success" => false, "message" => "Invalid action specified."]);
$conn->close();
?>
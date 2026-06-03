<?php
// api_items.php
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, DELETE, PUT, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'config.php'; 

// 🚨 MASTER KEY LOGIC 🚨
$sessionUserId = isset($_SESSION['userId']) ? intval($_SESSION['userId']) : 1;
$userRole = isset($_SESSION['userRole']) ? $_SESSION['userRole'] : 'user';

try {
    // === POST: Add New Item or BATCH of Items ===
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // --- 1. BATCH IMPORT LOGIC ---
        if (isset($data[0]) && is_array($data[0])) {
            $count = 0;
            foreach ($data as $row) {
                $page_no = $conn->real_escape_string($row['page_no'] ?? '');
                $name = $conn->real_escape_string((string)$row["name"]); 
                $initial_stock = intval($row["initial_stock"] ?? 0);
                $type = $conn->real_escape_string((string)($row["inventory_type"] ?? 'hardware')); 
                
                // 🚨 FIX: Allow Admin to target the selected User ID
                $targetUserId = $sessionUserId;
                if ($userRole === 'admin' && isset($row['user_id'])) {
                    $targetUserId = intval($row['user_id']);
                }
                
                if (empty($name)) continue;

                $check = $conn->query("SELECT id FROM stock WHERE item_name = '$name' AND user_id = $targetUserId AND inventory_type = '$type'");
                
                if ($check && $check->num_rows == 0) {
                    // Scan History using the Secure User ID
                    $histQuery = $conn->query("SELECT in_out, SUM(qty) as total FROM transactions WHERE item_name = '$name' AND inventory_type = '$type' AND user_id = $targetUserId GROUP BY in_out");
                    
                    $historical_in = 0;
                    $historical_out = 0;
                    
                    if ($histQuery) {
                        while($hRow = $histQuery->fetch_assoc()) {
                            if(strtoupper($hRow['in_out']) === 'IN') $historical_in = intval($hRow['total']);
                            if(strtoupper($hRow['in_out']) === 'OUT') $historical_out = intval($hRow['total']);
                        }
                    }

                    $current_stock = $initial_stock + $historical_in - $historical_out;

                    // FIX: Added page_no to INSERT statement with 8 question marks
                    $stmt = $conn->prepare("INSERT INTO stock (page_no, item_name, initial_stock, in_qty, out_qty, current_stock, user_id, inventory_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("ssiiiiis", $page_no, $name, $initial_stock, $historical_in, $historical_out, $current_stock, $targetUserId, $type);
                        $stmt->execute();
                        $stmt->close();
                        $count++;
                    }
                } else if ($check && $check->num_rows > 0 && $initial_stock > 0) {
                    // Update initial stock and page number if it exists
                    $conn->query("UPDATE stock SET page_no = '$page_no', initial_stock = $initial_stock, current_stock = $initial_stock + in_qty - out_qty WHERE item_name = '$name' AND user_id = $targetUserId AND inventory_type = '$type'");
                }
            }
            
            if(function_exists('writeLog') && $count > 0) {
                $logName = isset($_SESSION['hardwareUser']) ? $_SESSION['hardwareUser'] : "System";
                $typeUpper = strtoupper($type);
                $desc = "<span style='color:var(--accent); font-weight:bold;'>[$typeUpper]</span> <strong>Excel Import (Stock):</strong> Successfully imported <b>$count</b> new inventory items.";
                writeLog($conn, $sessionUserId, $logName, "IMPORT_STOCK", $desc);
            }
            
            echo json_encode(["success" => true, "message" => "Imported $count items"]);
            exit;
        }
        
        // --- 2. SINGLE ITEM LOGIC ---
        if (!isset($data["name"])) {
            die(json_encode(["success" => false, "message" => "Name required"]));
        }
        
        $page_no = $conn->real_escape_string((string)($data["page_no"] ?? '')); // NEW
        $name = $conn->real_escape_string((string)$data["name"]); 
        $initial_stock = intval($data["initial_stock"] ?? 0);
        $type = $conn->real_escape_string((string)($data["inventory_type"] ?? 'hardware')); 
        
        $targetUserId = $sessionUserId;
        if ($userRole === 'admin' && isset($data['user_id'])) {
            $targetUserId = intval($data['user_id']);
        }

        $query = "SELECT id FROM stock WHERE item_name = '$name' AND user_id = $targetUserId AND inventory_type = '$type'";
        $check = $conn->query($query);
        
        if ($check && $check->num_rows > 0) {
            echo json_encode(["success" => true, "message" => "Item already exists."]); 
            exit;
        }
        
        $histQuery = $conn->query("SELECT in_out, SUM(qty) as total FROM transactions WHERE item_name = '$name' AND inventory_type = '$type' AND user_id = $targetUserId GROUP BY in_out");
        
        $historical_in = 0;
        $historical_out = 0;
        
        if ($histQuery) {
            while($hRow = $histQuery->fetch_assoc()) {
                if(strtoupper($hRow['in_out']) === 'IN') $historical_in = intval($hRow['total']);
                if(strtoupper($hRow['in_out']) === 'OUT') $historical_out = intval($hRow['total']);
            }
        }

        $current_stock = $initial_stock + $historical_in - $historical_out;

        // FIX: Added page_no to INSERT statement with 8 question marks
        $stmt = $conn->prepare("INSERT INTO stock (page_no, item_name, initial_stock, in_qty, out_qty, current_stock, user_id, inventory_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiiiis", $page_no, $name, $initial_stock, $historical_in, $historical_out, $current_stock, $targetUserId, $type);
        
        if ($stmt->execute()) {
            if(function_exists('writeLog')) {
                $logName = isset($_SESSION['hardwareUser']) ? $_SESSION['hardwareUser'] : "System";
                $typeUpper = strtoupper($type);
                $desc = "<span style='color:var(--accent); font-weight:bold;'>[$typeUpper]</span> Added new item: <b>$name</b>";
                writeLog($conn, $sessionUserId, $logName, "ADD_ITEM", $desc);
            }
            echo json_encode(["success" => true, "message" => "Item added and synced with history!"]);
        } else {
            echo json_encode(["success" => false, "message" => "Insert failed: " . $stmt->error]);
        }
        $stmt->close();

   // === DELETE: Delete Item ===
    } else if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
        $data = json_decode(file_get_contents("php://input"), true);
        $name = $conn->real_escape_string((string)$data["name"]);
        $type = $conn->real_escape_string((string)($data["inventory_type"] ?? 'hardware')); 
        
        $targetUserId = $sessionUserId;
        if ($userRole === 'admin' && isset($data['user_id'])) {
            $targetUserId = intval($data['user_id']);
        }
        
        $stockSql = "DELETE FROM stock WHERE item_name = '$name' AND inventory_type = '$type' AND user_id = $targetUserId";
        
        if ($conn->query($stockSql)) {
            if (function_exists('writeLog') && isset($_SESSION['userId'])) {
                writeLog($conn, $_SESSION['userId'], $_SESSION['hardwareUser'], "DELETE_ITEM", "Deleted stock item: <b>$name</b> ($type)");
            }
            echo json_encode(["success" => true, "message" => "Deleted successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to delete item"]);
        }

    // === PUT: Update Initial Stock ===
    // === PUT: Update Initial Stock ===
    } else if ($_SERVER["REQUEST_METHOD"] === "PUT") {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data["name"])) {
            die(json_encode(["success" => false, "message" => "Item name required"]));
        }
        $page_no = $conn->real_escape_string($data['page_no'] ?? ''); 
        $name = $conn->real_escape_string((string)$data["name"]);
        $initial_stock = intval($data["initial_stock"] ?? 0);
        $type = $conn->real_escape_string((string)($data["inventory_type"] ?? 'hardware')); 
        
        $targetUserId = $sessionUserId;
        if ($userRole === 'admin' && isset($data['user_id'])) {
            $targetUserId = intval($data['user_id']);
        }

        // 🚨 FORENSIC LOGGING: Get the old values BEFORE we overwrite them!
        $oldRes = $conn->query("SELECT initial_stock, page_no FROM stock WHERE item_name = '$name' AND inventory_type = '$type' AND user_id = $targetUserId");
        $old = $oldRes ? $oldRes->fetch_assoc() : ['initial_stock' => '?', 'page_no' => '?'];
        
        $updateSql = "UPDATE stock SET page_no='$page_no', initial_stock = $initial_stock, current_stock = $initial_stock + in_qty - out_qty WHERE item_name = '$name' AND inventory_type = '$type' AND user_id = $targetUserId";
        
        if ($conn->query($updateSql)) {
            if (function_exists('writeLog') && isset($_SESSION['userId'])) {
                $changes = [];
                if ($old['initial_stock'] != $initial_stock) $changes[] = "Init Stock: {$old['initial_stock']} ➔ $initial_stock";
                if ($old['page_no'] != $page_no) $changes[] = "Page: {$old['page_no']} ➔ $page_no";
                
                $changeText = empty($changes) ? "No changes." : implode(" | ", $changes);
                $logMsg = "Updated stock info for <b>$name</b>.<br><span style='font-size:11px; color:var(--text-muted); line-height:1.6; display:block; margin-top:4px;'>🔍 <b>Changes:</b> $changeText</span>";

                writeLog($conn, $_SESSION['userId'], $_SESSION['hardwareUser'], "EDIT_ITEM", $logMsg);
            }
            echo json_encode(["success" => true, "message" => "Stock updated successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update stock: " . $conn->error]);
        }
        
    // === GET: Fetch Items ===
    } else {
        $type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : 'hardware';
        
        $targetUserId = $sessionUserId;
        if ($userRole === 'admin' && isset($_GET['user_id']) && $_GET['user_id'] !== "") {
            $targetUserId = intval($_GET['user_id']);
        }
        
        $where = "WHERE inventory_type = '$type' AND user_id = $targetUserId";
        
        // FIX: Added page_no to the SELECT query!
        $result = $conn->query("SELECT id, page_no, item_name, initial_stock, in_qty, out_qty, current_stock FROM stock $where ORDER BY item_name");
        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) $data[] = $row;
        }
        echo json_encode(["success" => true, "data" => $data]);
    }

} catch (\Throwable $e) {
    http_response_code(200);
    echo json_encode([
        "success" => false, 
        "message" => "PHP Crash Detected: " . $e->getMessage()
    ]);
}
$conn->close();
?>
<?php
// api_transactions.php

// 1. Start Session
session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include 'config.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "DB Connection Failed"]);
    exit;
}

// Handle Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🚨 MASTER KEY LOGIC 🚨
$sessionUserId = isset($_SESSION['userId']) ? intval($_SESSION['userId']) : 1;
$userRole = isset($_SESSION['userRole']) ? $_SESSION['userRole'] : 'user';

// ==========================================
// --- POST: Add Transaction (Single or Batch)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        echo json_encode(["success" => false, "message" => "Invalid JSON"]);
        exit;
    }

    // === BATCH IMPORT (Array of items) ===
    if (isset($data[0]) && is_array($data[0])) {
        // 🚨 FRONT-DOOR LOCK: Reject if the user is not an Admin
        if ($userRole !== 'admin') {
            echo json_encode(["success" => false, "message" => "Permission Denied: Only Admins can import Excel files."]);
            exit;
        }
        $count = 0;
        
        foreach ($data as $row) {
            $date = $conn->real_escape_string($row['date'] ?? date('Y-m-d'));
            $form_no = $conn->real_escape_string($row['form_no'] ?? '');
            $item_name = $conn->real_escape_string($row['item_name'] ?? '');
            $in_out = isset($row['in_out']) ? strtoupper(trim($row['in_out'])) : '';
            $qty = intval($row['qty'] ?? 0);
            $emp_id = $conn->real_escape_string($row['emp_id'] ?? '');
            $from_to = $conn->real_escape_string($row['from_to'] ?? '');
            $item_details = $conn->real_escape_string($row['item_details'] ?? '');
            $grn_number = $conn->real_escape_string($row['grn_number'] ?? '');
            $stock_entry = $conn->real_escape_string($row['stock_entry'] ?? '');
            $tag_no = $conn->real_escape_string($row['tag_no'] ?? '');
            $serial_no = $conn->real_escape_string($row['serial_no'] ?? '');
            $purpose = $conn->real_escape_string($row['purpose'] ?? '');
            $inventory_type = $conn->real_escape_string($row['inventory_type'] ?? 'hardware');

            // Set Target User ID safely
            $targetUserId = $sessionUserId;
            if ($userRole === 'admin' && isset($row['user_id'])) {
                $targetUserId = intval($row['user_id']);
            }

            // Validation inside loop
            // 1. Skip invalid rows
            if (empty($item_name) || !in_array($in_out, ['IN', 'OUT']) || $qty <= 0) continue;

            // 2. SMART DUPLICATE CHECKER (Finds the row using core identity)
            $dup_sql = "SELECT id FROM transactions WHERE 
                        item_name='$item_name' AND 
                        in_out='$in_out' AND 
                        qty=$qty AND 
                        date='$date' AND 
                        inventory_type='$inventory_type' AND 
                        user_id=$targetUserId AND 
                        form_no='$form_no' AND 
                        stock_entry='$stock_entry'"; 
            
            // Check employee ID separately to prevent empty string bugs
            if (!empty($emp_id)) {
                $dup_sql .= " AND emp_id='$emp_id'";
            }
            
            $checkDup = $conn->query($dup_sql);
            
            if ($checkDup && $checkDup->num_rows > 0) {
                // MATCH FOUND! Update the editable details instead of skipping.
                $existing = $checkDup->fetch_assoc();
                $existing_id = $existing['id'];
                
                $update_sql = "UPDATE transactions SET 
                               item_details='$item_details', 
                               grn_number='$grn_number', 
                               tag_no='$tag_no',
                               serial_no='$serial_no',
                               purpose='$purpose',
                               from_to='$from_to'
                               WHERE id=$existing_id";
                
                $conn->query($update_sql);
                
                // Skip the INSERT step below, because we just updated it!
                continue; 
            }

            // 3. INSERT THE CLEANED DATA (Only happens if it is a brand new transaction)
            $sql = "INSERT INTO transactions (date, form_no, item_name, in_out, qty, emp_id, from_to, item_details, grn_number, stock_entry, tag_no, serial_no, purpose, user_id, inventory_type) 
                    VALUES ('$date', '$form_no', '$item_name', '$in_out', $qty, '$emp_id', '$from_to', '$item_details', '$grn_number', '$stock_entry', '$tag_no', '$serial_no', '$purpose', $targetUserId, '$inventory_type')";

            if ($conn->query($sql)) {
                $count++;
                if ($in_out === 'IN') {
                    $conn->query("UPDATE stock SET in_qty = in_qty + $qty, current_stock = current_stock + $qty WHERE item_name = '$item_name' AND inventory_type = '$inventory_type' AND user_id=$targetUserId");
                } else {
                    $conn->query("UPDATE stock SET out_qty = out_qty + $qty, current_stock = current_stock - $qty WHERE item_name = '$item_name' AND inventory_type = '$inventory_type' AND user_id=$targetUserId");
                }
            }
        }

        if ($count > 0 && function_exists('writeLog')) {
            $logName = isset($_SESSION['hardwareUser']) ? $_SESSION['hardwareUser'] : "User-$sessionUserId";
            $typeUpper = strtoupper($inventory_type ?? 'HARDWARE');
            $desc = "<span style='color:var(--accent); font-weight:bold;'>[$typeUpper]</span> <strong>Excel Import (History):</strong> Successfully imported <b>$count</b> transaction records.";
            writeLog($conn, $sessionUserId, $logName, "IMPORT_HISTORY", $desc);
        }
        
        echo json_encode(["success" => true, "message" => "Batch Processed"]);
        exit;
    }

    // === SINGLE TRANSACTION LOGIC ===
    $date = $data['date'] ?? date('Y-m-d');
    $form_no = $conn->real_escape_string($data['form_no'] ?? '');
    $item_name = $conn->real_escape_string($data['item_name'] ?? '');
    $in_out = isset($data['in_out']) ? strtoupper(trim($data['in_out'])) : '';
    $qty = intval($data['qty'] ?? 0);
    $emp_id = $conn->real_escape_string($data['emp_id'] ?? '');
    $from_to = $conn->real_escape_string($data['from_to'] ?? '');
    $item_details = $conn->real_escape_string($data['item_details'] ?? '');
    
    $grn_number = $conn->real_escape_string($data['grn_number'] ?? '');
    $stock_entry = $conn->real_escape_string($data['stock_entry'] ?? ''); 
    $tag_no = $conn->real_escape_string($data['tag_no'] ?? '');
    $serial_no = $conn->real_escape_string($data['serial_no'] ?? '');
    $purpose = $conn->real_escape_string($data['purpose'] ?? ''); 
    $image_file = $conn->real_escape_string($data['image_file'] ?? '');
    $inventory_type = $conn->real_escape_string($data['inventory_type'] ?? 'hardware');
    
    $targetUserId = $sessionUserId;
    if ($userRole === 'admin' && isset($data['user_id'])) {
        $targetUserId = intval($data['user_id']);
    }

    if (empty($item_name) || !in_array($in_out, ['IN', 'OUT']) || $qty <= 0) {
        die(json_encode(["success" => false, "message" => "Please fill all required fields correctly."]));
    }

    if (empty($emp_id)) {
        die(json_encode(["success" => false, "message" => "⚠️ Please select an Employee ID!"]));
    }

    // 1. LOCAL STOCK CHECK
    if ($in_out === 'OUT') {
        $stockCheck = $conn->query("SELECT current_stock FROM stock WHERE item_name = '$item_name' AND inventory_type = '$inventory_type' AND user_id=$targetUserId");
        if ($stockCheck && $stockCheck->num_rows > 0) {
            $stockData = $stockCheck->fetch_assoc();
            $current_stock = intval($stockData['current_stock']);
            if ($qty > $current_stock) {
                die(json_encode(["success" => false, "message" => "⚠️ Insufficient Stock! You are trying to take out $qty, but only $current_stock are available."]));
            }
        } else {
            die(json_encode(["success" => false, "message" => "⚠️ Item not found in stock!"]));
        }
    }

    $snipeit_asset_id = 'NULL';
    $snipeit_user_id = 'NULL';
    $snipeit_synced = 0;

    // ==========================================
    // 🛡️ STRICT PRE-FLIGHT SNIPE-IT CHECK
    // ==========================================
    if (!function_exists('getSnipeITConfig')) {
        die(json_encode(["success" => false, "message" => "⚠️ Code Error: getSnipeITConfig missing in config.php!"]));
    }
    
    $snipe = getSnipeITConfig($conn);
    
    // 🚨 THIS IS THE STRICT LOCK: If tokens are missing, it blocks the transaction completely!
    if (empty($snipe['snipeit_url']) || empty($snipe['snipeit_token'])) {
        die(json_encode(["success" => false, "message" => "⚠️ Snipe-IT API Token or URL is missing! Please update your settings."]));
    }

    $snipeit_synced = 2; // Default to 'failed' if checks don't finish
    $raw_emp_id = trim(explode('-', $emp_id)[0]);
    $snipeUserId = null;
    
    // 🚨 FIX 1: Allow Employee Lookup for ALL tabs (Hardware, Consumable, AND Scrap!)
    if ($in_out === 'OUT' && !empty($raw_emp_id)) {
        $userData = snipeit_get("/users?search=" . urlencode($raw_emp_id), $snipe['snipeit_token'], $snipe['snipeit_url']);
        if (!empty($userData['rows']) && isset($userData['rows'][0]['id'])) {
            $snipeUserId = $userData['rows'][0]['id'];
        }
        if (!$snipeUserId) {
            die(json_encode(["success" => false, "message" => "⚠️ Snipe-IT Block: Employee ID '$raw_emp_id' not found in Snipe-IT!"]));
        }
        $snipeit_user_id = $snipeUserId;
    }

    // 💻 LANE 1: HARDWARE
    if ($inventory_type === 'hardware' && !empty($tag_no)) {
        $assetData = snipeit_get("/hardware/bytag/" . urlencode($tag_no), $snipe['snipeit_token'], $snipe['snipeit_url']);
        if (empty($assetData['id'])) die(json_encode(["success" => false, "message" => "⚠️ Snipe-IT Block: Asset Tag '$tag_no' not found!"]));
        $assetId = $assetData['id'];
        $snipeit_asset_id = $assetId;

        if ($in_out === 'OUT') {
            $response = snipeit_post("/hardware/$assetId/checkout", $snipe['snipeit_token'], $snipe['snipeit_url'], [
                "checkout_to_type" => "user", "assigned_user" => $snipeUserId, "note" => "Issued via Dashboard | Purpose: $purpose"
            ]);
            if (isset($response['status']) && $response['status'] == 'error') {
                die(json_encode(["success" => false, "message" => "Snipe-IT Blocked Checkout: " . json_encode($response['messages'])]));
            }
        } else { // IN (Checkin)
            $response = snipeit_post("/hardware/$assetId/checkin", $snipe['snipeit_token'], $snipe['snipeit_url'], ["note" => "Returned via Dashboard."]);
            if (isset($response['status']) && $response['status'] == 'error') {
                die(json_encode(["success" => false, "message" => "Snipe-IT Blocked Checkin: " . json_encode($response['messages'])]));
            }
        }
        $snipeit_synced = 1;
    }

    // 📦 LANE 2: CONSUMABLES
    elseif ($inventory_type === 'consumable' && $in_out === 'OUT') {
        $conData = snipeit_get("/consumables?search=" . urlencode($item_name), $snipe['snipeit_token'], $snipe['snipeit_url']);
        if (!empty($conData['rows']) && isset($conData['rows'][0]['id'])) {
            $consumableId = $conData['rows'][0]['id'];
            $snipeit_asset_id = $consumableId;
            $response = snipeit_post("/consumables/$consumableId/checkout", $snipe['snipeit_token'], $snipe['snipeit_url'], [
                "assigned_to" => $snipeUserId, "note" => "Issued via Dashboard | Purpose: $purpose"
            ]);
            if (isset($response['status']) && $response['status'] == 'error') {
                die(json_encode(["success" => false, "message" => "Snipe-IT Consumable Blocked: " . json_encode($response['messages'])]));
            }
            $snipeit_synced = 1;
        }
    }

    // ♻️ LANE 3: SCRAP
    elseif ($inventory_type === 'scrap' && !empty($tag_no)) {
        $assetData = snipeit_get("/hardware/bytag/" . urlencode($tag_no), $snipe['snipeit_token'], $snipe['snipeit_url']);
        if (empty($assetData['id'])) die(json_encode(["success" => false, "message" => "⚠️ Snipe-IT Block: Asset Tag '$tag_no' not found!"]));
        $assetId = $assetData['id'];
        $snipeit_asset_id = $assetId;

        $statusData = snipeit_get("/statuslabels", $snipe['snipeit_token'], $snipe['snipeit_url']);
        
        if ($in_out === 'IN') { 
            if (!empty($assetData['assigned_to'])) {
                snipeit_post("/hardware/$assetId/checkin", $snipe['snipeit_token'], $snipe['snipeit_url'], ["note" => "Auto-checkin before Scrap."]);
            }
            
            // 🚨 SMART FINDER: Specifically look for the exact word "Scrap" first
            $scrapStatusId = null;
            if (isset($statusData['rows'])) {
                foreach ($statusData['rows'] as $status) { if (strtolower($status['name']) === 'scrap') { $scrapStatusId = $status['id']; break; } }
                if (!$scrapStatusId) { foreach ($statusData['rows'] as $status) { if (strtolower($status['name']) === 'archived') { $scrapStatusId = $status['id']; break; } } }
                if (!$scrapStatusId) { foreach ($statusData['rows'] as $status) { if ($status['type'] === 'archived') { $scrapStatusId = $status['id']; break; } } }
            }
            if (!$scrapStatusId) die(json_encode(["success" => false, "message" => "⚠️ Snipe-IT Block: 'Archived/Scrap' label not found!"]));

            $response = snipeit_patch("/hardware/$assetId", $snipe['snipeit_token'], $snipe['snipeit_url'], [
                "status_id" => $scrapStatusId, "notes" => "Received into Scrap Store via Dashboard"
            ]);
            if (isset($response['status']) && $response['status'] == 'error') {
                die(json_encode(["success" => false, "message" => "Snipe-IT Blocked Scrap Update: " . json_encode($response['messages'])]));
            }
        } else { 
            
            // 🚨 SMART FINDER: Specifically look for the exact words "Ready to Deploy" first
            $deployStatusId = null;
            if (isset($statusData['rows'])) {
                foreach ($statusData['rows'] as $status) { if (strtolower($status['name']) === 'ready to deploy') { $deployStatusId = $status['id']; break; } }
                if (!$deployStatusId) { foreach ($statusData['rows'] as $status) { if ($status['type'] === 'deployable') { $deployStatusId = $status['id']; break; } } }
            }
            if (!$deployStatusId) die(json_encode(["success" => false, "message" => "⚠️ Snipe-IT Block: 'Ready to Deploy' status not found!"]));

            $patchRes = snipeit_patch("/hardware/$assetId", $snipe['snipeit_token'], $snipe['snipeit_url'], ["status_id" => $deployStatusId, "notes" => "Recovered from Scrap via Dashboard"]);
            if (isset($patchRes['status']) && $patchRes['status'] == 'error') {
                die(json_encode(["success" => false, "message" => "Snipe-IT Blocked Recovery: " . json_encode($patchRes['messages'])]));
            }

            if ($snipeUserId) {
                $checkoutRes = snipeit_post("/hardware/$assetId/checkout", $snipe['snipeit_token'], $snipe['snipeit_url'], [
                    "checkout_to_type" => "user", "assigned_user" => $snipeUserId, "note" => "Recovered & Issued via Dashboard"
                ]);
                if (isset($checkoutRes['status']) && $checkoutRes['status'] == 'error') {
                    die(json_encode(["success" => false, "message" => "Snipe-IT Blocked Checkout After Recovery: " . json_encode($checkoutRes['messages'])]));
                }
            }
        }
        $snipeit_synced = 1;
    
    }
    // ==========================================

    // ✅ IF WE GET HERE, SNIPE-IT APPROVED IT! NOW SAVE TO LOCAL DATABASE
    $sql = "INSERT INTO transactions (date, form_no, item_name, in_out, qty, emp_id, from_to, item_details, grn_number, stock_entry, image_file, tag_no, serial_no, purpose, user_id, inventory_type)
            VALUES ('$date', '$form_no', '$item_name', '$in_out', $qty, '$emp_id', '$from_to', '$item_details', '$grn_number', '$stock_entry', '$image_file', '$tag_no', '$serial_no', '$purpose', $targetUserId, '$inventory_type')";

    if ($conn->query($sql)) {
        $inserted_id = $conn->insert_id; 

        // Update Sync Trackers
        if ($snipeit_synced > 0) {
            $conn->query("UPDATE transactions SET snipeit_synced=$snipeit_synced WHERE id=$inserted_id");
            // Only update ID columns if they exist to prevent crashes
            $conn->query("UPDATE transactions SET snipeit_asset_id=$snipeit_asset_id WHERE id=$inserted_id");
            $conn->query("UPDATE transactions SET snipeit_user_id=$snipeit_user_id WHERE id=$inserted_id");
        }

        // Update Local Stock accurately (only ONCE!)
        if ($in_out === 'IN') {
            $conn->query("UPDATE stock SET in_qty = in_qty + $qty, current_stock = current_stock + $qty WHERE item_name = '$item_name' AND inventory_type = '$inventory_type' AND user_id=$targetUserId");
        } else {
            $conn->query("UPDATE stock SET out_qty = out_qty + $qty, current_stock = current_stock - $qty WHERE item_name = '$item_name' AND inventory_type = '$inventory_type' AND user_id=$targetUserId");
        }

        if(function_exists('writeLog')) {
            $typeUpper = strtoupper($inventory_type);
            $empDisplay = empty($emp_id) ? "No Employee" : "$emp_id ($from_to)";
            $actionColor = ($in_out === 'IN') ? "color:var(--success);" : "color:var(--danger);";
            
            $desc = "<span style='color:var(--accent); font-weight:bold;'>[$typeUpper]</span> ";
            $desc .= "<strong style='$actionColor'>$in_out</strong> : <b>$qty</b> x $item_name | Emp: <b>$empDisplay</b>";
            $logName = isset($_SESSION['hardwareUser']) ? $_SESSION['hardwareUser'] : "User-$targetUserId";
            writeLog($conn, $targetUserId, $logName, "NEW_TRANS", $desc);
        }
        
        echo json_encode(["success" => true, "message" => "Saved"]);
    } else {
        echo json_encode(["success" => false, "message" => "SQL Error: " . $conn->error]);
    }
    exit;
}

// ==========================================
// --- PUT: Update Transaction ---
// ==========================================
elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "ID required"]);
        exit;
    }

    $id = intval($data['id']);

    $oldRes = $conn->query("SELECT * FROM transactions WHERE id=$id");
    if($oldRes->num_rows == 0) {
        echo json_encode(["success" => false, "message" => "Transaction not found"]);
        exit;
    }
    $old = $oldRes->fetch_assoc();
    
    // 🚨 FIX: Force the correct old user ID so stock reverses accurately
    $old_user_id = intval($old['user_id']); 

    $date = $conn->real_escape_string($data['date']);
    $form_no = $conn->real_escape_string($data['form_no'] ?? '');
    $emp_id = $conn->real_escape_string($data['emp_id'] ?? '');
    $from_to = $conn->real_escape_string($data['from_to'] ?? '');
    $item_name = $conn->real_escape_string($data['item_name']);
    $in_out = strtoupper(trim($data['in_out']));
    $qty = intval($data['qty']);
    $item_details = $conn->real_escape_string($data['item_details']);
    $grn_number = $conn->real_escape_string($data['grn_number'] ?? '');
    $stock_entry = $conn->real_escape_string($data['stock_entry'] ?? ''); 
    $tag_no = $conn->real_escape_string($data['tag_no'] ?? '');
    $serial_no = $conn->real_escape_string($data['serial_no'] ?? '');
    $purpose = $conn->real_escape_string($data['purpose'] ?? '');
    $inventory_type = $conn->real_escape_string($data['inventory_type'] ?? 'hardware');

    // 🚨 FIX: Added user_id=$old_user_id to properly reverse old stock
    if($old['in_out'] == 'IN') {
        $conn->query("UPDATE stock SET in_qty = in_qty - {$old['qty']}, current_stock = current_stock - {$old['qty']} WHERE item_name = '{$old['item_name']}' AND inventory_type = '$inventory_type' AND user_id=$old_user_id");
    } else {
        $conn->query("UPDATE stock SET out_qty = out_qty - {$old['qty']}, current_stock = current_stock + {$old['qty']} WHERE item_name = '{$old['item_name']}' AND inventory_type = '$inventory_type' AND user_id=$old_user_id");
    }

    $image_update = "";
    if (isset($data['image_file']) && !empty($data['image_file'])) {
        $img = $conn->real_escape_string($data['image_file']);
        $image_update = ", image_file='$img'";
        
        // 🚨 FIX: Delete the old image from the server if they uploaded a new one!
        if (!empty($old['image_file']) && $old['image_file'] !== $data['image_file']) {
            $oldPath = "uploads/" . $old['image_file'];
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }
    }

    $sql = "UPDATE transactions SET 
            date='$date',
            form_no='$form_no',
            emp_id='$emp_id',
            from_to='$from_to',         
            item_name='$item_name', 
            in_out='$in_out', 
            qty=$qty, 
            item_details='$item_details', 
            grn_number='$grn_number', 
            stock_entry='$stock_entry', 
            tag_no='$tag_no',
            serial_no='$serial_no',
            purpose='$purpose',
            inventory_type='$inventory_type'
            $image_update 
            WHERE id=$id";

    if ($conn->query($sql)) {
        // 🚨 FIX: Added user_id=$old_user_id to properly apply the new edited stock
        if($in_out == 'IN') {
            $conn->query("UPDATE stock SET in_qty = in_qty + $qty, current_stock = current_stock + $qty WHERE item_name = '$item_name' AND inventory_type = '$inventory_type' AND user_id=$old_user_id");
        } else {
            $conn->query("UPDATE stock SET out_qty = out_qty + $qty, current_stock = current_stock - $qty WHERE item_name = '$item_name' AND inventory_type = '$inventory_type' AND user_id=$old_user_id");
        }

        // 🚨 FORENSIC LOGGING: Capture exact changes for ALL fields in the Audit Trail
        $changes = [];
        if ($old['date'] != $date) $changes[] = "Date: {$old['date']} ➔ $date";
        if ($old['form_no'] != $form_no) $changes[] = "Form: {$old['form_no']} ➔ $form_no";
        if ($old['item_name'] != $item_name) $changes[] = "Item: {$old['item_name']} ➔ $item_name";
        if ($old['in_out'] != $in_out) $changes[] = "Type: {$old['in_out']} ➔ $in_out";
        if ($old['qty'] != $qty) $changes[] = "Qty: {$old['qty']} ➔ $qty";
        if ($old['emp_id'] != $emp_id) $changes[] = "Emp: {$old['emp_id']} ➔ $emp_id";
        if ($old['from_to'] != $from_to) $changes[] = "Loc: {$old['from_to']} ➔ $from_to";
        if ($old['item_details'] != $item_details) $changes[] = "Details: {$old['item_details']} ➔ $item_details";
        if ($old['stock_entry'] != $stock_entry) $changes[] = "Entry: {$old['stock_entry']} ➔ $stock_entry";
        if ($old['grn_number'] != $grn_number) $changes[] = "GRN: {$old['grn_number']} ➔ $grn_number";
        if (($old['tag_no'] ?? '') != $tag_no) $changes[] = "Tag No: {$old['tag_no']} ➔ $tag_no";
        if (($old['serial_no'] ?? '') != $serial_no) $changes[] = "Serial: {$old['serial_no']} ➔ $serial_no";
        if (($old['purpose'] ?? '') != $purpose) $changes[] = "Purpose: {$old['purpose']} ➔ $purpose";
        
        if (isset($data['image_file']) && !empty($data['image_file']) && $old['image_file'] != $data['image_file']) {
            $changes[] = "Attachment Updated";
        }

        if (function_exists('writeLog') && isset($_SESSION['userId'])) {
            $log_item = $conn->real_escape_string($item_name);
            
            // Format the changes beautifully
            $changeText = empty($changes) ? "No values changed." : implode(" | ", $changes);
            
            $logMsg = "Edited <b>$in_out</b> transaction for <b>$log_item</b>.<br><span style='font-size:11px; color:var(--text-muted); line-height:1.6; display:block; margin-top:4px;'>🔍 <b>Changes:</b> $changeText</span>";
            
            writeLog($conn, $_SESSION['userId'], $_SESSION['hardwareUser'], "EDIT_TRANS", $logMsg);
        }

        echo json_encode(["success" => true, "message" => "Transaction Updated"]);
    } else {
        echo json_encode(["success" => false, "message" => "Update Failed: " . $conn->error]);
    }
    exit;
}

// ==========================================
// --- DELETE: Delete Transaction ---
// ==========================================
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    $targetUserId = $sessionUserId;
    if ($userRole === 'admin' && isset($data['user_id'])) {
        $targetUserId = intval($data['user_id']);
    }

    // 1. BULK DELETE
    if (isset($data['delete_all']) && $data['delete_all'] === true) {
        $inv_type = $conn->real_escape_string($data['inventory_type']);
        
        // 🚨 FIX: Find and delete all physical images attached to this tab before wiping the data!
        $imgRes = $conn->query("SELECT image_file FROM transactions WHERE user_id = $targetUserId AND inventory_type = '$inv_type' AND image_file IS NOT NULL AND image_file != ''");
        if ($imgRes) {
            while ($imgRow = $imgRes->fetch_assoc()) {
                $imgPath = "uploads/" . $imgRow['image_file'];
                if (file_exists($imgPath)) unlink($imgPath);
            }
        }
        
        $conn->query("DELETE FROM transactions WHERE user_id = $targetUserId AND inventory_type = '$inv_type'");
        $conn->query("DELETE FROM stock WHERE user_id = $targetUserId AND inventory_type = '$inv_type'");
        
        if (function_exists('writeLog') && isset($_SESSION['userId'])) {
            writeLog($conn, $_SESSION['userId'], $_SESSION['hardwareUser'], "WIPE_TAB", "Cleared all data in <b>$inv_type</b> tab");
        }
        
        echo json_encode(["success" => true, "message" => "Category wiped clean"]);
        exit;
    }
    
    // 2. SINGLE DELETE
    $id = intval($data['id'] ?? 0);

    // 🚨 FIX: Secure delete fetches user_id
   // 1. Fetch the image_file column as well
    $res = $conn->query("SELECT item_name, in_out, qty, inventory_type, stock_entry, form_no, user_id, image_file FROM transactions WHERE id = $id");
    if ($res && $row = $res->fetch_assoc()) {
        $item = $conn->real_escape_string($row['item_name']);
        $qty = intval($row['qty']);
        $type = $row['in_out'];
        $inv_type = $row['inventory_type']; 
        $owner_id = intval($row['user_id']);
        $img_file = $row['image_file']; // Grab the filename
        
        if ($userRole === 'admin' || $owner_id === $sessionUserId) {
            
            // 2. Physically delete the image from the server folder
            if (!empty($img_file)) {
                $imgPath = "uploads/" . $img_file;
                if (file_exists($imgPath)) {
                    unlink($imgPath); // Deletes the ghost image!
                }
            }
            // 🚨 FIX: Reverses stock with correct user_id filter
            if ($type === 'IN') {
                $conn->query("UPDATE stock SET in_qty = GREATEST(0, in_qty - $qty), current_stock = current_stock - $qty WHERE item_name = '$item' AND inventory_type = '$inv_type' AND user_id=$owner_id");
            } else {
                $conn->query("UPDATE stock SET out_qty = GREATEST(0, out_qty - $qty), current_stock = current_stock + $qty WHERE item_name = '$item' AND inventory_type = '$inv_type' AND user_id=$owner_id");
            }

            $conn->query("DELETE FROM transactions WHERE id = $id");
            
            if (function_exists('writeLog') && isset($_SESSION['userId'])) {
                $logMsg = "Deleted <b>$type</b> transaction for item: <b>$item</b> (Qty: $qty)";
                writeLog($conn, $_SESSION['userId'], $_SESSION['hardwareUser'], "DELETE_TRANS", $logMsg);
            }
            
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Transaction not found"]);
    }
    exit;
}

// ==========================================
// --- GET: Fetch Transactions ---
// ==========================================
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : 'hardware';
    $where = "WHERE inventory_type = '$type'";
    
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $s = $conn->real_escape_string($_GET['search']);
        $where .= " AND (item_name LIKE '%$s%' OR form_no LIKE '%$s%' OR emp_id LIKE '%$s%' OR tag_no LIKE '%$s%' OR serial_no LIKE '%$s%' OR purpose LIKE '%$s%')";
    }
    
    if ($userRole === 'admin') {
        if (isset($_GET['user_id']) && $_GET['user_id'] !== "") {
            $uid = intval($_GET['user_id']);
            $where .= " AND user_id = $uid";
        }
    } else {
        $where .= " AND user_id = $sessionUserId";
    }

    $result = $conn->query("SELECT * FROM transactions $where ORDER BY id DESC");
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    echo json_encode(["success" => true, "data" => $rows]);
    exit;
}

$conn->close();
?>
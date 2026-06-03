<?php
require 'config.php';

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "DB connection failed"]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["file"])) {
    $file = $_FILES["file"]["tmp_name"];
    
    // Check if file exists
    if (!file_exists($file)) {
        die(json_encode(["success" => false, "message" => "File not found"]));
    }

    // Use ZipArchive to read XLSX (standard approach for PHP without libraries)
    $zip = new ZipArchive();
    if (!$zip->open($file)) {
        die(json_encode(["success" => false, "message" => "Invalid Excel file"]));
    }

    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$xml) { 
        die(json_encode(["success" => false, "message" => "Cannot read Sheet1"])); 
    }

    // Extract shared strings for text values if needed (simplified here for numbers/inline strings)
    $dom = new DOMDocument();
    @$dom->loadXML($xml);
    $rows = $dom->getElementsByTagName('row');
    
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    // NEW: Capture the inventory type from the POST request (default to hardware if missing)
    $inv_type = isset($_POST['inventory_type']) ? $conn->real_escape_string($_POST['inventory_type']) : 'hardware';
    
    $firstRow = true;

    foreach ($rows as $row) {
        // Skip header row
        if ($firstRow) { $firstRow = false; continue; }

        $cells = $row->getElementsByTagName('c');
        $data = [];
        
        // Read cells into array
        foreach ($cells as $cell) {
            $data[] = $cell->nodeValue;
        }

        // --- MAP YOUR COLUMNS HERE ---
        // 0=Date, 1=Form, 2=Item, 3=In/Out, 4=Qty, ... 13=Initial Stock (Column N)
        $date       = isset($data[0]) ? $data[0] : date("Y-m-d");
        $raw_item_name = isset($data[2]) ? trim($data[2]) : "";
$item_name = $conn->real_escape_string($raw_item_name);
        $in_out     = isset($data[3]) ? strtoupper(trim($data[3])) : "";
        $qty        = isset($data[4]) ? intval($data[4]) : 0;
        
        // Read Initial Stock from Column 13 (N)
        $initial_stock = isset($data[13]) ? intval($data[13]) : 0; 

        if (empty($item_name) || $qty == 0) continue;

        // 1. Ensure Item Exists in Stock Table (Filtered by inventory_type)
        $check = $conn->query("SELECT id FROM stock WHERE item_name = '$item_name' AND user_id = $user_id AND inventory_type = '$inv_type'");
        
        if ($check->num_rows == 0) {
            // New Item: Insert it with the specific inventory_type
            $current = $initial_stock; // Start with initial
            $insert = $conn->prepare("INSERT INTO stock (item_name, initial_stock, in_qty, out_qty, current_stock, user_id, inventory_type) VALUES (?, ?, 0, 0, ?, ?, ?)");
            $insert->bind_param("siiis", $item_name, $initial_stock, $current, $user_id, $inv_type);
            $insert->execute();
        } else {
            // Existing Item: Update Initial Stock only if Excel has a value > 0 (Filtered by inventory_type)
            if ($initial_stock > 0) {
                $conn->query("UPDATE stock SET 
                    initial_stock = $initial_stock,
                    current_stock = $initial_stock + in_qty - out_qty
                    WHERE item_name = '$item_name' AND user_id = $user_id AND inventory_type = '$inv_type'");
            }
        }

        // 2. Insert Transaction with the specific inventory_type
        $stmt = $conn->prepare("INSERT INTO transactions (date, item_name, in_out, qty, user_id, inventory_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiis", $date, $item_name, $in_out, $qty, $user_id, $inv_type);
        
        if ($stmt->execute()) {
            // 3. Update Stock Counts based on this transaction (Filtered by inventory_type)
            if ($in_out === "IN") {
                $conn->query("UPDATE stock SET in_qty = in_qty + $qty, current_stock = current_stock + $qty WHERE item_name = '$item_name' AND user_id = $user_id AND inventory_type = '$inv_type'");
            } elseif ($in_out === "OUT") {
                $conn->query("UPDATE stock SET out_qty = out_qty + $qty, current_stock = current_stock - $qty WHERE item_name = '$item_name' AND user_id = $user_id AND inventory_type = '$inv_type'");
            }
        }
    }

    echo json_encode(["success" => true, "message" => "Import completed"]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
}
?>
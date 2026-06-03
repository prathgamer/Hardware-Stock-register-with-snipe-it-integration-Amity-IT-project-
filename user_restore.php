<?php
// user_restore.php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') die(json_encode(['success'=>false, 'message'=>'Access Denied']));
include 'config.php';

$target_uid = intval($_POST['user_id'] ?? 0);
// 🚨 NEW: Grab the specific tab they want to restore (defaults to 'all')
$restore_tab = isset($_POST['restore_tab']) ? $conn->real_escape_string($_POST['restore_tab']) : 'all';

if ($target_uid <= 0) die(json_encode(['success'=>false, 'message'=>'Please select a user to restore data into.']));
if (!isset($_FILES['backup_file'])) die(json_encode(['success'=>false, 'message'=>'No file uploaded.']));

$zip = new ZipArchive();
if ($zip->open($_FILES['backup_file']['tmp_name']) === TRUE) {
    
    // 1. Verify it's a User Backup file
    $json_data = $zip->getFromName('user_data.json');
    if (!$json_data) die(json_encode(['success'=>false, 'message'=>'Invalid file! Missing user_data.json.']));
    $data = json_decode($json_data, true);

    // ==========================================
    // 2. WIPE THE TARGET USER'S OLD DATA
    // ==========================================
    if ($restore_tab === 'all') {
        // Standard Wipe: Clears absolutely everything
        $conn->query("DELETE FROM stock WHERE user_id=$target_uid");
        $conn->query("DELETE FROM transactions WHERE user_id=$target_uid");
        $conn->query("DELETE FROM employees WHERE user_id=$target_uid");
    } else {
        // Surgical Wipe: Only clears the selected tab!
        $conn->query("DELETE FROM stock WHERE user_id=$target_uid AND inventory_type='$restore_tab'");
        $conn->query("DELETE FROM transactions WHERE user_id=$target_uid AND inventory_type='$restore_tab'");
        // We do NOT delete employees here, to keep them safe.
    }

    // ==========================================
    // 3. INSERT THE BACKED-UP DATA
    // ==========================================
    foreach(['stock', 'transactions', 'employees'] as $table) {
        if (!isset($data[$table])) continue;

        // Safety: If doing a selective tab restore, skip the employees table so we don't duplicate them
        if ($restore_tab !== 'all' && $table === 'employees') {
            continue;
        }

        foreach($data[$table] as $row) {
            
            // 🚨 THE FIREWALL: If they chose "Hardware Only", skip any Software/Network items!
            if ($restore_tab !== 'all' && ($table === 'stock' || $table === 'transactions')) {
                if (isset($row['inventory_type']) && $row['inventory_type'] !== $restore_tab) {
                    continue; // Skip this item, it belongs to a different tab!
                }
            }

            // 🚨 DATABASE FIX: Map old 'remarks' backups to the new 'tag_no' column!
            if ($table === 'transactions') {
                if (array_key_exists('remarks', $row)) {
                    $row['tag_no'] = $row['remarks']; // Move data to Tag No
                    unset($row['remarks']);           // Delete the old Remarks column
                }
                // Ensure new columns exist so it doesn't break
                if (!array_key_exists('serial_no', $row)) $row['serial_no'] = '';
                if (!array_key_exists('purpose', $row)) $row['purpose'] = '';
            }

            $cols = []; $vals = [];
            foreach($row as $k => $v) {
                $cols[] = "`$k`";
                if ($v === '{TARGET_USER}') {
                    $vals[] = $target_uid; // Map it to the selected user!
                } elseif ($v === null) {
                    $vals[] = "NULL";
                } else {
                    $vals[] = "'" . $conn->real_escape_string($v) . "'";
                }
            }
            $sql = "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
            $conn->query($sql);
        }
    }

    // ==========================================
    // 4. RESTORE IMAGES
    // ==========================================
    $temp_dir = 'temp_img_' . time() . '/';
    mkdir($temp_dir);
    $zip->extractTo($temp_dir);
    if (is_dir($temp_dir . 'uploads')) {
        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
        $files = scandir($temp_dir . 'uploads');
        foreach($files as $f) {
            if ($f != '.' && $f != '..') copy($temp_dir . 'uploads/' . $f, 'uploads/' . $f);
        }
    }
    
    // Clean up temporary files
    array_map('unlink', glob("$temp_dir/uploads/*.*"));
    @rmdir("$temp_dir/uploads");
    @unlink("$temp_dir/user_data.json");
    @rmdir($temp_dir);
    $zip->close();

    $success_msg = $restore_tab === 'all' ? 'All user data and images' : strtoupper($restore_tab) . ' data';
    echo json_encode(['success'=>true, 'message'=> $success_msg . ' successfully restored!']);
} else {
    echo json_encode(['success'=>false, 'message'=>'Cannot open zip file.']);
}
?>
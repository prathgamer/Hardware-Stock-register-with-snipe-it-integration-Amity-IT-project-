<?php
// restore_backup.php
session_start();
header('Content-Type: application/json');

// 🚨 SECURITY CHECK: Only Admins can restore the system!
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access Denied. Admins only.']);
    exit;
}

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];

    // Check for upload errors (like file too large)
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload failed. Error code: ' . $file['error']]);
        exit;
    }

    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) === TRUE) {
        
        // 1. Create a secure temporary folder to extract everything into
        $temp_dir = __DIR__ . '/temp_restore_' . time() . '/';
        mkdir($temp_dir);
        $zip->extractTo($temp_dir);
        $zip->close();

        // 2. Restore the Database
        $sql_files = glob($temp_dir . '*.sql');
        if (count($sql_files) > 0) {
            $sql_content = file_get_contents($sql_files[0]);

            // Disable foreign key checks temporarily so the import doesn't get stuck
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");

            if ($conn->multi_query($sql_content)) {
                // Clear out the query results to prevent sync errors
                do {
                    if ($res = $conn->store_result()) { $res->free(); }
                } while ($conn->more_results() && $conn->next_result());
            } else {
                deleteDir($temp_dir);
                echo json_encode(['success' => false, 'message' => 'Database restore failed: ' . $conn->error]);
                exit;
            }
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        }

        // 3. Restore the Uploads Folder (Images/Receipts)
        if (is_dir($temp_dir . 'uploads')) {
            $target_uploads = __DIR__ . '/uploads/';
            if (!is_dir($target_uploads)) mkdir($target_uploads, 0777, true);
            
            // Move all files from the zip into the real uploads folder
            $files = scandir($temp_dir . 'uploads');
            foreach ($files as $f) {
                if ($f !== '.' && $f !== '..') {
                    copy($temp_dir . 'uploads/' . $f, $target_uploads . $f);
                }
            }
        }

        // 4. Clean up the temporary folder
        deleteDir($temp_dir);

        // 5. Log the action
        if (function_exists('writeLog')) {
            writeLog($conn, $_SESSION['userId'], $_SESSION['hardwareUser'], "RESTORE", "System fully restored from Zip backup.");
        }

        echo json_encode(['success' => true, 'message' => 'System successfully restored!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not open ZIP file. Ensure it is a valid backup.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
}

// Helper function to delete folders
function deleteDir($dirPath) {
    if (!is_dir($dirPath)) return;
    $files = array_diff(scandir($dirPath), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dirPath/$file")) ? deleteDir("$dirPath/$file") : unlink("$dirPath/$file");
    }
    return rmdir($dirPath);
}
?>
<?php
// smart_user_backup.php
session_start();
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') die(json_encode(['success'=>false, 'message'=>'Access Denied']));
include 'config.php';

$action = $_GET['action'] ?? '';
$uid = intval($_GET['user_id'] ?? 0);
$zip_filename = __DIR__ . '/temp_user_backup_' . $uid . '.zip';
$upload_dir = __DIR__ . '/uploads/'; // 🚨 FIX: Absolute Pathing

if ($action === 'init') {
    if ($uid <= 0) die(json_encode(['success'=>false, 'message'=>'Invalid User']));
    if (file_exists($zip_filename)) unlink($zip_filename);
    
    $zip = new ZipArchive();
    $zip->open($zip_filename, ZipArchive::CREATE);
    
    $export = ['stock' => [], 'transactions' => [], 'employees' => []];
    $res = $conn->query("SELECT * FROM stock WHERE user_id=$uid");
    while($r = $res->fetch_assoc()) { unset($r['id']); $r['user_id'] = '{TARGET_USER}'; $export['stock'][] = $r; }

    $res = $conn->query("SELECT * FROM transactions WHERE user_id=$uid");
    while($r = $res->fetch_assoc()) { unset($r['id']); $r['user_id'] = '{TARGET_USER}'; $export['transactions'][] = $r; }

    $res = $conn->query("SELECT * FROM employees WHERE user_id=$uid");
    while($r = $res->fetch_assoc()) { unset($r['id']); $r['user_id'] = '{TARGET_USER}'; $export['employees'][] = $r; }

    $zip->addFromString('user_data.json', json_encode($export));
    $zip->close();

    // 🚨 FIX: Safe Queue Building for specific user
    $_SESSION['user_backup_queue'] = [];
    $imgRes = $conn->query("SELECT image_file FROM transactions WHERE user_id=$uid AND image_file IS NOT NULL AND image_file != ''");
    while($row = $imgRes->fetch_assoc()) {
        $img = $row['image_file'];
        if (file_exists($upload_dir . $img)) { // 🚨 FIX: Strictly checks absolute path
            $_SESSION['user_backup_queue'][] = $img;
        }
    }
    
    echo json_encode(['success' => true, 'total_files' => count($_SESSION['user_backup_queue'])]);
    exit;
}

if ($action === 'process') {
    if (!isset($_SESSION['user_backup_queue'])) die(json_encode(['success' => false]));
    
    // 🚨 FIX: Safely extract 50 items and resave the queue
    $queue = $_SESSION['user_backup_queue'];
    $batch = array_splice($queue, 0, 50);
    $_SESSION['user_backup_queue'] = $queue;
    
    if (count($batch) > 0) {
        $zip = new ZipArchive();
        if ($zip->open($zip_filename) === TRUE) {
            foreach ($batch as $file) {
                $fullPath = $upload_dir . $file;
                if (file_exists($fullPath)) {
                    $zip->addFile($fullPath, 'uploads/' . $file);
                }
            }
            $zip->close();
        }
    }
    
    echo json_encode(['success' => true, 'remaining' => count($_SESSION['user_backup_queue'])]);
    exit;
}

if ($action === 'download') {
    if (!file_exists($zip_filename)) die("Backup file not found!");
    $date = date('Y-m-d');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="User_'.$uid.'_Backup_'.$date.'.zip"');
    header('Content-Length: ' . filesize($zip_filename));
    readfile($zip_filename);
    
    unlink($zip_filename); 
    unset($_SESSION['user_backup_queue']);
    exit;
}
?>
<?php
// smart_backup.php
session_start();
// 🚨 Security: Admins only
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Access Denied']));
}

$action = $_GET['action'] ?? '';
$zip_filename = __DIR__ . '/temp_backup.zip';

if ($action === 'init') {
    // 1. Wipe any old interrupted backups
    if (file_exists($zip_filename)) unlink($zip_filename);
    
    // 2. Start a fresh Zip
    $zip = new ZipArchive();
    $zip->open($zip_filename, ZipArchive::CREATE);
    
    // 3. Add the Database JSON (Like we did in the user backup, but for everyone)
    include 'config.php';
    $export = ['stock' => [], 'transactions' => [], 'employees' => []];
    $res = $conn->query("SELECT * FROM stock"); while($r = $res->fetch_assoc()) $export['stock'][] = $r;
    $res = $conn->query("SELECT * FROM transactions"); while($r = $res->fetch_assoc()) $export['transactions'][] = $r;
    $res = $conn->query("SELECT * FROM employees"); while($r = $res->fetch_assoc()) $export['employees'][] = $r;
    $zip->addFromString('database_dump.json', json_encode($export));
    $zip->close();

    // 4. Find all images and save the list to the session
    $_SESSION['backup_queue'] = [];
    if (is_dir('uploads')) {
        $files = scandir('uploads');
        foreach ($files as $f) {
            if ($f !== '.' && $f !== '..') $_SESSION['backup_queue'][] = $f;
        }
    }
    
    echo json_encode(['success' => true, 'total_files' => count($_SESSION['backup_queue'])]);
    exit;
}

if ($action === 'process') {
    if (!isset($_SESSION['backup_queue'])) die(json_encode(['success' => false]));
    
    // 1. Grab the next 50 files from the queue
    $batch = array_splice($_SESSION['backup_queue'], 0, 50);
    
    if (count($batch) > 0) {
        $zip = new ZipArchive();
        if ($zip->open($zip_filename) === TRUE) {
            foreach ($batch as $file) {
                $zip->addFile('uploads/' . $file, 'uploads/' . $file);
            }
            $zip->close();
        }
    }
    
    echo json_encode(['success' => true, 'remaining' => count($_SESSION['backup_queue'])]);
    exit;
}

if ($action === 'download') {
    if (!file_exists($zip_filename)) die("Backup file not found!");
    
    $date = date('Y-m-d');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="Full_System_Backup_'.$date.'.zip"');
    header('Content-Length: ' . filesize($zip_filename));
    readfile($zip_filename);
    
    // Clean up after download
    unlink($zip_filename); 
    unset($_SESSION['backup_queue']);
    exit;
}
?>
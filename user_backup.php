<?php
// user_backup.php
session_start();
// 🚨 Security: Admins only
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') die("Access Denied");
include 'config.php';

$uid = intval($_GET['user_id'] ?? 0);
if ($uid <= 0) die("Invalid User ID");

$export = ['stock' => [], 'transactions' => [], 'employees' => []];

// 1. Fetch data & strip primary IDs so we can restore safely later
$res = $conn->query("SELECT * FROM stock WHERE user_id=$uid");
while($r = $res->fetch_assoc()) { unset($r['id']); $r['user_id'] = '{TARGET_USER}'; $export['stock'][] = $r; }

$res = $conn->query("SELECT * FROM transactions WHERE user_id=$uid");
while($r = $res->fetch_assoc()) { unset($r['id']); $r['user_id'] = '{TARGET_USER}'; $export['transactions'][] = $r; }

$res = $conn->query("SELECT * FROM employees WHERE user_id=$uid");
while($r = $res->fetch_assoc()) { unset($r['id']); $r['user_id'] = '{TARGET_USER}'; $export['employees'][] = $r; }

// 2. Create the Zip File
$date = date('Y-m-d_H-i-s');
$zip_filename = "User_{$uid}_Backup_{$date}.zip";
$zip = new ZipArchive();
$zip->open($zip_filename, ZipArchive::CREATE);

// Add the Data
$zip->addFromString('user_data.json', json_encode($export));

// 3. Add Only This User's Images
$res = $conn->query("SELECT image_file FROM transactions WHERE user_id=$uid AND image_file != ''");
while($r = $res->fetch_assoc()) {
    $img = 'uploads/' . $r['image_file'];
    if(file_exists($img)) $zip->addFile($img, $img);
}
$zip->close();

// 4. Download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$zip_filename.'"');
readfile($zip_filename);
unlink($zip_filename);
?>
<?php
// full_backup.php
session_start();
// 🚨 SECURITY CHECK: Only Admins can download backups!
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
    die("Access Denied: Only administrators can perform full system backups.");
}
include 'config.php'; 

// 1. Set the names for our files
$date_stamp = date('Y-m-d_H-i-s');
$sql_filename = "database_$date_stamp.sql";
$zip_filename = "Amity_Full_Backup_$date_stamp.zip";

// ==========================================
// PART 1: GENERATE THE DATABASE SQL DUMP
// ==========================================
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) { $tables[] = $row[0]; }

$sqlScript = "-- Amity-IT Hardware Full Backup\n";
$sqlScript .= "-- Generated: $date_stamp\n\n";

foreach ($tables as $table) {
    $result = $conn->query("SHOW CREATE TABLE $table");
    $row = $result->fetch_row();
    $sqlScript .= "\n\n" . $row[1] . ";\n\n";

    $result = $conn->query("SELECT * FROM $table");
    $columnCount = $result->field_count;

    for ($i = 0; $i < $columnCount; $i++) {
        while ($row = $result->fetch_row()) {
            $sqlScript .= "INSERT INTO $table VALUES(";
            for ($j = 0; $j < $columnCount; $j++) {
                if (isset($row[$j])) {
                    $cleaned = $conn->real_escape_string($row[$j]);
                    $sqlScript .= '"' . $cleaned . '"';
                } else {
                    $sqlScript .= 'NULL';
                }
                if ($j < ($columnCount - 1)) { $sqlScript .= ','; }
            }
            $sqlScript .= ");\n";
        }
    }
    $sqlScript .= "\n";
}

// ==========================================
// PART 2: CREATE THE ZIP FILE (SQL + UPLOADS)
// ==========================================
$zip = new ZipArchive();
if ($zip->open($zip_filename, ZipArchive::CREATE) !== TRUE) {
    die("Error: Could not create zip file.");
}

// Add the SQL file to the Zip
$zip->addFromString($sql_filename, $sqlScript);

// Add the 'uploads' folder to the Zip
$uploads_dir = 'uploads/';
if (is_dir($uploads_dir)) {
    // Scan all files in the uploads directory
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads_dir));
    foreach ($files as $name => $file) {
        // Skip directories (like '.' and '..')
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            // Maintain the 'uploads/...' folder structure inside the zip
            $relativePath = 'uploads/' . basename($filePath);
            $zip->addFile($filePath, $relativePath);
        }
    }
}

$zip->close();

// ==========================================
// PART 3: FORCE BROWSER TO DOWNLOAD THE ZIP
// ==========================================
if (file_exists($zip_filename)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zip_filename) . '"');
    header('Content-Length: ' . filesize($zip_filename));
    
    // Clear output buffer and send the zip
    flush();
    readfile($zip_filename);
    
    // Delete the zip from the server after downloading to save space
    unlink($zip_filename);
    exit;
} else {
    echo "Error: Zip file was not generated.";
}
?>
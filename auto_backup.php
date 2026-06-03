<?php
// local_backup.php
// A script designed to run locally on XAMPP and save to a folder
include __DIR__ . '/config.php'; 

// 1. Create a "backups" folder if it doesn't exist
$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// 2. Name the file with today's date
$backup_file_name = $backup_dir . 'hardware_backup_' . date('Y-m-d_H-i-s') . '.sql';

// 3. Gather the Database Tables
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) { $tables[] = $row[0]; }

$sqlScript = "-- Amity-IT Hardware Local Backup\n";
$sqlScript .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

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

// 4. Save the file to the folder
file_put_contents($backup_file_name, $sqlScript);
echo "Backup successfully saved to the backups folder: " . basename($backup_file_name);
?>
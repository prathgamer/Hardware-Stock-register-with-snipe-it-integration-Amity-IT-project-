<?php
// backup.php
session_start();
include 'config.php'; // Connects to your database ($conn)

// Generate the filename with today's date and time
$backup_file_name = 'amity_hardware_backup_' . date('Y-m-d_H-i-s') . '.sql';

// Fetch all tables in your database
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$sqlScript = "-- Amity-IT Hardware Database Backup\n";
$sqlScript .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

// Loop through each table and grab its structure and data
foreach ($tables as $table) {
    // 1. Get Table Structure
    $result = $conn->query("SHOW CREATE TABLE $table");
    $row = $result->fetch_row();
    $sqlScript .= "\n\n" . $row[1] . ";\n\n";

    // 2. Get Table Data
    $result = $conn->query("SELECT * FROM $table");
    $columnCount = $result->field_count;

    for ($i = 0; $i < $columnCount; $i++) {
        while ($row = $result->fetch_row()) {
            $sqlScript .= "INSERT INTO $table VALUES(";
            for ($j = 0; $j < $columnCount; $j++) {
                if (isset($row[$j])) {
                    // Clean the data to prevent SQL breaks
                    $cleaned = $conn->real_escape_string($row[$j]);
                    $sqlScript .= '"' . $cleaned . '"';
                } else {
                    $sqlScript .= 'NULL';
                }
                if ($j < ($columnCount - 1)) {
                    $sqlScript .= ',';
                }
            }
            $sqlScript .= ");\n";
        }
    }
    $sqlScript .= "\n";
}

// Force the browser to download the file instead of displaying it
header('Content-Type: application/x-sql');
header('Content-Disposition: attachment; filename=' . $backup_file_name);
header('Cache-Control: no-store, no-cache, must-revalidate');

echo $sqlScript;
exit;
?>
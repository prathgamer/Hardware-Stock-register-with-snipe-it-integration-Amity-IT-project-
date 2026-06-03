<?php
// upload.php
session_start();
header('Content-Type: application/json');

// 🚨 SECURITY FIX: Block unauthenticated users from uploading files directly!
if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $file = $_FILES['image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate Extension
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }

    // Handle Custom Name
    if (isset($_POST['custom_name']) && !empty(trim($_POST['custom_name']))) {
        // Sanitize the custom name (remove special chars)
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['custom_name']));
        $filename = $safe_name . "." . $ext;
    } else {
        // Fallback to random name if empty
        $filename = uniqid() . "." . $ext;
    }

    $target_file = $target_dir . $filename;

    // Check if file already exists, if so, append number
    $counter = 1;
    while(file_exists($target_file)) {
        $filename = $safe_name . "_" . $counter . "." . $ext;
        $target_file = $target_dir . $filename;
        $counter++;
    }

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move file. Check folder permissions.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or invalid request']);
}
?>
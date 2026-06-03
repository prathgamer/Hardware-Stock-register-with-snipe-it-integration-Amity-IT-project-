<?php
// check_admin.php

// 1. Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdmin() {
    // Check if user is logged in at all
    if (!isset($_SESSION['userId'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Authentication required"]);
        exit;
    }

    // Check if user has admin role
    if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Admin access required"]);
        exit;
    }
}

function requireAuth() {
    if (!isset($_SESSION['userId'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Authentication required"]);
        exit;
    }
}
?>

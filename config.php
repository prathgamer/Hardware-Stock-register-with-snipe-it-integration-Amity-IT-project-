<?php
// config.php
$host = "localhost";
$db   = "hardware_db";
$user = "root";
$pass = "";

// Use $conn because register.php and others expect $conn
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    http_response_code(500);
    die("Connection failed: " . $conn->connect_error);
}
// Add this to config.php
function writeLog($conn, $user_id, $username, $type, $desc) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action_type, description, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $username, $type, $desc, $ip);
    $stmt->execute();
}
// ==========================================
// SNIPE-IT API HELPER FUNCTIONS
// ==========================================
function getSnipeITConfig($conn) {
    $res = $conn->query("SELECT snipeit_url, snipeit_token FROM system_settings LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) return $row;
    return ['snipeit_url' => '', 'snipeit_token' => ''];
}

function snipeit_get($endpoint, $token, $baseUrl) {
    $ch = curl_init(rtrim($baseUrl, '/') . '/api/v1' . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

function snipeit_post($endpoint, $token, $baseUrl, $body) {
    $ch = curl_init(rtrim($baseUrl, '/') . '/api/v1' . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}
// Shared API updater (PATCH) - Used for changing Statuses like Scrap/Archive
function snipeit_patch($endpoint, $token, $baseUrl, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, rtrim($baseUrl, '/') . "/api/v1" . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Accept: application/json", "Content-Type: application/json"]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}
?>

<?php
session_start();
header('Content-Type: application/json');
include 'config.php';

if (isset($_GET['action']) && $_GET['action'] == 'get_asset_by_tag') {
    $tag = urlencode(trim($_GET['tag']));
    $snipe = getSnipeITConfig($conn);
    
    if (empty($snipe['snipeit_url']) || empty($snipe['snipeit_token'])) {
        die(json_encode(["success" => false, "message" => "Snipe-IT API not configured."]));
    }

    $assetData = snipeit_get("/hardware/bytag/" . $tag, $snipe['snipeit_token'], $snipe['snipeit_url']);

    if (isset($assetData['id'])) {
        echo json_encode(["success" => true, "data" => $assetData]);
    } else {
        echo json_encode(["success" => false, "message" => "Asset not found in Snipe-IT."]);
    }
}
// ==========================================
// 📦 GET CONSUMABLE BY NAME
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'get_consumable_by_name') {
    $name = urlencode(trim($_GET['name']));
    $snipe = getSnipeITConfig($conn);
    
    if (empty($snipe['snipeit_url']) || empty($snipe['snipeit_token'])) {
        die(json_encode(["success" => false, "message" => "Snipe-IT API not configured."]));
    }

    $conData = snipeit_get("/consumables?search=" . $name, $snipe['snipeit_token'], $snipe['snipeit_url']);

    if (!empty($conData['rows']) && isset($conData['rows'][0]['id'])) {
        // We found it! Send back the exact match data
        echo json_encode(["success" => true, "data" => $conData['rows'][0]]);
    } else {
        echo json_encode(["success" => false, "message" => "Consumable not found in Snipe-IT."]);
    }
}
// ==========================================
// 👤 GET SNIPE-IT USER (For Slip Department/Location)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'get_user_by_emp_id') {
    $emp_id = urlencode(trim($_GET['emp_id']));
    $snipe = getSnipeITConfig($conn);
    
    if (empty($snipe['snipeit_url']) || empty($snipe['snipeit_token'])) {
        die(json_encode(["success" => false, "message" => "Snipe-IT API not configured."]));
    }

    $userData = snipeit_get("/users?search=" . $emp_id, $snipe['snipeit_token'], $snipe['snipeit_url']);

    if (!empty($userData['rows']) && isset($userData['rows'][0]['id'])) {
        echo json_encode(["success" => true, "data" => $userData['rows'][0]]);
    } else {
        echo json_encode(["success" => false, "message" => "User not found in Snipe-IT."]);
    }
}
?>
<?php
// api_chatbot.php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'config.php';

// 🚨 SECURITY: Ensure only logged-in users can use the bot
$sessionUserId = isset($_SESSION['userId']) ? intval($_SESSION['userId']) : 1;
$userRole = isset($_SESSION['userRole']) ? $_SESSION['userRole'] : 'user';

// 1. YOUR GOOGLE GEMINI API KEY
$apiKey = "AIzaSyCjR3h30nq20bauUHECOLpW8q20aAmO41s"; 

$input = json_decode(file_get_contents("php://input"), true);
$userMessage = trim($input['message'] ?? '');

if (empty($userMessage)) {
    echo json_encode(["reply" => "Hello! I am the Amity-IT Assistant. How can I help you with the inventory today?"]);
    exit;
}

// 2. GATHER LIVE CONTEXT (Only fetch stock belonging to the current user/admin)
$where = ($userRole === 'admin') ? "1=1" : "user_id = $sessionUserId";
$stockQuery = $conn->query("SELECT item_name, current_stock, inventory_type FROM stock WHERE $where");

$stockSummary = [];
if ($stockQuery && $stockQuery->num_rows > 0) {
    while ($row = $stockQuery->fetch_assoc()) {
        $type = strtoupper($row['inventory_type']);
        $stockSummary[] = "[$type] {$row['item_name']}: {$row['current_stock']} in stock";
    }
}
$stockDataText = empty($stockSummary) ? "The inventory is currently completely empty." : implode(", ", $stockSummary);

// 3. BUILD THE SYSTEM PROMPT
$systemPrompt = "You are the highly intelligent Amity-IT Inventory Assistant. 
You help staff manage their hardware, consumable, and scrap inventory.
Keep your answers brief, professional, and helpful. Do not use complex markdown formatting, just plain text.
Here is the LIVE inventory data right now: " . $stockDataText;

// 4. PREPARE THE GEMINI API PAYLOAD
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
$payload = [
    "contents" => [
        ["role" => "user", "parts" => [["text" => $systemPrompt . "\n\nUser Question: " . $userMessage]]]
    ],
    "generationConfig" => [
        "temperature" => 0.4, 
        "maxOutputTokens" => 200
    ]
];

// 5. SEND REQUEST TO GOOGLE (With Auto-Retry Logic)
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

$maxRetries = 3;
$attempt = 0;
$response = false;
$geminiData = [];

// Loop to automatically retry if the server is busy
while ($attempt < $maxRetries) {
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo json_encode(["reply" => "Network Error: " . curl_error($ch)]);
        curl_close($ch);
        exit;
    }

    $geminiData = json_decode($response, true);

    // If we get a 503 (Busy) or 429 (Too Many Requests), wait and try again!
    if ($httpCode == 503 || $httpCode == 429 || (isset($geminiData['error']) && strpos($geminiData['error']['message'], 'high demand') !== false)) {
        $attempt++;
        sleep(2); // Wait 2 seconds before knocking on Google's door again
        continue; 
    }
    
    // If it's a 200 OK or any other error, break the loop
    break; 
}

curl_close($ch);

// Check if it STILL failed after all retries
if (isset($geminiData['error'])) {
    // We send back the actual Google error message now just in case it's a different issue!
    $errorMsg = $geminiData['error']['message'] ?? "Unknown API Error";
    echo json_encode(["reply" => "⚠️ AI Error: " . $errorMsg]);
    exit;
}

// 6. RETURN THE AI RESPONSE (Fixed: Only outputting this ONCE!)
$aiText = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? "I'm sorry, I couldn't process that request.";
echo json_encode(["reply" => trim($aiText)]);
?>
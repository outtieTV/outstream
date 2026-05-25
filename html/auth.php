<?php
/**
 * Nginx RTMP Stream Authentication Handshake Handler
 * Reads valid stream names and tokens dynamically from an external auth.json database.
 */

// 1. Ensure the request is coming via POST from Nginx RTMP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.1 405 Method Not Allowed");
    echo "Method Not Allowed. Expected POST application handshake.";
    exit;
}

// 2. Extract incoming parameters sent by OBS / Nginx RTMP
// Nginx RTMP naturally passes the stream key identity inside $_POST['name']
$streamIdentity = isset($_POST['name']) ? trim($_POST['name']) : '';
// Custom parameter passed from your OBS configuration (e.g., ?key=yourToken)
$providedToken  = isset($_POST['key'])  ? trim($_POST['key'])  : '';

// Fallback logic: Check if OBS combined them into a single string inside 'name'
if (empty($providedToken) && !empty($streamIdentity) && strpos($streamIdentity, '?') !== false) {
    // If OBS sent: runescape?name=runescape&key=mySecretPassword123
    $urlParts = parse_url("rtmp://localhost/" . $streamIdentity);
    if (isset($urlParts['query'])) {
        parse_str($urlParts['query'], $queryArgs);
        
        // Clean up the stream key base name
        if (isset($urlParts['path'])) {
            $streamIdentity = trim($urlParts['path'], '/');
        }
        // Extract the parsed security token
        if (isset($queryArgs['key'])) {
            $providedToken = trim($queryArgs['key']);
        }
    }
}

// Block empty submission handshakes immediately
if (empty($streamIdentity) || empty($providedToken)) {
    header("HTTP/1.1 400 Bad Request");
    echo "Authentication Handshake Failed: Missing identity or verification token.";
    exit;
}

// 3. Locate and load the auth.json file safely
$jsonFilePath = __DIR__ . '/auth.json';

if (!file_exists($jsonFilePath)) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Authentication System Error: Database file is missing.";
    exit;
}

$jsonData = file_get_contents($jsonFilePath);
$authDatabase = json_decode($jsonData, true);

// Ensure the JSON data is parsed correctly as an associative array
if ($authDatabase === null || !is_array($authDatabase)) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Authentication System Error: Database layout is malformed.";
    exit;
}

// 4. Validate credentials against your JSON database
// Using hash_equals to protect against timing analysis attacks during validation
if (array_key_exists($streamIdentity, $authDatabase) && hash_equals($authDatabase[$streamIdentity], $providedToken)) {
    // HTTP 200 OK tells Nginx RTMP to accept the stream and start writing HLS fragments
    header("HTTP/1.1 200 OK");
    echo "Authentication Successful. Streaming access granted.";
    exit;
} else {
    // HTTP 404 or 403 tells Nginx RTMP to terminate the streaming hook immediately
    header("HTTP/1.1 404 Not Found");
    echo "Authentication Failed: Invalid stream name or secret key token.";
    exit;
}

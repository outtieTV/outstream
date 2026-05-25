<?php
require_once __DIR__ . '/config.php';
$config = load_config();
header('Content-Type: application/json');

// Sanitize stream key - CRITICAL CHANGE: Added "\/" to the allowed list to support path categories
$streamKey = isset($_POST['streamkey'])
    ? preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $_POST['streamkey'])
    : '';

if (empty($streamKey)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid stream key.'
    ]);
    exit;
}

// Ensure the root clip directory exists
$clipDir = '/var/www/clip/';
$hlsBase = 'http://127.0.0.1:9090/hls/';

// Properly construct the structural path. E.g., http://127.0.0.1:9090/hls/gaming/outtie_live/index.m3u8
// Using rawurlencode per segment keeps structural slashes safe
$segments = explode('/', $streamKey);
$encodedSegments = array_map('rawurlencode', $segments);
$playlistUrl = $hlsBase . implode('/', $encodedSegments) . '/index.m3u8';

// Fetch playlist
$playlist = @file_get_contents($playlistUrl);

if ($playlist === false) {
    echo json_encode([
        'success' => false,
        'error' => 'Live stream not found.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Parse HLS playlist duration
|--------------------------------------------------------------------------
|
| Count EXTINF durations to determine how much video exists.
|
*/

preg_match_all('/#EXTINF:([0-9\.]+)/', $playlist, $matches);

if (empty($matches[1])) {
    echo json_encode([
        'success' => false,
        'error' => 'No HLS segments available yet.'
    ]);
    exit;
}

$totalDuration = 0;

foreach ($matches[1] as $duration) {
    $totalDuration += floatval($duration);
}

// Maximum clip length
$clipLength = min(60, floor($totalDuration));

// Prevent zero-second clips
if ($clipLength < 1) {
    $clipLength = 1;
}

// Estimate segment count
// Assumes ~1s fragments
$segmentCount = $clipLength;


// CRITICAL CHANGE: Flatten the output file name or handle the nested subdirectories.
// Option A (Highly Recommended): Flatten the slashes into underscores for the output video file
// This avoids needing to create hundreds of matching folders inside your clips storage layout.
// Example: "gaming/outtie_live" becomes "gaming_outtie_live_clip_1716558482.mp4"
$flatStreamKey = str_replace('/', '_', $streamKey);
$clipName = $flatStreamKey . '_clip_' . time() . '.mp4';
$outputPath = $clipDir . $clipName;

/*
|--------------------------------------------------------------------------
| FFmpeg command
|--------------------------------------------------------------------------
|
| -live_start_index negative values count backward
| from live edge.
|
*/

$cmd = sprintf(
    'ffmpeg ' .
    '-y ' .
    '-live_start_index -%d ' .
    '-i %s ' .
    '-t %d ' .
    '-c:v copy -c:a aac ' .
    '-bsf:a aac_adtstoasc ' .
    '%s 2>&1',
    $segmentCount,
    escapeshellarg($playlistUrl),
    $clipLength,
    escapeshellarg($outputPath)
);

exec($cmd, $output, $returnStatus);

if ($returnStatus === 0) {
    // Read the base URL from the JSON config
    $downloadBaseUrl = $config['downloadBaseUrl']; 

    echo json_encode([
        'success' => true,
        'message' => 'Clip saved successfully!',
        'file' => $clipName,
        'url' => $downloadBaseUrl . rawurlencode($clipName),
        'duration' => $clipLength
    ]);
    exit;
} else {
    echo json_encode([
        'success' => false,
        'error' => 'FFmpeg failed.',
        'details' => $output
    ]);
}

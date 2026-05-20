<?php
header('Content-Type: application/json');

// Sanitize stream key
$streamKey = isset($_POST['streamkey'])
    ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['streamkey'])
    : '';

if (empty($streamKey)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid stream key.'
    ]);
    exit;
}

$clipDir = '/var/www/clip/';
$hlsBase = 'http://127.0.0.1:9090/hls/';

$playlistUrl = $hlsBase . $streamKey . '.m3u8';

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
// Assumes ~2s fragments
$segmentCount = ceil($clipLength / 2);

// Generate filename
$clipName = $streamKey . '_clip_' . time() . '.mp4';
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
    '-c copy ' .
    '-bsf:a aac_adtstoasc ' .
    '%s 2>&1',
    $segmentCount,
    escapeshellarg($playlistUrl),
    $clipLength,
    escapeshellarg($outputPath)
);

exec($cmd, $output, $returnStatus);

if ($returnStatus === 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Clip saved successfully!',
        'file' => $clipName,
        'duration' => $clipLength
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'FFmpeg failed.',
        'details' => $output
    ]);
}


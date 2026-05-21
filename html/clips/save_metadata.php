<?php
header('Content-Type: application/json');

$clipDir = "/var/www/clip";

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode([
        "success" => false,
        "error" => "Invalid JSON payload."
    ]);
    exit;
}

$file = isset($input['file'])
    ? basename($input['file'])
    : '';

$start = isset($input['start'])
    ? intval($input['start'])
    : 0;

$end = isset($input['end'])
    ? intval($input['end'])
    : 0;

$title = isset($input['title'])
    ? trim($input['title'])
    : '';

$description = isset($input['description'])
    ? trim($input['description'])
    : '';

if (empty($file)) {
    echo json_encode([
        "success" => false,
        "error" => "Missing file."
    ]);
    exit;
}

$fullPath = $clipDir . '/' . $file;

if (!file_exists($fullPath)) {
    echo json_encode([
        "success" => false,
        "error" => "Video file does not exist."
    ]);
    exit;
}

$allowedExtensions = ['mp4', 'webm', 'mov'];

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    echo json_encode([
        "success" => false,
        "error" => "Unsupported video type."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate duration using ffprobe
|--------------------------------------------------------------------------
*/
$escaped = escapeshellarg($fullPath);

$duration = trim(shell_exec("
ffprobe -v error \
-show_entries format=duration \
-of default=noprint_wrappers=1:nokey=1 \
$escaped
"));

$duration = is_numeric($duration)
    ? floatval($duration)
    : 0;

if ($duration <= 0) {
    echo json_encode([
        "success" => false,
        "error" => "Could not read video duration."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate clip range
|--------------------------------------------------------------------------
*/
if ($start < 0) {
    $start = 0;
}

if ($end > floor($duration)) {
    $end = floor($duration);
}

if ($start >= $end) {
    echo json_encode([
        "success" => false,
        "error" => "Start time must be less than end time."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Default title fallback
|--------------------------------------------------------------------------
*/
$baseName = pathinfo($file, PATHINFO_FILENAME);

if ($title === '') {
    $title = $baseName;
}

/*
|--------------------------------------------------------------------------
| Build metadata JSON
|--------------------------------------------------------------------------
*/
$metadata = [
    "filename" => $file,
    "title" => $title,
    "description" => $description,
    "start" => $start,
    "end" => $end,
    "clip_length" => ($end - $start),
    "created_at" => date('c'),
    "video_duration" => floor($duration)
];

/*
|--------------------------------------------------------------------------
| Save JSON beside video
|--------------------------------------------------------------------------
| Example:
| runescape_clip.mp4
| runescape_clip.json
|--------------------------------------------------------------------------
*/
$jsonPath = $clipDir . '/' . $baseName . '.json';

$result = file_put_contents(
    $jsonPath,
    json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

if ($result === false) {
    echo json_encode([
        "success" => false,
        "error" => "Failed to save metadata file."
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Metadata saved successfully.",
    "json" => basename($jsonPath)
]);

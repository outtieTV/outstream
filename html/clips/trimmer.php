<?php
$clipDir = "/var/www/clip";

$allowedExtensions = ['mp4', 'webm', 'mov'];

$file = isset($_GET['file']) ? basename($_GET['file']) : '';

if (!$file) {
    die("No file specified.");
}

$fullPath = $clipDir . '/' . $file;

if (!file_exists($fullPath)) {
    die("File not found.");
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    die("Unsupported file type.");
}

$baseName = pathinfo($file, PATHINFO_FILENAME);

/*
|--------------------------------------------------------------------------
| Detect transcoding conflict
|--------------------------------------------------------------------------
| Example:
| runescape_clip.flv
| runescape_clip.mp4
|
| If more than one file exists with the same basename but different
| extensions, we assume transcoding/rendering is still happening.
|--------------------------------------------------------------------------
*/
$matchingFiles = glob($clipDir . '/' . $baseName . '.*');

$videoMatches = [];

foreach ($matchingFiles as $match) {
    $matchExt = strtolower(pathinfo($match, PATHINFO_EXTENSION));

    if (in_array($matchExt, ['mp4', 'flv', 'webm', 'mov'])) {
        $videoMatches[] = $match;
    }
}

if (count($videoMatches) > 1) {
    die("
    <html>
    <head>
        <title>Transcoding In Progress</title>
        <style>
            body {
                background: #0a0a0a;
                color: white;
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }

            .box {
                background: #151515;
                border: 1px solid #2d2d2d;
                border-radius: 18px;
                padding: 30px;
                max-width: 500px;
                text-align: center;
            }

            h1 {
                margin-top: 0;
                color: #f87171;
            }

            a {
                color: #93c5fd;
                text-decoration: none;
            }

            a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class='box'>
            <h1>⚠ Transcoding In Progress</h1>

            <p>
                Multiple video formats were detected for this clip.
            </p>

            <p>
                Please wait until transcoding/rendering has completed
                before trimming this video.
            </p>

            <br>

            <a href='/clips/'>← Back to Clip Library</a>
        </div>
    </body>
    </html>
    ");
}

/*
|--------------------------------------------------------------------------
| Get duration using ffprobe
|--------------------------------------------------------------------------
*/
$escaped = escapeshellarg($fullPath);

$duration = trim(shell_exec("
ffprobe -v error \
-show_entries format=duration \
-of default=noprint_wrappers=1:nokey=1 \
$escaped
"));

$duration = is_numeric($duration) ? floatval($duration) : 0;

if ($duration <= 0) {
    die("Could not read video duration.");
}

$durationRounded = floor($duration);

function formatTime($seconds)
{
    $seconds = floor($seconds);

    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;

    if ($h > 0) {
        return sprintf("%02d:%02d:%02d", $h, $m, $s);
    }

    return sprintf("%02d:%02d", $m, $s);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Clip Trimmer</title>

<style>
:root {
    --bg: #050505;
    --card: #111111;
    --border: #242424;
    --text: #f5f5f5;
    --muted: #9ca3af;
    --accent: #3b82f6;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 24px;
    background: radial-gradient(circle at top, #111827 0%, #050505 60%);
    color: var(--text);
    font-family: "Segoe UI", sans-serif;
}

.container {
    max-width: 1100px;
    margin: auto;
}

.card {
    background: rgba(17,17,17,0.94);
    border: 1px solid var(--border);
    border-radius: 24px;
    overflow: hidden;
}

.player-wrap {
    background: black;
}

video {
    width: 100%;
    max-height: 620px;
    background: black;
}

.content {
    padding: 24px;
}

h1 {
    margin-top: 0;
}

.meta {
    color: var(--muted);
    margin-bottom: 22px;
}

.slider-group {
    margin-bottom: 28px;
}

label {
    display: block;
    margin-bottom: 10px;
    font-weight: bold;
}

.range-wrap {
    display: flex;
    align-items: center;
    gap: 14px;
}

.range-wrap input[type=range] {
    flex: 1;
}

.time {
    min-width: 80px;
    text-align: right;
    color: #93c5fd;
    font-weight: bold;
}

input[type=range] {
    width: 100%;
}

.text-input,
.text-area {
    width: 100%;
    background: #161616;
    border: 1px solid #2f2f2f;
    color: white;
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 18px;
    font-size: 14px;
}

.text-area {
    min-height: 140px;
    resize: vertical;
}

.info-box {
    background: #121212;
    border: 1px solid #2c2c2c;
    border-radius: 16px;
    padding: 18px;
    margin-bottom: 22px;
    color: var(--muted);
}

.actions {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
}

.btn {
    background: #1a1a1a;
    border: 1px solid #2f2f2f;
    color: white;
    padding: 14px 20px;
    border-radius: 14px;
    text-decoration: none;
    cursor: pointer;
    transition: 0.2s ease;
    font-size: 14px;
}

.btn:hover {
    background: #2563eb;
    border-color: #2563eb;
}

.primary {
    background: #2563eb;
    border-color: #2563eb;
}

.primary:hover {
    opacity: 0.9;
}

.warning {
    color: #fca5a5;
    margin-top: 10px;
}

.preview-time {
    margin-top: 14px;
    color: #93c5fd;
    font-weight: bold;
}
</style>
</head>
<body>

<div class="container">

    <a href="/clips/" class="btn" style="display:inline-block;margin-bottom:18px;">
        ← Back to Clip Library
    </a>

    <div class="card">

        <div class="player-wrap">
            <video
                id="videoPlayer"
                controls
                preload="metadata"
            >
                <source
                    src="/clip/<?php echo rawurlencode($file); ?>"
                    type="video/<?php echo $ext; ?>"
                >
            </video>
        </div>

        <div class="content">

            <h1>✂ Clip Trimmer</h1>

            <div class="meta">
                <?php echo htmlspecialchars($file); ?>
                •
                Total Length:
                <?php echo formatTime($durationRounded); ?>
            </div>

            <div class="info-box">
                Adjust the start and end sliders to define the clip range.

                <br><br>

                Title and description are optional metadata and do not
                change the output filename.
            </div>

            <div class="slider-group">
                <label>Start Time</label>

                <div class="range-wrap">
                    <input
                        type="range"
                        id="startSlider"
                        min="0"
                        max="<?php echo $durationRounded; ?>"
                        value="0"
                        step="1"
                    >

                    <div class="time" id="startTime">
                        00:00
                    </div>
                </div>
            </div>

            <div class="slider-group">
                <label>End Time</label>

                <div class="range-wrap">
                    <input
                        type="range"
                        id="endSlider"
                        min="0"
                        max="<?php echo $durationRounded; ?>"
                        value="<?php echo $durationRounded; ?>"
                        step="1"
                    >

                    <div class="time" id="endTime">
                        <?php echo formatTime($durationRounded); ?>
                    </div>
                </div>
            </div>

            <div class="preview-time">
                Clip Length:
                <span id="clipLength">
                    <?php echo formatTime($durationRounded); ?>
                </span>
            </div>

            <br>

            <input
                type="text"
                id="clipTitle"
                class="text-input"
                placeholder="Clip Title (optional)"
            >

            <textarea
                id="clipDescription"
                class="text-area"
                placeholder="Clip Description (optional)"
            ></textarea>

            <div class="actions">
                <button class="btn primary" onclick="saveMetadata()">
                    Save Metadata
                </button>

                <button class="btn" onclick="previewStart()">
                    Preview Start
                </button>

                <button class="btn" onclick="previewEnd()">
                    Preview End
                </button>
            </div>

            <div id="status" style="margin-top:18px;"></div>

        </div>
    </div>
</div>

<script>
const duration = <?php echo $durationRounded; ?>;

const startSlider = document.getElementById('startSlider');
const endSlider = document.getElementById('endSlider');

const startTime = document.getElementById('startTime');
const endTime = document.getElementById('endTime');

const clipLength = document.getElementById('clipLength');

const player = document.getElementById('videoPlayer');

function formatTime(seconds) {
    seconds = Math.floor(seconds);

    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;

    if (h > 0) {
        return String(h).padStart(2, '0') + ':' +
               String(m).padStart(2, '0') + ':' +
               String(s).padStart(2, '0');
    }

    return String(m).padStart(2, '0') + ':' +
           String(s).padStart(2, '0');
}

function updateUI() {
    let start = parseInt(startSlider.value);
    let end = parseInt(endSlider.value);

    if (start > end - 1) {
        start = end - 1;
        startSlider.value = start;
    }

    if (end < start + 1) {
        end = start + 1;
        endSlider.value = end;
    }

    if (start < 0) {
        start = 0;
        startSlider.value = 0;
    }

    if (end > duration) {
        end = duration;
        endSlider.value = duration;
    }

    startTime.textContent = formatTime(start);
    endTime.textContent = formatTime(end);

    clipLength.textContent = formatTime(end - start);
}

startSlider.addEventListener('input', updateUI);
endSlider.addEventListener('input', updateUI);

function previewStart() {
    player.currentTime = parseInt(startSlider.value);
    player.play();
}

function previewEnd() {
    player.currentTime = parseInt(endSlider.value);
    player.play();
}

async function saveMetadata() {
    const title = document.getElementById('clipTitle').value.trim();
    const description = document.getElementById('clipDescription').value.trim();

    const payload = {
        file: <?php echo json_encode($file); ?>,
        start: parseInt(startSlider.value),
        end: parseInt(endSlider.value),
        title: title,
        description: description
    };

    try {
        const response = await fetch('/clips/save_metadata.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (data.success) {
            document.getElementById('status').innerHTML =
                '<span style="color:#86efac;">Metadata saved successfully.</span>';
        } else {
            document.getElementById('status').innerHTML =
                '<span class="warning">' + data.error + '</span>';
        }
    } catch (err) {
        console.error(err);

        document.getElementById('status').innerHTML =
            '<span class="warning">Failed to save metadata.</span>';
    }
}

updateUI();
</script>

</body>
</html>

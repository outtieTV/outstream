<?php
$dir = "/var/www/clip";

$allowed = ['mp4', 'flv', 'webm', 'mov'];
$nativePlayable = ['mp4', 'webm', 'mov'];

$files = array_values(array_filter(scandir($dir), function($f) use ($dir) {
    return $f !== '.' &&
           $f !== '..' &&
           is_file($dir . '/' . $f);
}));

$data = [];

foreach ($files as $file) {

    $path = $dir . '/' . $file;

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        continue;
    }

    $streamKey = "unknown";

    if (preg_match('/^([a-zA-Z0-9_-]+)_/i', $file, $matches)) {
        $streamKey = strtolower($matches[1]);
    }

    /*
    |--------------------------------------------------------------------------
    | Load optional JSON metadata
    |--------------------------------------------------------------------------
    */
    $baseName = pathinfo($file, PATHINFO_FILENAME);

    $jsonPath = $dir . '/' . $baseName . '.json';

    $metadata = null;

    if (file_exists($jsonPath)) {

        $json = json_decode(file_get_contents($jsonPath), true);

        if (is_array($json)) {
            $metadata = $json;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Determine display title
    |--------------------------------------------------------------------------
    */
    $displayTitle = $metadata['title'] ?? $file;

    /*
    |--------------------------------------------------------------------------
    | Description
    |--------------------------------------------------------------------------
    */
    $description = $metadata['description'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Clip start/end
    |--------------------------------------------------------------------------
    */
    $clipStart = isset($metadata['start'])
        ? intval($metadata['start'])
        : 0;

    $clipEnd = isset($metadata['end'])
        ? intval($metadata['end'])
        : 0;

    $clipLength = isset($metadata['clip_length'])
        ? intval($metadata['clip_length'])
        : 0;

    /*
    |--------------------------------------------------------------------------
    | Has metadata?
    |--------------------------------------------------------------------------
    */
    $isEditedClip = file_exists($jsonPath);

    $data[] = [
        "name" => $file,
        "streamKey" => $streamKey,
        "mtime" => filemtime($path),
        "size" => filesize($path),
        "ext" => $ext,
        "playable" => in_array($ext, $nativePlayable),

        "edited" => $isEditedClip,
        "title" => $displayTitle,
        "description" => $description,

        "clipStart" => $clipStart,
        "clipEnd" => $clipEnd,
        "clipLength" => $clipLength
    ];
}

/*
|--------------------------------------------------------------------------
| Newest first
|--------------------------------------------------------------------------
*/
usort($data, function($a, $b) {
    return $b["mtime"] <=> $a["mtime"];
});

function formatTime($seconds)
{
    $seconds = intval($seconds);

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

<title>Clip Library</title>

<link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />

<style>
:root {
    --bg: #050505;
    --card: #111111;
    --card-hover: #171717;
    --border: #232323;
    --text: #f5f5f5;
    --muted: #9ca3af;
    --accent: #3b82f6;
    --green: #10b981;
    --edited: #f59e0b;
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

h1 {
    margin-bottom: 8px;
    font-size: 38px;
}

.subtitle {
    color: var(--muted);
    margin-bottom: 24px;
}

.topbar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

input,
button,
select {
    background: #161616;
    border: 1px solid #2a2a2a;
    color: white;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 14px;
}

input {
    flex: 1;
    min-width: 260px;
}

button {
    cursor: pointer;
    transition: 0.2s ease;
}

button:hover {
    background: #202020;
}

.stats {
    margin-bottom: 20px;
    color: var(--muted);
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 20px;
}

.card {
    background: rgba(17,17,17,0.92);
    border: 1px solid var(--border);
    border-radius: 22px;
    overflow: hidden;
    transition:
        transform 0.2s ease,
        background 0.2s ease,
        border-color 0.2s ease;
    cursor: pointer;
}

.card:hover {
    transform: translateY(-4px);
    background: var(--card-hover);
    border-color: #3b82f6;
}

.preview {
    position: relative;
    width: 100%;
    height: 220px;
    background: black;
    overflow: hidden;
}

.preview video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    background: black;
}

.badge {
    position: absolute;
    top: 12px;
    left: 12px;

    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(10px);

    padding: 6px 10px;
    border-radius: 999px;

    font-size: 12px;
    font-weight: bold;
    color: white;
}

.edited-badge {
    position: absolute;
    top: 12px;
    right: 12px;

    background: rgba(245, 158, 11, 0.9);

    padding: 6px 10px;
    border-radius: 999px;

    font-size: 12px;
    font-weight: bold;
    color: white;
}

.info {
    padding: 18px;
}

.stream {
    color: #93c5fd;
    font-weight: bold;
    margin-bottom: 8px;
}

.filename {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 10px;
    word-break: break-word;
}

.realfile {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 12px;
    word-break: break-word;
}

.description {
    color: #d1d5db;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 14px;
    white-space: pre-wrap;
}

.meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
    color: var(--muted);
    font-size: 13px;
}

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    flex: 1;
    text-align: center;
    text-decoration: none;
    background: #1a1a1a;
    border: 1px solid #2f2f2f;
    color: white;
    padding: 10px 14px;
    border-radius: 12px;
    transition: 0.2s ease;
    font-size: 14px;
    cursor: pointer;
}

.btn:hover {
    background: #2563eb;
    border-color: #2563eb;
}

.home-link {
    display: inline-block;
    margin-bottom: 20px;
    color: #93c5fd;
    text-decoration: none;
}

.home-link:hover {
    text-decoration: underline;
}

.empty {
    opacity: 0.7;
    margin-top: 40px;
    text-align: center;
}

/* Modal */

.vjs-modal {
    display: none;
    position: fixed;

    top: 0;
    left: 0;

    width: 100%;
    height: 100%;

    background: rgba(0, 0, 0, 0.85);

    z-index: 9999;

    justify-content: center;
    align-items: center;
}

.vjs-modal-content {
    width: 90%;
    max-width: 960px;

    background: #000;

    position: relative;

    border-radius: 8px;
    overflow: hidden;
}

.vjs-modal-close {
    position: absolute;

    top: 10px;
    right: 15px;

    font-size: 28px;
    color: #fff;

    cursor: pointer;
    z-index: 10001;

    background: rgba(0,0,0,0.5);

    border: none;
    padding: 0 8px;

    border-radius: 4px;
}

@media (max-width: 700px) {

    body {
        padding: 16px;
    }

    .grid {
        grid-template-columns: 1fr;
    }

    .preview {
        height: 200px;
    }

    .video-js {
        width: 100% !important;
        height: auto !important;
        aspect-ratio: 16/9;
    }
}
</style>
</head>

<body>

<a href="/" class="home-link">
    â† Back to Stream Player
</a>

<h1>ðŸŽ¬ Clip Library</h1>

<div class="subtitle">
    Browse, preview, and share saved clips
</div>

<div class="topbar">

    <input
        type="text"
        id="searchBox"
        placeholder="Search stream key, title, description, or filename..."
        onkeyup="filterClips()"
    >

    <button id="sortBtn" onclick="toggleSort()">
        Sort: Newest â†’ Oldest
    </button>

</div>

<div class="stats">
    Total Clips:
    <span id="clipCount"><?php echo count($data); ?></span>
</div>

<div class="grid" id="clipGrid">

<?php foreach ($data as $clip): ?>

<?php $clipUrl = "/clip/" . rawurlencode($clip["name"]); ?>

<div
    class="card"

    data-name="<?php echo strtolower($clip["name"]); ?>"
    data-stream="<?php echo strtolower($clip["streamKey"]); ?>"
    data-title="<?php echo strtolower($clip["title"]); ?>"
    data-description="<?php echo strtolower($clip["description"]); ?>"
    data-mtime="<?php echo $clip["mtime"]; ?>"

    <?php if ($clip["playable"]): ?>
    ondblclick="openPlayer(
        '<?php echo $clipUrl; ?>',
        'video/<?php echo $clip['ext']; ?>',
        <?php echo intval($clip['clipStart']); ?>,
        <?php echo intval($clip['clipEnd']); ?>
    )"
    title="Double click to play"
    <?php endif; ?>
>

    <div class="preview">

        <video
            muted
            preload="metadata"
            loop
            playsinline
            onmouseenter="this.play()"
            onmouseleave="this.pause(); this.currentTime = 0.5;"
        >
            <source
                src="<?php echo $clipUrl; ?>#t=<?php echo max(0, $clip['clipStart']); ?>"
                type="video/<?php echo $clip["ext"]; ?>"
            >
        </video>

        <div class="badge">
            <?php echo strtoupper($clip["ext"]); ?>
        </div>

        <?php if ($clip["edited"]): ?>
        <div class="edited-badge">
            âœ‚ Edited Clip
        </div>
        <?php endif; ?>

    </div>

    <div class="info">

        <div class="stream">
            <?php echo htmlspecialchars($clip["streamKey"]); ?>
        </div>

        <div class="filename">
            <?php echo htmlspecialchars($clip["title"]); ?>
        </div>

        <?php if ($clip["edited"]): ?>
        <div class="realfile">
            <?php echo htmlspecialchars($clip["name"]); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($clip["description"])): ?>
        <div class="description">
            <?php echo nl2br(htmlspecialchars($clip["description"])); ?>
        </div>
        <?php endif; ?>

        <div class="meta">

            <span>
                <?php echo round($clip["size"] / 1024 / 1024, 2); ?> MB
            </span>

            <span>
                <?php echo date("Y-m-d H:i:s", $clip["mtime"]); ?>
            </span>

            <?php if ($clip["edited"]): ?>
            <span>
                Clip:
                <?php echo formatTime($clip["clipStart"]); ?>
                â†’
                <?php echo formatTime($clip["clipEnd"]); ?>
            </span>

            <span>
                Length:
                <?php echo formatTime($clip["clipLength"]); ?>
            </span>
            <?php endif; ?>

        </div>

        <div class="actions">

            <?php if ($clip["playable"]): ?>

                <button
                    class="btn"
                    onclick="openPlayer(
                        '<?php echo $clipUrl; ?>',
                        'video/<?php echo $clip['ext']; ?>',
                        <?php echo intval($clip['clipStart']); ?>,
                        <?php echo intval($clip['clipEnd']); ?>
                    )"
                >
                    Open Player
                </button>

                <a
                    class="btn"
                    href="/clips/trimmer.php?file=<?php echo rawurlencode($clip["name"]); ?>"
                >
                    Clip Trimmer
                </a>

            <?php else: ?>

                <a
                    class="btn"
                    href="<?php echo $clipUrl; ?>"
                    download
                >
                    Download
                </a>

            <?php endif; ?>

            <button
                class="btn"
                onclick="copyClipURL('<?php echo $clipUrl; ?>')"
            >
                Copy URL
            </button>

        </div>

    </div>

</div>

<?php endforeach; ?>

</div>

<?php if (count($data) === 0): ?>

<div class="empty">

    <h2>No clips found</h2>

    <p>Your generated clips will appear here.</p>

</div>

<?php endif; ?>

<div id="playerModal" class="vjs-modal">

    <div class="vjs-modal-content">

        <button
            class="vjs-modal-close"
            onclick="closePlayer()"
        >
            Ã—
        </button>

        <video
            id="my-video"
            class="video-js vjs-default-skin vjs-big-play-centered"
            controls
            preload="auto"
            width="960"
            height="540"
        >
            <p class="vjs-no-js">
                To view this video please enable JavaScript
            </p>
        </video>

    </div>

</div>

<script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>

<script>
let newestFirst = true;

let player = videojs('my-video');

let activeClipEnd = null;

function openPlayer(src, type, startTime = 0, endTime = 0) {

    document.getElementById('playerModal').style.display = 'flex';

    activeClipEnd = endTime > startTime
        ? endTime
        : null;

    player.src({
        type: type,
        src: src
    });

    player.ready(function() {

        player.currentTime(startTime);

        player.play();
    });
}

/*
|--------------------------------------------------------------------------
| Stop playback at clip end
|--------------------------------------------------------------------------
*/
player.on('timeupdate', function() {

    if (
        activeClipEnd !== null &&
        player.currentTime() >= activeClipEnd
    ) {

        player.pause();

        player.currentTime(activeClipEnd);
    }
});

function closePlayer() {

    player.pause();

    document.getElementById('playerModal').style.display = 'none';

    activeClipEnd = null;
}

document.getElementById('playerModal').addEventListener('click', function(e) {

    if (e.target === this) {
        closePlayer();
    }
});

function toggleSort() {

    const grid = document.getElementById('clipGrid');

    const cards = Array.from(
        grid.querySelectorAll('.card')
    );

    cards.sort((a, b) => {

        const at = parseInt(a.dataset.mtime);
        const bt = parseInt(b.dataset.mtime);

        return newestFirst
            ? at - bt
            : bt - at;
    });

    grid.innerHTML = '';

    cards.forEach(card => {
        grid.appendChild(card);
    });

    newestFirst = !newestFirst;

    document.getElementById('sortBtn').textContent =
        newestFirst
            ? 'Sort: Newest â†’ Oldest'
            : 'Sort: Oldest â†’ Newest';
}

function filterClips() {

    const query = document
        .getElementById('searchBox')
        .value
        .toLowerCase();

    const cards = document.querySelectorAll('.card');

    let visible = 0;

    cards.forEach(card => {

        const name = card.dataset.name;
        const stream = card.dataset.stream;
        const title = card.dataset.title;
        const description = card.dataset.description;

        const match =
            name.includes(query) ||
            stream.includes(query) ||
            title.includes(query) ||
            description.includes(query);

        card.style.display = match ? '' : 'none';

        if (match) {
            visible++;
        }
    });

    document.getElementById('clipCount').textContent = visible;
}

async function copyClipURL(path) {

    const url = window.location.origin + path;

    try {

        await navigator.clipboard.writeText(url);

        alert('Copied URL:\\n' + url);

    } catch(err) {

        console.error(err);

        alert('Failed to copy URL.');
    }
}
</script>

</body>
</html>


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
$basenameMap = [];

foreach ($files as $file) {
    $path = $dir . '/' . $file;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        continue;
    }

    $baseName = pathinfo($file, PATHINFO_FILENAME);

    if (!isset($basenameMap[$baseName])) {
        $basenameMap[$baseName] = [];
    }

    $basenameMap[$baseName][] = $ext;

    $category = "";
    $streamKey = "unknown";

    // UPDATED: Regex checks for the nested category flat pattern (category_streamkey_clip_timestamp)
    if (preg_match('/^([a-zA-Z0-9_\-]+)_([a-zA-Z0-9_\-]+)_clip_\d+$/i', $baseName, $matches)) {
        $category = strtolower($matches[1]);
        $streamKey = strtolower($matches[2]);
    } 
    // Fallback logic for older single flat format files (streamkey_clip_timestamp)
    elseif (preg_match('/^([a-zA-Z0-9_\-]+)_clip_\d+$/i', $baseName, $matches)) {
        $streamKey = strtolower($matches[1]);
    }
    // Generic legacy fallback
    elseif (preg_match('/^([a-zA-Z0-9_\-]+)_/i', $file, $matches)) {
        $streamKey = strtolower($matches[1]);
    }

    /*
    |--------------------------------------------------------------------------
    | Metadata
    |--------------------------------------------------------------------------
    */
    $jsonPath = $dir . '/' . $baseName . '.json';
    $metadata = [];

    if (file_exists($jsonPath)) {
        $decoded = json_decode(
            file_get_contents($jsonPath),
            true
        );

        if (is_array($decoded)) {
            $metadata = $decoded;
        }
    }

    $displayTitle = $metadata['title'] ?? $file;
    $description = $metadata['description'] ?? '';

    $clipStart = isset($metadata['start']) ? intval($metadata['start']) : 0;
    $clipEnd = isset($metadata['end']) ? intval($metadata['end']) : 0;
    $clipLength = isset($metadata['clip_length']) ? intval($metadata['clip_length']) : 0;
    $isEditedClip = file_exists($jsonPath);

    $data[] = [
        "name" => $file,
        "baseName" => $baseName,
        "category" => $category,
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

foreach ($data as &$item) {
    $item["transcoding"] = count($basenameMap[$item["baseName"]]) > 1;
}
unset($item);

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

input, button {
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

/*
|--------------------------------------------------------------------------
| GRID VIEW
|--------------------------------------------------------------------------
*/
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
    transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease;
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

.warning {
    margin-top: 12px;
    background: rgba(255, 153, 0, 0.12);
    border: 1px solid rgba(255, 153, 0, 0.35);
    color: #ffcc66;
    padding: 10px;
    border-radius: 12px;
    font-size: 13px;
}

.info {
    padding: 18px;
}

.stream {
    color: #93c5fd;
    font-weight: bold;
    margin-bottom: 8px;
}

.stream .cat-prefix {
    color: #a7f3d0;
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

/*
|--------------------------------------------------------------------------
| LIST VIEW
|--------------------------------------------------------------------------
*/
.list-view {
    display: none;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(17,17,17,0.92);
    border-radius: 16px;
    overflow: hidden;
}

th, td {
    padding: 14px;
    border-bottom: 1px solid #1f2937;
    text-align: left;
}

th {
    background: #161616;
}

tbody tr {
    cursor: pointer;
    transition: 0.15s ease;
}

tbody tr:hover {
    background: #171717;
}

.type-badge {
    display: inline-block;
    background: #1e293b;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: bold;
}

/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/
.vjs-modal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.85);
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
    top: 10px; right: 15px;
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
    table, thead, tbody, tr, td, th {
        display: block;
    }
    thead {
        display: none;
    }
    tr {
        margin-bottom: 16px;
        border-bottom: 1px solid #1f2937;
    }
    td {
        border-bottom: none;
    }
}
</style>
</head>

<body>

<h1>🎬 Clip Library</h1>

<div class="subtitle">
    Browse, preview, and share saved clips
</div>

<div class="topbar">
    <input
        type="text"
        id="searchBox"
        placeholder="Search clips..."
        onkeyup="filterClips()"
    >

    <button id="sortBtn" onclick="toggleSort()">
        Sort: Newest → Oldest
    </button>

    <button onclick="setView('grid')">
        Grid View
    </button>

    <button onclick="setView('list')">
        List View
    </button>
</div>

<div class="stats">
    Total Clips: <span id="clipCount"><?php echo count($data); ?></span>
</div>

<div class="grid" id="gridView">
<?php foreach ($data as $clip): ?>
<?php $clipUrl = "/clip/" . rawurlencode($clip["name"]); ?>

<div
    class="card searchable"
    data-name="<?php echo strtolower($clip["name"]); ?>"
    data-stream="<?php echo strtolower($clip["streamKey"]); ?>"
    data-category="<?php echo strtolower($clip["category"]); ?>"
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
            onmouseleave="
                this.pause();
                this.currentTime = 0.5;
            "
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
            ✂ Edited Clip
        </div>
        <?php endif; ?>
    </div>

    <div class="info">
        <div class="stream">
            <?php if (!empty($clip["category"])): ?>
                <span class="cat-prefix"><?php echo htmlspecialchars($clip["category"]); ?></span> / 
            <?php endif; ?>
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
                Clip: <?php echo formatTime($clip["clipStart"]); ?> → <?php echo formatTime($clip["clipEnd"]); ?>
            </span>
            <?php endif; ?>
        </div>

        <div class="actions">
            <?php if ($clip["playable"]): ?>
            <button
                class="btn"
                onclick="event.stopPropagation();
                openPlayer(
                    '<?php echo $clipUrl; ?>',
                    'video/<?php echo $clip['ext']; ?>',
                    <?php echo intval($clip['clipStart']); ?>,
                    <?php echo intval($clip['clipEnd']); ?>
                )"
            >
                Open Player
            </button>
            <?php endif; ?>

            <a
                class="btn"
                href="/clips/save_metadata.php?file=<?php echo rawurlencode($clip["name"]); ?>"
                onclick="event.stopPropagation();"
            >
                Edit Metadata
            </a>
        </div>

        <?php if ($clip["transcoding"]): ?>
        <div class="warning">
            ⚠️ Multiple filetypes detected.<br>
            Transcoding may still be in progress.
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<div class="list-view" id="listView">
<table>
<thead>
<tr>
    <th>Category / Stream</th>
    <th>Title</th>
    <th>Type</th>
    <th>Size</th>
    <th>Modified</th>
</tr>
</thead>
<tbody id="tableBody">
<?php foreach ($data as $clip): ?>
<?php $clipUrl = "/clip/" . rawurlencode($clip["name"]); ?>

<tr
    class="searchable"
    data-name="<?php echo strtolower($clip["name"]); ?>"
    data-stream="<?php echo strtolower($clip["streamKey"]); ?>"
    data-category="<?php echo strtolower($clip["category"]); ?>"
    data-title="<?php echo strtolower($clip["title"]); ?>"
    data-description="<?php echo strtolower($clip["description"]); ?>"
    data-mtime="<?php echo $clip["mtime"]; ?>"

    <?php if ($clip["playable"]): ?>
    onclick="openPlayer(
        '<?php echo $clipUrl; ?>',
        'video/<?php echo $clip['ext']; ?>',
        <?php echo intval($clip['clipStart']); ?>,
        <?php echo intval($clip['clipEnd']); ?>
    )"
    title="Click to play"
    <?php endif; ?>
>
    <td>
        <?php if (!empty($clip["category"])): ?>
            <span style="color:#a7f3d0;"><?php echo htmlspecialchars($clip["category"]); ?></span> / 
        <?php endif; ?>
        <?php echo htmlspecialchars($clip["streamKey"]); ?>
    </td>

    <td>
        <strong><?php echo htmlspecialchars($clip["title"]); ?></strong>
        <?php if ($clip["transcoding"]): ?>
        <br>
        <small style="color:#ffcc66;">
            ⚠️ Transcoding in progress
        </small>
        <?php endif; ?>
    </td>

    <td>
        <span class="type-badge">
            <?php echo strtoupper($clip["ext"]); ?>
        </span>
    </td>

    <td>
        <?php echo round($clip["size"] / 1024 / 1024, 2); ?> MB
    </td>

    <td>
        <?php echo date("Y-m-d H:i:s", $clip["mtime"]); ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div id="playerModal" class="vjs-modal">
    <div class="vjs-modal-content">
        <button class="vjs-modal-close" onclick="closePlayer()">×</button>
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

/*
|--------------------------------------------------------------------------
| PLAYER
|--------------------------------------------------------------------------
*/
function openPlayer(src, type, startTime = 0, endTime = 0) {
    document.getElementById('playerModal').style.display = 'flex';

    activeClipEnd = endTime > startTime ? endTime : null;

    player.src({
        type: type,
        src: src
    });

    player.ready(function() {
        player.currentTime(startTime);
        player.play();
    });
}

player.on('timeupdate', function() {
    if (activeClipEnd !== null && player.currentTime() >= activeClipEnd) {
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

/*
|--------------------------------------------------------------------------
| VIEW SWITCHING
|--------------------------------------------------------------------------
*/
function setView(view) {
    document.getElementById('gridView').style.display = view === 'grid' ? 'grid' : 'none';
    document.getElementById('listView').style.display = view === 'list' ? 'block' : 'none';
}

/*
|--------------------------------------------------------------------------
| SORT
|--------------------------------------------------------------------------
*/
function toggleSort() {
    const containers = [
        document.getElementById('gridView'),
        document.getElementById('tableBody')
    ];

    containers.forEach(container => {
        const items = Array.from(container.children);

        items.sort((a, b) => {
            const at = parseInt(a.dataset.mtime);
            const bt = parseInt(b.dataset.mtime);
            return newestFirst ? at - bt : bt - at;
        });

        items.forEach(item => {
            container.appendChild(item);
        });
    });

    newestFirst = !newestFirst;
    document.getElementById('sortBtn').textContent = newestFirst ? 'Sort: Newest → Oldest' : 'Sort: Oldest → Newest';
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/
function filterClips() {
    const query = document.getElementById('searchBox').value.toLowerCase();
    const items = document.querySelectorAll('.searchable');
    let visible = 0;

    items.forEach(item => {
        const text = 
            item.dataset.name + ' ' +
            item.dataset.stream + ' ' +
            item.dataset.category + ' ' +
            item.dataset.title + ' ' +
            item.dataset.description;

        const match = text.includes(query);
        item.style.display = match ? '' : 'none';

        if (match) {
            visible++;
        }
    });

    document.getElementById('clipCount').textContent = visible;
}

setView('grid');
</script>

</body>
</html>

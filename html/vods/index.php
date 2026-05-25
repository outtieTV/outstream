<?php
$dir = "/var/www/vod";

$allowed = ['mp4', 'flv', 'mkv', 'webm', 'avi', 'mov'];
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

    // UPDATED: Look for nested flat structure: category_streamkey_archive
    if (preg_match('/^([a-zA-Z0-9_\-]+)_([a-zA-Z0-9_\-]+)_archive/i', $baseName, $matches)) {
        $category = strtolower($matches[1]);
        $streamKey = strtolower($matches[2]);
    } 
    // Fallback logic for older single flat format files (streamkey_archive)
    elseif (preg_match('/^([a-zA-Z0-9_\-]+)_archive/i', $file, $matches)) {
        $streamKey = strtolower($matches[1]);
    }

    $jsonPath = $dir . '/' . $baseName . '.json';
    $metadata = [];

    if (file_exists($jsonPath)) {
        $decoded = json_decode(file_get_contents($jsonPath), true);

        if (is_array($decoded)) {
            $metadata = $decoded;
        }
    }

    $data[] = [
        "name" => $file,
        "baseName" => $baseName,
        "category" => $category,
        "streamKey" => $streamKey,
        "mtime" => filemtime($path),
        "size" => filesize($path),
        "ext" => $ext,
        "playable" => in_array($ext, $nativePlayable),
        "title" => $metadata["title"] ?? $file,
        "description" => $metadata["description"] ?? ""
    ];
}

foreach ($data as &$item) {
    $item["transcoding"] = count($basenameMap[$item["baseName"]]) > 1;
}
unset($item);

usort($data, function($a, $b) {
    return $b["mtime"] <=> $a["mtime"];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>VOD Library</title>

<link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />

<style>
:root {
    --bg: #0b0f14;
    --card: #161b22;
    --border: #2d333b;
    --hover: #21262d;
    --text: #e6edf3;
    --accent: #58a6ff;
}

body {
    margin: 0;
    padding: 24px;
    background: var(--bg);
    color: var(--text);
    font-family: Arial, sans-serif;
}

h1 {
    margin-bottom: 10px;
}

.topbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

input, button {
    background: #161b22;
    color: white;
    border: 1px solid #30363d;
    border-radius: 10px;
    padding: 12px;
}

input {
    flex: 1;
    min-width: 260px;
}

button {
    cursor: pointer;
}

button:hover {
    background: #21262d;
}

.stats {
    margin-bottom: 16px;
    opacity: 0.8;
}

.view-buttons {
    display: flex;
    gap: 8px;
}

.grid-view {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 20px;
}

.card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    cursor: pointer;
    transition: 0.2s ease;
}

.card:hover {
    transform: translateY(-3px);
    border-color: var(--accent);
}

.preview {
    width: 100%;
    height: 200px;
    background: black;
}

.preview video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.info {
    padding: 16px;
}

.stream {
    color: #8ecbff;
    font-weight: bold;
    margin-bottom: 6px;
}

.stream .cat-prefix {
    color: #a7f3d0;
}

.title {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 10px;
}

.description {
    color: #b9c0c8;
    font-size: 14px;
    line-height: 1.4;
    margin-bottom: 14px;
    white-space: pre-wrap;
}

.meta {
    font-size: 13px;
    opacity: 0.8;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}

.actions {
    display: flex;
    gap: 10px;
}

.btn {
    flex: 1;
    text-align: center;
    text-decoration: none;
    background: #21262d;
    border: 1px solid #30363d;
    color: white;
    padding: 10px;
    border-radius: 10px;
}

.btn:hover {
    background: #30363d;
}

.warning {
    margin-top: 10px;
    background: rgba(255, 153, 0, 0.15);
    border: 1px solid rgba(255,153,0,0.35);
    color: #ffcc66;
    padding: 10px;
    border-radius: 10px;
    font-size: 13px;
}

.list-view {
    display: none;
}

.list-view table {
    width: 100%;
    border-collapse: collapse;
}

.list-view th, .list-view td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
}

.list-view tr {
    cursor: pointer;
}

.list-view tr:hover {
    background: var(--hover);
}

.vjs-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}

.vjs-modal-content {
    width: 90%;
    max-width: 1000px;
    background: black;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}

.vjs-modal-close {
    position: absolute;
    top: 10px; right: 15px;
    z-index: 10000;
    font-size: 28px;
    color: white;
    background: rgba(0,0,0,0.5);
    border: none;
    cursor: pointer;
}

@media (max-width: 700px) {
    .grid-view {
        grid-template-columns: 1fr;
    }
    body {
        padding: 14px;
    }
}
</style>
</head>
<body>

<h1>📹 VOD Library</h1>

<div class="topbar">
    <input
        type="text"
        id="searchBox"
        placeholder="Search VODs..."
        onkeyup="filterItems()"
    >

    <button id="sortBtn" onclick="toggleSort()">
        Sort: Newest → Oldest
    </button>

    <div class="view-buttons">
        <button onclick="setView('grid')">
            Grid View
        </button>

        <button onclick="setView('list')">
            List View
        </button>
    </div>
</div>

<div class="stats">
    Total Videos: <span id="videoCount"><?php echo count($data); ?></span>
</div>

<div id="gridView" class="grid-view">
<?php foreach ($data as $vod): ?>
<?php $vodUrl = "/vod/" . rawurlencode($vod["name"]); ?>

<div
    class="card searchable"
    data-name="<?php echo strtolower($vod["name"]); ?>"
    data-title="<?php echo strtolower($vod["title"]); ?>"
    data-stream="<?php echo strtolower($vod["streamKey"]); ?>"
    data-category="<?php echo strtolower($vod["category"]); ?>"
    data-description="<?php echo strtolower($vod["description"]); ?>"
    data-mtime="<?php echo $vod["mtime"]; ?>"

    <?php if ($vod["playable"]): ?>
    ondblclick="openPlayer('<?php echo $vodUrl; ?>', 'video/<?php echo $vod["ext"]; ?>')"
    title="Double click to play"
    <?php endif; ?>
>
    <div class="preview">
        <?php if ($vod["playable"]): ?>
        <video muted preload="metadata">
            <source
                src="<?php echo $vodUrl; ?>#t=2"
                type="video/<?php echo $vod["ext"]; ?>"
            >
        </video>
        <?php endif; ?>
    </div>

    <div class="info">
        <div class="stream">
            <?php if (!empty($vod["category"])): ?>
                <span class="cat-prefix"><?php echo htmlspecialchars($vod["category"]); ?></span> / 
            <?php endif; ?>
            <?php echo htmlspecialchars($vod["streamKey"]); ?>
        </div>

        <div class="title">
            <?php echo htmlspecialchars($vod["title"]); ?>
        </div>

        <?php if (!empty($vod["description"])): ?>
        <div class="description">
            <?php echo nl2br(htmlspecialchars($vod["description"])); ?>
        </div>
        <?php endif; ?>

        <div class="meta">
            <span>
                <?php echo strtoupper($vod["ext"]); ?>
            </span>

            <span>
                <?php echo round($vod["size"] / 1024 / 1024, 2); ?> MB
            </span>

            <span>
                <?php echo date("Y-m-d H:i:s", $vod["mtime"]); ?>
            </span>
        </div>

        <div class="actions">
            <?php if ($vod["playable"]): ?>
            <button
                class="btn"
                onclick="event.stopPropagation(); openPlayer('<?php echo $vodUrl; ?>', 'video/<?php echo $vod["ext"]; ?>')"
            >
                Play
            </button>
            <?php endif; ?>

            <a
                class="btn"
                href="/vods/edit_metadata.php?file=<?php echo rawurlencode($vod["name"]); ?>"
                onclick="event.stopPropagation();"
            >
                Edit Metadata
            </a>
        </div>

        <?php if ($vod["transcoding"]): ?>
        <div class="warning">
            ⚠️ Multiple filetypes detected for this VOD.<br>
            Transcoding may still be in progress.
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<div id="listView" class="list-view">
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
<?php foreach ($data as $vod): ?>
<?php $vodUrl = "/vod/" . rawurlencode($vod["name"]); ?>

<tr
    class="searchable"
    data-name="<?php echo strtolower($vod["name"]); ?>"
    data-title="<?php echo strtolower($vod["title"]); ?>"
    data-stream="<?php echo strtolower($vod["streamKey"]); ?>"
    data-category="<?php echo strtolower($vod["category"]); ?>"
    data-description="<?php echo strtolower($vod["description"]); ?>"
    data-mtime="<?php echo $vod["mtime"]; ?>"

    <?php if ($vod["playable"]): ?>
    onclick="openPlayer('<?php echo $vodUrl; ?>', 'video/<?php echo $vod["ext"]; ?>')"
    <?php endif; ?>
>
    <td>
        <?php if (!empty($vod["category"])): ?>
            <span style="color:#a7f3d0;"><?php echo htmlspecialchars($vod["category"]); ?></span> / 
        <?php endif; ?>
        <?php echo htmlspecialchars($vod["streamKey"]); ?>
    </td>

    <td>
        <?php echo htmlspecialchars($vod["title"]); ?>

        <?php if ($vod["transcoding"]): ?>
            <br>
            <small style="color:#ffcc66;">
                ⚠️ Transcoding in progress
            </small>
        <?php endif; ?>
    </td>

    <td><?php echo strtoupper($vod["ext"]); ?></td>

    <td>
        <?php echo round($vod["size"] / 1024 / 1024, 2); ?> MB
    </td>

    <td>
        <?php echo date("Y-m-d H:i:s", $vod["mtime"]); ?>
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
        ></video>
    </div>
</div>

<script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>

<script>
let newestFirst = true;
let currentView = 'grid';
const player = videojs('my-video');

function openPlayer(src, type) {
    document.getElementById('playerModal').style.display = 'flex';

    player.src({
        src: src,
        type: type
    });

    player.ready(function() {
        player.play();
    });
}

function closePlayer() {
    player.pause();
    document.getElementById('playerModal').style.display = 'none';
}

document.getElementById('playerModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePlayer();
    }
});

function setView(view) {
    currentView = view;
    document.getElementById('gridView').style.display = view === 'grid' ? 'grid' : 'none';
    document.getElementById('listView').style.display = view === 'list' ? 'block' : 'none';
}

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

        items.forEach(item => container.appendChild(item));
    });

    newestFirst = !newestFirst;
    document.getElementById('sortBtn').textContent = newestFirst ? 'Sort: Newest → Oldest' : 'Sort: Oldest → Newest';
}

function filterItems() {
    const query = document.getElementById('searchBox').value.toLowerCase();
    const items = document.querySelectorAll('.searchable');
    let visible = 0;

    items.forEach(item => {
        const text =
            item.dataset.name + ' ' +
            item.dataset.title + ' ' +
            item.dataset.stream + ' ' +
            item.dataset.category + ' ' +
            item.dataset.description;

        const match = text.includes(query);
        item.style.display = match ? '' : 'none';

        if (match) visible++;
    });

    document.getElementById('videoCount').textContent = visible;
}

setView('grid');
</script>

</body>
</html>

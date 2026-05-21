<?php
$dir = "/var/www/vod";

$allowed = ['mp4', 'flv', 'mkv', 'webm', 'avi', 'mov'];
// Extensions modern browsers can natively play inside a web player
$nativePlayable = ['mp4', 'webm', 'mov'];

$files = array_values(array_filter(scandir($dir), function($f) use ($dir) {
    return $f !== '.' && $f !== '..' && is_file($dir . '/' . $f);
}));

$data = [];

foreach ($files as $file) {
    $path = $dir . '/' . $file;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        continue;
    }

    $streamKey = "unknown";
    if (preg_match('/^([a-zA-Z0-9_-]+)_archive/i', $file, $matches)) {
        $streamKey = strtolower($matches[1]);
    }

    $data[] = [
        "name" => $file,
        "streamKey" => $streamKey,
        "mtime" => filemtime($path),
        "size" => filesize($path),
        "ext" => $ext,
        "playable" => in_array($ext, $nativePlayable)
    ];
}

/* Newest first by default */
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
    --bg: #0f1115;
    --card: #171a21;
    --header: #1f2430;
    --border: #2a2f3a;
    --hover: #2a3140;
    --text: #e6e6e6;
    --accent: #4ea1ff;
}

body {
    font-family: Arial, sans-serif;
    background: var(--bg);
    color: var(--text);
    margin: 20px;
}

h1 {
    margin-bottom: 10px;
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

.topbar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

input, button, select {
    background: var(--header);
    color: white;
    border: 1px solid #333;
    padding: 10px;
    border-radius: 6px;
}

input {
    flex: 1;
    min-width: 250px;
}

button {
    cursor: pointer;
}

button:hover {
    background: #2a3140;
}

.stats {
    margin-bottom: 10px;
    opacity: 0.8;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card);
    overflow: hidden;
    border-radius: 10px;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    text-align: left;
}

th {
    background: var(--header);
}

tr {
    cursor: pointer;
}

tr:hover {
    background: var(--hover);
}

a {
    color: var(--accent);
    text-decoration: none;
    cursor: pointer;
}

a:hover {
    text-decoration: underline;
}

.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: bold;
}

.badge.mp4 { background: #2d7d46; }
.badge.flv { background: #7d5f2d; }
.badge.mkv { background: #6b2d7d; }
.badge.webm { background: #2d607d; }
.badge.avi { background: #7d2d52; }
.badge.mov { background: #7d2d2d; }

.streamkey {
    font-weight: bold;
    color: #8ecbff;
}

/* Thumbnail wrapper styling for table data list layout */
.file-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.table-thumbnail {
    width: 80px;
    height: 45px;
    object-fit: cover;
    background: #000;
    border-radius: 4px;
    border: 1px solid var(--border);
}

/* Modal Styling */
.vjs-modal {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
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
    box-shadow: 0 5px 25px rgba(0,0,0,0.5);
}
.vjs-modal-close {
    position: absolute;
    top: 10px; right: 15px;
    font-size: 28px; color: #fff;
    cursor: pointer; z-index: 10001;
    background: rgba(0,0,0,0.5);
    border: none; padding: 0 8px; border-radius: 4px;
}

/* Enhanced Responsive Mobile UI */
@media (max-width: 768px) {
    body { margin: 12px; }
    
    table, thead, tbody, tr, td, th {
        display: block;
    }

    thead {
        display: none;
    }

    tr {
        margin-bottom: 15px;
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        background: var(--card);
        padding: 6px 0;
    }

    td {
        border: none;
        padding: 8px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        text-align: right;
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    }

    td:last-child {
        border-bottom: none;
    }

    /* Insert labels dynamically on mobile */
    td::before {
        content: attr(data-label);
        font-weight: bold;
        color: var(--muted);
        text-align: left;
        font-size: 13px;
        opacity: 0.7;
        padding-right: 10px;
    }
    
    .file-cell {
        justify-content: flex-end;
        width: 100%;
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
    ← Back to Stream Player
</a>

<h1>📼 VOD Library</h1>

<div class="topbar">
    <input
        type="text"
        id="searchBox"
        placeholder="Search stream key or filename..."
        onkeyup="filterTable()"
    >

    <button id="sortBtn" onclick="toggleSort()">
        Sort: Newest → Oldest
    </button>
</div>

<div class="stats">
    Total Videos:
    <span id="videoCount"><?php echo count($data); ?></span>
</div>

<table>
    <thead>
        <tr>
            <th>Stream</th>
            <th>File</th>
            <th>Type</th>
            <th>Size</th>
            <th>Modified</th>
        </tr>
    </thead>

    <tbody id="tableBody">
        <?php foreach ($data as $f): ?>
        <?php $vodUrl = "/vod/" . rawurlencode($f["name"]); ?>

        <tr
            data-name="<?php echo strtolower($f["name"]); ?>"
            data-stream="<?php echo strtolower($f["streamKey"]); ?>"
            data-mtime="<?php echo $f["mtime"]; ?>"
            <?php if ($f["playable"]): ?>
            ondblclick="openPlayer('<?php echo $vodUrl; ?>', 'video/<?php echo $f['ext']; ?>')"
            title="Click to play"
            <?php endif; ?>
        >

            <td data-label="Stream">
                <span class="streamkey">
                    <?php echo htmlspecialchars($f["streamKey"]); ?>
                </span>
            </td>

            <td data-label="File">
                <div class="file-cell">
                    <?php if ($f["playable"]): ?>
                        <video class="table-thumbnail" preload="metadata">
                            <source src="<?php echo $vodUrl; ?>#t=2.0" type="video/<?php echo $f["ext"]; ?>">
                        </video>
                        <a onclick="openPlayer('<?php echo $vodUrl; ?>', 'video/<?php echo $f['ext']; ?>')">
                            <?php echo htmlspecialchars($f["name"]); ?> 🎬
                        </a>
                    <?php else: ?>
                        <div class="table-thumbnail" style="display:flex; align-items:center; justify-content:center; font-size:20px; background:#222;">💾</div>
                        <a href="<?php echo $vodUrl; ?>" download>
                            <?php echo htmlspecialchars($f["name"]); ?> 💾
                        </a>
                    <?php endif; ?>
                </div>
            </td>

            <td data-label="Type">
                <span class="badge <?php echo $f["ext"]; ?>">
                    <?php echo strtoupper($f["ext"]); ?>
                </span>
            </td>

            <td data-label="Size">
                <?php echo round($f["size"] / 1024 / 1024, 2); ?> MB
            </td>

            <td data-label="Modified">
                <?php echo date("Y-m-d H:i:s", $f["mtime"]); ?>
            </td>

        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div id="playerModal" class="vjs-modal">
    <div class="vjs-modal-content">
        <button class="vjs-modal-close" onclick="closePlayer()">×</button>
        <video id="my-video" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto" width="960" height="540">
            <p class="vjs-no-js">To view this video please enable JavaScript</p>
        </video>
    </div>
</div>

<script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>

<script>
let newestFirst = true;
let player = videojs('my-video');

function openPlayer(src, type) {
    document.getElementById('playerModal').style.display = 'flex';
    player.src({ type: type, src: src });
    player.ready(function() {
        player.play();
    });
}

function closePlayer() {
    player.pause();
    document.getElementById('playerModal').style.display = 'none';
}

// Close modal if overlay is clicked
document.getElementById('playerModal').addEventListener('click', function(e) {
    if (e.target === this) closePlayer();
});

function updateButton() {
    document.getElementById("sortBtn").textContent =
        newestFirst
            ? "Sort: Newest → Oldest"
            : "Sort: Oldest → Newest";
}

function toggleSort() {
    const tbody = document.getElementById("tableBody");
    const rows = Array.from(tbody.querySelectorAll("tr"));

    rows.sort((a, b) => {
        const at = parseInt(a.dataset.mtime);
        const bt = parseInt(b.dataset.mtime);
        return newestFirst ? at - bt : bt - at;
    });

    tbody.innerHTML = "";
    rows.forEach(r => tbody.appendChild(r));
    newestFirst = !newestFirst;
    updateButton();
}

function filterTable() {
    const query = document.getElementById("searchBox").value.toLowerCase();
    const rows = document.querySelectorAll("#tableBody tr");
    let visible = 0;

    rows.forEach(row => {
        const name = row.dataset.name;
        const stream = row.dataset.stream;
        const match = name.includes(query) || stream.includes(query);

        row.style.display = match ? "" : "none";
        if (match) visible++;
    });

    document.getElementById("videoCount").textContent = visible;
}

updateButton();
</script>

</body>
</html>

<?php
$dir = "/var/www/clip";

$allowed = ['mp4', 'flv', 'webm', 'mov'];
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
    if (preg_match('/^([a-zA-Z0-9_-]+)_/i', $file, $matches)) {
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

/* Newest first */
usort($data, function($a, $b) {
    return $b["mtime"] <=> $a["mtime"];
});
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

input, button, select {
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
    top: 12px; left: 12px;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(10px);
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
    font-size: 14px;
    margin-bottom: 14px;
    word-break: break-word;
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

/* Modal Window for Player */
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
}
.vjs-modal-close {
    position: absolute;
    top: 10px; right: 15px;
    font-size: 28px; color: #fff;
    cursor: pointer; z-index: 10001;
    background: rgba(0,0,0,0.5);
    border: none; padding: 0 8px; border-radius: 4px;
}

@media (max-width: 700px) {
    body { padding: 16px; }
    .grid { grid-template-columns: 1fr; }
    .preview { height: 200px; }
    .video-js { width: 100% !important; height: auto !important; aspect-ratio: 16/9; }
}
</style>
</head>

<body>

<a href="/" class="home-link">
    ← Back to Stream Player
</a>

<h1>🎬 Clip Library</h1>

<div class="subtitle">
    Browse, preview, and share saved clips
</div>

<div class="topbar">
    <input
        type="text"
        id="searchBox"
        placeholder="Search stream key or filename..."
        onkeyup="filterClips()"
    >
    <button id="sortBtn" onclick="toggleSort()">
        Sort: Newest → Oldest
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
    data-mtime="<?php echo $clip["mtime"]; ?>"
    <?php if ($clip["playable"]): ?>
    ondblclick="openPlayer('<?php echo $clipUrl; ?>', 'video/<?php echo $clip['ext']; ?>')"
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
            <source src="<?php echo $clipUrl; ?>#t=0.5" type="video/<?php echo $clip["ext"]; ?>">
        </video>
        <div class="badge">
            <?php echo strtoupper($clip["ext"]); ?>
        </div>
    </div>

    <div class="info">
        <div class="stream">
            <?php echo htmlspecialchars($clip["streamKey"]); ?>
        </div>

        <div class="filename">
            <?php echo htmlspecialchars($clip["name"]); ?>
        </div>

        <div class="meta">
            <span><?php echo round($clip["size"] / 1024 / 1024, 2); ?> MB</span>
            <span><?php echo date("Y-m-d H:i:s", $clip["mtime"]); ?></span>
        </div>

        <div class="actions">
            <?php if ($clip["playable"]): ?>
                <button class="btn" onclick="openPlayer('<?php echo $clipUrl; ?>', 'video/<?php echo $clip['ext']; ?>')">
                    Open Player
                </button>
            <?php else: ?>
                <a class="btn" href="<?php echo $clipUrl; ?>" download>
                    Download
                </a>
            <?php endif; ?>

            <button class="btn" onclick="copyClipURL('<?php echo $clipUrl; ?>')">
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

document.getElementById('playerModal').addEventListener('click', function(e) {
    if (e.target === this) closePlayer();
});

function toggleSort() {
    const grid = document.getElementById('clipGrid');
    const cards = Array.from(grid.querySelectorAll('.card'));

    cards.sort((a, b) => {
        const at = parseInt(a.dataset.mtime);
        const bt = parseInt(b.dataset.mtime);
        return newestFirst ? at - bt : bt - at;
    });

    grid.innerHTML = '';
    cards.forEach(card => grid.appendChild(card));
    newestFirst = !newestFirst;

    document.getElementById('sortBtn').textContent =
        newestFirst ? 'Sort: Newest → Oldest' : 'Sort: Oldest → Newest';
}

function filterClips() {
    const query = document.getElementById('searchBox').value.toLowerCase();
    const cards = document.querySelectorAll('.card');
    let visible = 0;

    cards.forEach(card => {
        const name = card.dataset.name;
        const stream = card.dataset.stream;
        const match = name.includes(query) || stream.includes(query);

        card.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    document.getElementById('clipCount').textContent = visible;
}

async function copyClipURL(path) {
    const url = window.location.origin + path;
    try {
        await navigator.clipboard.writeText(url);
        alert('Copied URL:\n' + url);
    } catch(err) {
        console.error(err);
        alert('Failed to copy URL.');
    }
}
</script>

</body>
</html>

<?php
require_once __DIR__ . '/config.php';
$config = load_config();

$streamKey = $config['streamKey'];

if (isset($_GET['streamkey']) && is_string($_GET['streamkey'])) {
    $streamKey = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['streamkey']);
}

$baseUrl = 'http://10.0.0.65:9090/hls/';
$finalSource =
    $baseUrl .
    rawurlencode($streamKey) .
    '/index.m3u8';

$hlsPath = '/var/www/hls';
$directories = [];

if (is_dir($hlsPath)) {
    $dirs = glob($hlsPath . '/*', GLOB_ONLYDIR);

    if ($dirs !== false) {
        foreach ($dirs as $dir) {
            $directories[] = basename($dir);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

<title>Live Stream - <?= htmlspecialchars($streamKey) ?></title>

<link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet">
<script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --panel-bg: rgba(15,15,15,0.82);
    --panel-border: rgba(255,255,255,0.08);
    --hover-bg: rgba(255,255,255,0.12);
    --blue: #2563eb;
    --blue-hover: #1d4ed8;
    --red: #ef4444;
    --green: #10b981;
    --yellow: #f59e0b;
}

html,
body {
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: #050505;
    font-family: "Segoe UI", sans-serif;
    color: white;
}

body {
    position: relative;
}

body::before {
    content: "";
    position: fixed;
    inset: 0;
    background:
        radial-gradient(circle at top, #1f2937 0%, #050505 70%);
    filter: blur(50px);
    opacity: 0.45;
    z-index: 0;
}

.video-container {
    position: absolute;
    inset: 0;
    z-index: 1;
    background: #000;
}

.video-js {
    width: 100% !important;
    height: 100% !important;
    background: #000;
}

.video-js .vjs-progress-control {
    display: none !important;
}

/* CONTROL PANEL */

.control-panel {
    position: fixed;
    top: 16px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 100;

    width: min(96vw, 1050px);

    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;

    padding: 14px;

    border-radius: 24px;

    background: var(--panel-bg);
    backdrop-filter: blur(16px);

    border: 1px solid var(--panel-border);

    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}

.control-panel.hidden {
    opacity: 0;
    transform: translateX(-50%) translateY(-20px);
    pointer-events: none;
}

.status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 6px;
}

.live-dot {
    width: 10px;
    height: 10px;
    background: var(--red);
    border-radius: 50%;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }

    50% {
        transform: scale(1.4);
        opacity: 0.6;
    }

    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.status-text {
    font-size: 14px;
    font-weight: 700;
}

.stream-input {
    flex: 1;
    min-width: 180px;

    padding: 12px 16px;

    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.12);

    background: rgba(255,255,255,0.06);

    color: white;
    outline: none;

    transition: 0.2s ease;
}

.stream-input:focus {
    border-color: #3b82f6;
    background: rgba(255,255,255,0.1);
    box-shadow: 0 0 15px rgba(59,130,246,0.35);
}

.btn {
    border: none;
    border-radius: 999px;

    padding: 11px 16px;

    font-size: 14px;
    font-weight: 700;

    cursor: pointer;

    color: white;

    transition:
        background 0.2s ease,
        transform 0.15s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

.btn-primary {
    background: var(--blue);
}

.btn-primary:hover {
    background: var(--blue-hover);
}

.btn-danger {
    background: rgba(239,68,68,0.2);
    border: 1px solid rgba(239,68,68,0.4);
    color: #f87171;
}

.btn-danger:hover {
    background: rgba(239,68,68,0.35);
}

.btn-secondary {
    background: rgba(255,255,255,0.08);
}

.btn-secondary:hover {
    background: rgba(255,255,255,0.15);
}

.btn-sm {
    padding: 5px 10px;
    border-radius: 8px;
    font-size: 12px;
}

/* SIDEBAR */

.sidebar-toggle {
    position: fixed;
    top: 18px;
    left: 18px;

    width: 48px;
    height: 48px;

    z-index: 110;

    border-radius: 50%;
    border: 1px solid var(--panel-border);

    background: var(--panel-bg);
    backdrop-filter: blur(14px);

    color: white;

    cursor: pointer;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 22px;
}

.sidebar-toggle:hover {
    background: rgba(255,255,255,0.12);
}

.directory-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;

    width: min(300px, 82vw);

    z-index: 109;

    background: rgba(10,10,10,0.9);
    backdrop-filter: blur(20px);

    border-right: 1px solid var(--panel-border);

    padding: 85px 18px 18px;

    overflow-y: auto;

    transform: translateX(-100%);
    transition: transform 0.25s ease;
}

.directory-sidebar.open {
    transform: translateX(0);
}

.panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    margin-bottom: 16px;
}

.panel-header h3 {
    font-size: 16px;
}

.directory-item,
.recent-item {
    padding: 12px;
    margin-bottom: 10px;

    border-radius: 14px;

    background: rgba(255,255,255,0.05);

    cursor: pointer;

    transition: background 0.2s ease;

    word-break: break-word;
}

.directory-item:hover,
.recent-item:hover {
    background: var(--hover-bg);
}

.empty-msg {
    color: #9ca3af;
    font-size: 13px;
    text-align: center;
    padding: 10px 0;
}

/* RECENT STREAMS */

.recent-streams {
    position: fixed;
    right: 16px;
    top: 92px;

    width: 280px;
    max-height: calc(100vh - 120px);

    overflow-y: auto;

    z-index: 90;

    padding: 16px;

    border-radius: 22px;

    background: var(--panel-bg);
    backdrop-filter: blur(18px);

    border: 1px solid var(--panel-border);
}

/* OVERLAY */

.center-overlay {
    position: absolute;
    inset: 0;

    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;

    gap: 16px;

    z-index: 50;

    background: rgba(0,0,0,0.55);
    backdrop-filter: blur(8px);

    opacity: 0;
    pointer-events: none;

    transition: 0.25s ease;
}

.center-overlay.visible {
    opacity: 1;
    pointer-events: auto;
}

.spinner {
    width: 54px;
    height: 54px;

    border-radius: 50%;

    border: 4px solid rgba(255,255,255,0.15);
    border-top-color: #3b82f6;

    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.overlay-text {
    font-size: 18px;
    font-weight: 700;
}

/* STATS */

.stats-bar {
    position: fixed;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%);

    z-index: 100;

    width: min(95vw, 760px);

    display: flex;
    justify-content: center;
    gap: 18px;
    flex-wrap: wrap;

    padding: 12px 18px;

    border-radius: 24px;

    background: var(--panel-bg);
    backdrop-filter: blur(14px);

    border: 1px solid var(--panel-border);

    color: #d1d5db;
    font-size: 13px;
}

.stats-item {
    white-space: nowrap;
}

/* TABLETS */

@media (max-width: 1100px) {

    .recent-streams {
        width: 240px;
    }

    .control-panel {
        top: 14px;
        width: calc(100vw - 24px);
    }
}

/* MOBILE */

@media (max-width: 768px) {

    html,
    body {
        overflow: hidden;
    }

    .control-panel {
        top: auto;
        bottom: 82px;

        left: 50%;
        transform: translateX(-50%);

        width: calc(100vw - 16px);

        padding: 12px;
        gap: 8px;

        border-radius: 22px;
    }

    .control-panel.hidden {
        opacity: 0;
        transform: translateX(-50%) translateY(25px);
    }

    .stream-input {
        width: 100%;
        min-width: unset;
    }

    .btn {
        flex: 1 1 calc(50% - 8px);
        min-width: 120px;
    }

    .sidebar-toggle {
        top: 14px;
        left: 14px;
    }

    .recent-streams {
        display: none;
    }

    .stats-bar {
        bottom: 12px;
        width: calc(100vw - 16px);

        gap: 10px;

        padding: 10px 14px;

        font-size: 12px;
    }

    .stats-item {
        flex: 1 1 calc(50% - 10px);
        text-align: center;
    }
}

/* VERY SMALL MOBILE */

@media (max-width: 480px) {

    .btn {
        min-width: unset;
        font-size: 13px;
        padding: 10px 12px;
    }

    .status {
        width: 100%;
        justify-content: center;
    }

    .overlay-text {
        font-size: 16px;
        text-align: center;
        padding: 0 20px;
    }

    .stats-item {
        flex: 1 1 100%;
    }
}
</style>
</head>

<body>

<button
    class="sidebar-toggle"
    onclick="toggleSidebar()"
    title="Toggle Stream Directory"
>
☰
</button>

<div class="directory-sidebar" id="directorySidebar">

    <div class="panel-header">
        <h3>Stream Directory</h3>
    </div>

    <div class="directory-list">

        <?php if (empty($directories)): ?>

            <div class="empty-msg">
                No stream directories found
            </div>

        <?php else: ?>

            <?php foreach ($directories as $dir): ?>

                <div
                    class="directory-item"
                    onclick="loadDirectoryStream('<?= htmlspecialchars(addslashes($dir)) ?>')"
                >
                    📁 <?= htmlspecialchars($dir) ?>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

<div class="control-panel" id="controls">

    <div class="status">
        <div class="live-dot"></div>
        <div class="status-text" id="statusText">
            CONNECTING
        </div>
    </div>

    <input
        type="text"
        id="streamKeyInput"
        class="stream-input"
        placeholder="Enter stream key..."
        value="<?= htmlspecialchars($streamKey) ?>"
    >

    <button class="btn btn-primary" onclick="changeStream()">
        Load
    </button>

    <button
        class="btn btn-primary"
        style="background:#ef4444;"
        onclick="takeClip()"
    >
        Clip
    </button>

    <button
        class="btn btn-secondary"
        id="muteBtn"
        onclick="toggleMute()"
    >
        Unmute
    </button>

    <button
        class="btn btn-secondary"
        onclick="toggleFullscreen()"
    >
        Fullscreen
    </button>

    <button
        class="btn btn-secondary"
        onclick="enablePIP()"
    >
        PiP
    </button>

</div>

<div class="video-container">

    <video
        id="video"
        class="video-js vjs-default-skin"
        autoplay
        muted
        controls
        playsinline
    ></video>

    <div class="center-overlay visible" id="overlay">

        <div class="spinner"></div>

        <div class="overlay-text" id="overlayText">
            Connecting to stream...
        </div>

    </div>

</div>

<div class="stats-bar">

    <div class="stats-item">
        Stream:
        <span id="currentStream">
            <?= htmlspecialchars($streamKey) ?>
        </span>
    </div>

    <div class="stats-item">
        Resolution:
        <span id="resolution">--</span>
    </div>

    <div class="stats-item">
        Latency:
        <span id="latency">--</span>
    </div>

    <div class="stats-item">
        Volume:
        <span id="volume">Muted</span>
    </div>

</div>

<div class="recent-streams">

    <div class="panel-header">

        <h3>Recent Streams</h3>

        <button
            class="btn btn-sm btn-danger"
            onclick="clearRecent()"
        >
            Clear
        </button>

    </div>

    <div id="recentList"></div>

</div>

<script>
const overlay = document.getElementById('overlay');
const overlayText = document.getElementById('overlayText');
const statusText = document.getElementById('statusText');
const streamInput = document.getElementById('streamKeyInput');
const directorySidebar = document.getElementById('directorySidebar');

const muteBtn = document.getElementById('muteBtn');
const volumeText = document.getElementById('volume');

let controlsTimeout = null;

const player = videojs('video', {
    fluid: true,

    html5: {
        vhs: {
            overrideNative: true
        },
        nativeAudioTracks: false,
        nativeVideoTracks: false
    }
});

function toggleSidebar() {
    directorySidebar.classList.toggle('open');
}

function loadDirectoryStream(streamKey) {
    streamInput.value = streamKey;
    loadStream(streamKey);

    if (window.innerWidth <= 768) {
        directorySidebar.classList.remove('open');
    }
}

function showOverlay(text) {
    overlay.classList.add('visible');
    overlayText.textContent = text;
}

function hideOverlay() {
    overlay.classList.remove('visible');
}

function setStatus(text, color = '#10b981') {
    statusText.textContent = text;
    statusText.style.color = color;
}

function updateMuteButton() {

    const muted = player.muted();

    muteBtn.textContent = muted
        ? 'Unmute'
        : 'Mute';

    volumeText.textContent = muted
        ? 'Muted'
        : Math.round(player.volume() * 100) + '%';
}

player.on('loadedmetadata', () => {

    setStatus('LIVE', '#10b981');

    hideOverlay();

    const quality = player.videoHeight();

    if (quality) {
        document.getElementById('resolution').textContent =
            quality + 'p';
    }

    updateMuteButton();
});

player.on('volumechange', updateMuteButton);

player.on('error', () => {

    const error = player.error();

    console.error('Video.js Error:', error);

    setStatus('OFFLINE', '#ef4444');

    showOverlay('Stream offline or unavailable');
});

function loadStream(streamKey) {

    if (!streamKey) {
        return;
    }

    const source =
        '<?= $baseUrl ?>' +
        encodeURIComponent(streamKey) +
        '/index.m3u8';

    document.getElementById('currentStream').textContent =
        streamKey;

    setStatus('CONNECTING', '#f59e0b');

    showOverlay('Connecting to stream...');

    player.src({
        src: source,
        type: 'application/x-mpegURL'
    });

    player.play().catch(err => {
        console.log('Autoplay deferred:', err);
    });

    history.replaceState(
        null,
        '',
        '?streamkey=' + encodeURIComponent(streamKey)
    );

    saveRecent(streamKey);
    renderRecent();
}

function changeStream() {

    const key = streamInput.value.trim();

    if (key) {
        loadStream(key);
    }
}

function takeClip() {

    const streamKey =
        document.getElementById('currentStream').textContent;

    if (!streamKey || streamKey === 'test') {

        alert('Cannot clip an empty or default test stream.');

        return;
    }

    setStatus('CLIPPING...', '#ef4444');

    const formData = new FormData();

    formData.append('streamkey', streamKey);

    fetch('clip.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {

        if (data.success) {

            alert(
                'Success!\n\n' +
                data.message +
                '\nSaved as: ' +
                data.file
            );

        } else {

            alert('Clip failed: ' + data.error);
        }

        setStatus('LIVE', '#10b981');
    })
    .catch(error => {

        console.error('Error creating clip:', error);

        alert('An error occurred while creating the clip.');

        setStatus('LIVE', '#10b981');
    });
}

function saveRecent(streamKey) {

    let recent =
        JSON.parse(localStorage.getItem('recentStreams') || '[]');

    recent = recent.filter(v => v !== streamKey);

    recent.unshift(streamKey);

    recent = recent.slice(0, 10);

    localStorage.setItem(
        'recentStreams',
        JSON.stringify(recent)
    );
}

function renderRecent() {

    const recent =
        JSON.parse(localStorage.getItem('recentStreams') || '[]');

    const container =
        document.getElementById('recentList');

    container.innerHTML = '';

    if (recent.length === 0) {

        container.innerHTML =
            '<div class="empty-msg">No recent history</div>';

        return;
    }

    recent.forEach(stream => {

        const div = document.createElement('div');

        div.className = 'recent-item';

        div.textContent = stream;

        div.onclick = () => {

            streamInput.value = stream;

            loadStream(stream);
        };

        container.appendChild(div);
    });
}

function clearRecent() {

    localStorage.removeItem('recentStreams');

    renderRecent();
}

function toggleMute() {

    player.muted(!player.muted());

    updateMuteButton();
}

function toggleFullscreen() {

    if (!player.isFullscreen()) {
        player.requestFullscreen();
    } else {
        player.exitFullscreen();
    }
}

async function enablePIP() {

    const videoEl = player.tech().el();

    try {

        if (document.pictureInPictureElement) {

            await document.exitPictureInPicture();

        } else {

            await videoEl.requestPictureInPicture();
        }

    } catch(err) {

        console.log(err);
    }
}

document.addEventListener('keydown', e => {

    if (e.target.tagName === 'INPUT') {
        return;
    }

    switch(e.key.toLowerCase()) {

        case ' ':

            e.preventDefault();

            if (player.paused()) {
                player.play();
            } else {
                player.pause();
            }

            break;

        case 'm':
            toggleMute();
            break;

        case 'f':
            toggleFullscreen();
            break;

        case 'p':
            enablePIP();
            break;

        case 'c':
            takeClip();
            break;
    }
});

streamInput.addEventListener('keypress', e => {

    if (e.key === 'Enter') {
        changeStream();
    }
});

function showControls() {

    const controls =
        document.getElementById('controls');

    controls.classList.remove('hidden');

    clearTimeout(controlsTimeout);

    controlsTimeout = setTimeout(() => {

        controls.classList.add('hidden');

    }, window.innerWidth <= 768 ? 2200 : 3200);
}

document.addEventListener('mousemove', showControls);
document.addEventListener('touchstart', showControls);

showControls();

setInterval(() => {

    if (!player.paused()) {

        const liveTracker = player.liveTracker;

        if (liveTracker && liveTracker.isLive()) {

            const latency =
                liveTracker.liveWindow() -
                player.currentTime();

            document.getElementById('latency').textContent =
                Math.max(0, latency).toFixed(1) + 's';

        } else {

            document.getElementById('latency').textContent =
                '--';
        }
    }

}, 1000);

loadStream('<?= htmlspecialchars($streamKey) ?>');

renderRecent();

updateMuteButton();
</script>

</body>
</html>

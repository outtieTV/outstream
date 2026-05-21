<?php
$streamKey = 'test';

if (isset($_GET['streamkey']) && is_string($_GET['streamkey'])) {
    $streamKey = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['streamkey']);
}

$baseUrl = 'http://10.0.0.65:9090/hls/';
$finalSource =
    $baseUrl .
    rawurlencode($streamKey) .
    '/index.m3u8';

/* Scan /var/www/hls for valid stream directories */
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Stream - <?= htmlspecialchars($streamKey) ?></title>

    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
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
            background: radial-gradient(circle at center, #1f2937 0%, #050505 70%);
            filter: blur(50px);
            opacity: 0.4;
            z-index: 0;
        }

        .video-container {
            position: absolute;
            inset: 0;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
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

        .control-panel {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 100;

            display: flex;
            align-items: center;
            gap: 12px;

            padding: 12px 18px;
            border-radius: 999px;

            background: rgba(15, 15, 15, 0.72);
            backdrop-filter: blur(16px);

            border: 1px solid rgba(255,255,255,0.08);

            transition:
                opacity 0.3s ease,
                transform 0.3s ease;
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
            min-width: 140px;
        }

        .live-dot {
            width: 10px;
            height: 10px;
            background: #ef4444;
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
            font-weight: 600;
        }

        .stream-input {
            width: 240px;
            padding: 10px 16px;

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

            padding: 10px 18px;

            font-size: 14px;
            font-weight: 600;

            cursor: pointer;

            transition: 0.2s ease;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: rgba(255,255,255,0.08);
            color: white;
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.15);
        }

        .btn-sm {
            padding: 4px 10px;
            font-size: 12px;
            border-radius: 6px;
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.4);
        }

        .center-overlay {
            position: absolute;
            inset: 0;

            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;

            gap: 14px;

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
            width: 52px;
            height: 52px;

            border: 4px solid rgba(255,255,255,0.15);
            border-top-color: #3b82f6;

            border-radius: 50%;

            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .overlay-text {
            font-size: 18px;
            font-weight: 600;
            color: white;
        }

        .stats-bar {
            position: fixed;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);

            z-index: 100;

            display: flex;
            align-items: center;
            gap: 16px;

            padding: 10px 16px;

            background: rgba(15,15,15,0.72);
            backdrop-filter: blur(12px);

            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.08);

            font-size: 13px;
            color: #d1d5db;
        }

        .stats-item {
            white-space: nowrap;
        }

        .recent-streams {
            position: fixed;
            right: 20px;
            top: 90px;

            z-index: 90;

            width: 260px;

            background: rgba(15,15,15,0.75);
            backdrop-filter: blur(16px);

            border-radius: 20px;

            border: 1px solid rgba(255,255,255,0.08);

            padding: 16px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .panel-header h3 {
            font-size: 15px;
        }

        .recent-item,
        .directory-item {
            padding: 10px 12px;
            margin-bottom: 8px;

            border-radius: 12px;

            background: rgba(255,255,255,0.05);

            cursor: pointer;

            transition: 0.2s ease;
            word-break: break-all;
        }

        .recent-item:hover,
        .directory-item:hover {
            background: rgba(255,255,255,0.12);
        }

        .directory-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 280px;

            z-index: 95;

            background: rgba(15, 15, 15, 0.8);
            backdrop-filter: blur(20px);

            border-right: 1px solid rgba(255, 255, 255, 0.08);

            padding: 80px 20px 20px 20px;

            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);

            overflow-y: auto;
        }

        .directory-sidebar.open {
            transform: translateX(0);
        }

        .sidebar-toggle {
            position: fixed;
            left: 20px;
            top: 25px;

            z-index: 105;

            background: rgba(15, 15, 15, 0.72);
            backdrop-filter: blur(16px);

            border: 1px solid rgba(255, 255, 255, 0.08);

            color: white;

            width: 44px;
            height: 44px;

            border-radius: 50%;

            cursor: pointer;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 20px;

            transition: background 0.2s;
        }

        .sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .directory-list {
            margin-top: 15px;
        }

        .empty-msg {
            font-size: 13px;
            color: #9ca3af;
            font-style: italic;
            text-align: center;
            margin-top: 10px;
        }

        @media (max-width: 900px) {
            .control-panel {
                width: calc(100% - 20px);
                flex-wrap: wrap;
                justify-content: center;
                border-radius: 24px;
                left: 50%;
                top: 80px;
            }

            .sidebar-toggle {
                top: 15px;
                left: 15px;
            }

            .stream-input {
                width: 100%;
            }

            .recent-streams,
            .directory-sidebar {
                display: none;
            }

            .stats-bar {
                flex-wrap: wrap;
                width: calc(100% - 20px);
                justify-content: center;
                border-radius: 18px;
            }
        }
    </style>
</head>
<body>

<button class="sidebar-toggle" onclick="toggleSidebar()" title="Toggle Stream Directory">
    ☰
</button>

<div class="directory-sidebar" id="directorySidebar">

    <div class="panel-header">
        <h3>Stream Directory</h3>
    </div>

    <div class="directory-list">

        <?php if (empty($directories)): ?>

            <div class="empty-msg">
                No stream directories found in /var/www/hls
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
        <div class="status-text" id="statusText">CONNECTING</div>
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
        style="background: #ef4444;"
        onclick="takeClip()"
    >
        Clip
    </button>

    <button class="btn btn-secondary" onclick="toggleMute()">
        Mute
    </button>

    <button class="btn btn-secondary" onclick="toggleFullscreen()">
        Fullscreen
    </button>

    <button class="btn btn-secondary" onclick="enablePIP()">
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
        <span id="currentStream"><?= htmlspecialchars($streamKey) ?></span>
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

        <button class="btn btn-sm btn-danger" onclick="clearRecent()">
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

let controlsTimeout = null;

const player = videojs('video', {
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

player.on('loadedmetadata', () => {
    setStatus('LIVE', '#10b981');
    hideOverlay();

    const quality = player.videoHeight();

    if (quality) {
        document.getElementById('resolution').textContent =
            quality + 'p';
    }
});

player.on('error', () => {
    const error = player.error();

    console.error('Video.js Error:', error);

    setStatus('OFFLINE', '#ef4444');
    showOverlay('Stream offline or data missing');
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
                'Success! ' +
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

    const isMuted = player.muted();

    player.muted(!isMuted);

    document.getElementById('volume').textContent =
        player.muted()
            ? 'Muted'
            : Math.round(player.volume() * 100) + '%';
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
    }, 3000);
}

document.addEventListener('mousemove', showControls);

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
</script>

</body>
</html>

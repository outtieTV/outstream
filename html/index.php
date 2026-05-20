<?php
$streamKey = isset($_GET['streamkey']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['streamkey']) : 'test';

$baseUrl = 'http://10.0.0.65:9090/hls/';
$finalSource = $baseUrl . rawurlencode($streamKey) . '.m3u8';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Stream - <?= htmlspecialchars($streamKey) ?></title>

    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
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

        /* Ambient blurred background */
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

        video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
        }

        /* TOP CONTROL PANEL */
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
            0 { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.4); opacity: 0.6; }
            100% { transform: scale(1); opacity: 1; }
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

        /* Overlay */
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

        /* Bottom stats bar */
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

        /* Recent streams */
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

        .recent-streams h3 {
            margin-bottom: 12px;
            font-size: 15px;
        }

        .recent-item {
            padding: 10px 12px;
            margin-bottom: 8px;

            border-radius: 12px;

            background: rgba(255,255,255,0.05);

            cursor: pointer;

            transition: 0.2s ease;
        }

        .recent-item:hover {
            background: rgba(255,255,255,0.12);
        }

        /* Mobile */
        @media (max-width: 900px) {

            .control-panel {
                width: calc(100% - 20px);
                flex-wrap: wrap;
                justify-content: center;
                border-radius: 24px;
            }

            .stream-input {
                width: 100%;
            }

            .recent-streams {
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

    <button class="btn btn-primary" style="background: #ef4444;" onclick="takeClip()">
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
        <span id="volume">100%</span>
    </div>

</div>

<div class="recent-streams">
    <h3>Recent Streams</h3>
    <div id="recentList"></div>
</div>

<script>

const video = document.getElementById('video');
const overlay = document.getElementById('overlay');
const overlayText = document.getElementById('overlayText');
const statusText = document.getElementById('statusText');
const streamInput = document.getElementById('streamKeyInput');

let hls = null;
let controlsTimeout = null;

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

function destroyPlayer() {

    if (hls) {
        hls.destroy();
        hls = null;
    }

    video.pause();
    video.removeAttribute('src');
    video.load();
}

function loadStream(streamKey) {

    if (!streamKey) return;

    const source =
        '<?= $baseUrl ?>' +
        encodeURIComponent(streamKey) +
        '.m3u8';

    document.getElementById('currentStream').textContent = streamKey;

    setStatus('CONNECTING', '#f59e0b');
    showOverlay('Connecting to stream...');

    destroyPlayer();

    if (Hls.isSupported()) {

        hls = new Hls({
            lowLatencyMode: true,
            liveSyncDurationCount: 1,
            liveMaxLatencyDurationCount: 3,
            backBufferLength: 90
        });

        hls.loadSource(source);
        hls.attachMedia(video);

        hls.on(Hls.Events.MANIFEST_PARSED, () => {

            setStatus('LIVE', '#10b981');

            hideOverlay();

            video.play().catch(err => {
                console.log(err);
            });

        });

        hls.on(Hls.Events.LEVEL_SWITCHED, () => {

            const level = hls.levels[hls.currentLevel];

            if (level) {
                document.getElementById('resolution').textContent =
                    level.height + 'p';
            }

        });

        hls.on(Hls.Events.ERROR, (event, data) => {

            console.error(data);

            if (data.fatal) {

                switch(data.type) {

                    case Hls.ErrorTypes.NETWORK_ERROR:

                        setStatus('RECONNECTING', '#f59e0b');

                        showOverlay('Stream offline. Retrying...');

                        setTimeout(() => {
                            hls.startLoad();
                        }, 3000);

                        break;

                    case Hls.ErrorTypes.MEDIA_ERROR:

                        setStatus('RECOVERING', '#f59e0b');

                        hls.recoverMediaError();

                        break;

                    default:

                        setStatus('OFFLINE', '#ef4444');

                        showOverlay('Stream offline');

                        destroyPlayer();

                        break;
                }
            }
        });

    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {

        video.src = source;

        video.addEventListener('loadedmetadata', () => {

            hideOverlay();

            setStatus('LIVE', '#10b981');

            video.play();

        });

    } else {

        setStatus('UNSUPPORTED', '#ef4444');

        showOverlay('HLS unsupported in this browser');

    }

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
    const streamKey = document.getElementById('currentStream').textContent;
    
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
            alert('Success! ' + data.message + '\nSaved as: ' + data.file);
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

function toggleMute() {

    video.muted = !video.muted;

    document.getElementById('volume').textContent =
        video.muted
            ? 'Muted'
            : Math.round(video.volume * 100) + '%';
}

function toggleFullscreen() {

    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

async function enablePIP() {

    try {

        if (document.pictureInPictureElement) {

            await document.exitPictureInPicture();

        } else {

            await video.requestPictureInPicture();

        }

    } catch(err) {

        console.log(err);

    }
}

/* Keyboard shortcuts */

document.addEventListener('keydown', e => {

    if (e.target.tagName === 'INPUT') return;

    switch(e.key.toLowerCase()) {

        case ' ':
            e.preventDefault();

            if (video.paused) {
                video.play();
            } else {
                video.pause();
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
            // Shortcut key "C" to generate clip
            takeClip();
            break;
    }
});

/* Enter key support */

streamInput.addEventListener('keypress', e => {

    if (e.key === 'Enter') {
        changeStream();
    }

});

/* Auto-hide controls */

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

/* Update stats */

setInterval(() => {

    if (!video.paused) {

        const latency =
            Math.max(0, (video.duration - video.currentTime));

        document.getElementById('latency').textContent =
            latency.toFixed(1) + 's';
    }

}, 1000);

/* Initial stream load */

loadStream('<?= htmlspecialchars($streamKey) ?>');

renderRecent();

</script>

</body>
</html>

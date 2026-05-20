<?php
// Fallback to 'test' if no streamkey is provided in the URL
$streamKey = isset($_GET['streamkey']) ? $_GET['streamkey'] : 'test';
$baseUrl = 'http://10.0.0.65:9090/hls/';
$finalSource = $baseUrl . htmlspecialchars($streamKey) . '.m3u8';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stream Player - <?= htmlspecialchars($streamKey) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <style>
        /* Reset and Fullscreen Layout */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body, html {
            width: 100%;
            height: 100%;
            background-color: #050505;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden;
        }

        /* Fullscreen Video Background */
        .video-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        video {
            width: 100%;
            height: 100%;
            object-fit: contain; /* Keeps aspect ratio. Change to 'cover' if you want zero black bars */
            background: #000;
        }

        /* Floating Top Control Bar */
        .control-panel {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            background: rgba(20, 20, 20, 0.75);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            transition: opacity 0.3s ease;
        }

        /* Subtle fade out when hovering away from the top */
        .control-panel:hover {
            background: rgba(20, 20, 20, 0.9);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* Input Styling */
        .stream-input {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            color: #fff;
            font-size: 14px;
            outline: none;
            width: 200px;
            transition: all 0.2s ease;
        }
        .stream-input:focus {
            border-color: #3b82f6;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 8px rgba(59, 130, 246, 0.5);
        }

        /* Button Styling */
        .btn-load {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s ease;
        }
        .btn-load:hover {
            background: #2563eb;
        }

        /* Current Stream Badge */
        .status-badge {
            font-size: 12px;
            color: #a3a3a3;
            margin-right: 8px;
            white-space: nowrap;
        }
        .status-badge span {
            color: #10b981;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="control-panel">
        <div class="status-badge">Active: <span><?= htmlspecialchars($streamKey) ?></span></div>
        <input type="text" id="streamKeyInput" class="stream-input" placeholder="Enter stream key..." value="<?= htmlspecialchars($streamKey) ?>">
        <button onclick="changeStream()" class="btn-load">Load</button>
    </div>

    <div class="video-container">
        <video id="video" controls autoplay muted playsinline></video>
    </div>

    <script>
        const video = document.getElementById('video');
        // PHP injects the resolved stream URL directly into JS
        const videoSrc = '<?= $finalSource ?>';

        // Initialize HLS
        if (Hls.isSupported()) {
            const hls = new Hls();
            hls.loadSource(videoSrc);
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, function() {
                video.play().catch(e => console.log("Autoplay blocked/interrupted:", e));
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = videoSrc;
            video.addEventListener('loadedmetadata', function() {
                video.play().catch(e => console.log("Autoplay blocked/interrupted:", e));
            });
        }

        // Handle changing stream via textbox
        function changeStream() {
            const key = document.getElementById('streamKeyInput').value.trim();
            if (key) {
                // Redirects the page back to itself with the new key in query params
                window.location.href = window.location.pathname + '?streamkey=' + encodeURIComponent(key);
            }
        }

        // Allow pressing 'Enter' in input field to submit
        document.getElementById('streamKeyInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                changeStream();
            }
        });
    </script>
</body>
</html>

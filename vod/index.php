<?php
$dir = "/var/www/vod";

$allowed = ['mp4', 'flv', 'mkv', 'webm', 'avi', 'mov'];

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

    /*
        Example formats:
        test_archive_2026-20-05.flv
        wow_archive_1747791122.mp4
    */

    $streamKey = "unknown";

    if (preg_match('/^([a-zA-Z0-9_-]+)_archive/i', $file, $matches)) {
        $streamKey = strtolower($matches[1]);
    }

    $data[] = [
        "name" => $file,
        "streamKey" => $streamKey,
        "mtime" => filemtime($path),
        "size" => filesize($path),
        "ext" => $ext
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
<title>VOD Library</title>

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

tr:hover {
    background: var(--hover);
}

a {
    color: var(--accent);
    text-decoration: none;
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

@media (max-width: 900px) {

    table,
    thead,
    tbody,
    tr,
    td,
    th {
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
    }

    td {
        border: none;
        border-bottom: 1px solid var(--border);
    }
}
</style>
</head>

<body>

<h1>ðŸ“¼ VOD Library</h1>

<div class="topbar">
    <input
        type="text"
        id="searchBox"
        placeholder="Search stream key or filename..."
        onkeyup="filterTable()"
    >

    <button id="sortBtn" onclick="toggleSort()">
        Sort: Newest â†’ Oldest
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

        <tr
            data-name="<?php echo strtolower($f["name"]); ?>"
            data-stream="<?php echo strtolower($f["streamKey"]); ?>"
            data-mtime="<?php echo $f["mtime"]; ?>"
        >

            <td>
                <span class="streamkey">
                    <?php echo htmlspecialchars($f["streamKey"]); ?>
                </span>
            </td>

            <td>
                <a href="/vod/<?php echo urlencode($f["name"]); ?>" target="_blank">
                    <?php echo htmlspecialchars($f["name"]); ?>
                </a>
            </td>

            <td>
                <span class="badge <?php echo $f["ext"]; ?>">
                    <?php echo strtoupper($f["ext"]); ?>
                </span>
            </td>

            <td>
                <?php echo round($f["size"] / 1024 / 1024, 2); ?> MB
            </td>

            <td>
                <?php echo date("Y-m-d H:i:s", $f["mtime"]); ?>
            </td>

        </tr>

        <?php endforeach; ?>
    </tbody>
</table>

<script>
let newestFirst = true;

function updateButton() {
    document.getElementById("sortBtn").textContent =
        newestFirst
            ? "Sort: Newest â†’ Oldest"
            : "Sort: Oldest â†’ Newest";
}

function toggleSort() {

    const tbody = document.getElementById("tableBody");

    const rows = Array.from(tbody.querySelectorAll("tr"));

    rows.sort((a, b) => {

        const at = parseInt(a.dataset.mtime);
        const bt = parseInt(b.dataset.mtime);

        return newestFirst
            ? at - bt
            : bt - at;
    });

    tbody.innerHTML = "";

    rows.forEach(r => tbody.appendChild(r));

    newestFirst = !newestFirst;

    updateButton();
}

function filterTable() {

    const query =
        document.getElementById("searchBox")
        .value
        .toLowerCase();

    const rows =
        document.querySelectorAll("#tableBody tr");

    let visible = 0;

    rows.forEach(row => {

        const name = row.dataset.name;
        const stream = row.dataset.stream;

        const match =
            name.includes(query) ||
            stream.includes(query);

        row.style.display =
            match ? "" : "none";

        if (match)
            visible++;
    });

    document.getElementById("videoCount")
        .textContent = visible;
}

updateButton();
</script>

</body>
</html>

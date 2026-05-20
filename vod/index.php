<?php
$dir = "/var/www/vod";

$files = array_values(array_filter(scandir($dir), function($f) use ($dir) {
    return $f !== '.' && $f !== '..' && is_file($dir . '/' . $f);
}));

$data = [];

foreach ($files as $file) {
    $path = $dir . '/' . $file;

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (!in_array($ext, ['mp4', 'flv', 'mkv', 'webm', 'avi', 'mov'])) {
        continue;
    }

    $data[] = [
        "name" => $file,
        "mtime" => filemtime($path),
        "size" => filesize($path),
        "ext" => $ext
    ];
}

usort($data, function($a, $b) {
    return $a["mtime"] <=> $b["mtime"];
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>VOD Library</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #0f1115;
    color: #e6e6e6;
    margin: 20px;
}

h1 { margin-bottom: 10px; }

button {
    background: #1f2430;
    color: white;
    border: 1px solid #333;
    padding: 6px 10px;
    cursor: pointer;
    margin-bottom: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #171a21;
}

th, td {
    padding: 10px;
    border-bottom: 1px solid #2a2f3a;
    text-align: left;
}

th { background: #1f2430; }

tr:hover { background: #2a3140; }

a {
    color: #4ea1ff;
    text-decoration: none;
}
</style>
</head>

<body>

<h1>ðŸ“¼ VOD Library</h1>

<!-- ðŸ”¥ UPDATED BUTTON -->
<button id="sortBtn" onclick="toggleSort()">Sort: Oldest â†’ Newest</button>

<table>
    <thead>
        <tr>
            <th>File</th>
            <th>Type</th>
            <th>Size</th>
            <th>Modified</th>
        </tr>
    </thead>
    <tbody id="tableBody">
        <?php foreach ($data as $f): ?>
            <tr>
                <td>
                    <a href="/vod/<?php echo urlencode($f["name"]); ?>" target="_blank">
                        <?php echo htmlspecialchars($f["name"]); ?>
                    </a>
                </td>
                <td><?php echo strtoupper($f["ext"]); ?></td>
                <td><?php echo round($f["size"]/1024/1024, 2); ?> MB</td>
                <td><?php echo date("Y-m-d H:i:s", $f["mtime"]); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
let asc = true;

function updateButton() {
    const btn = document.getElementById("sortBtn");
    btn.textContent = asc
        ? "Sort: Oldest â†’ Newest"
        : "Sort: Newest â†’ Oldest";
}

function toggleSort() {
    const tbody = document.getElementById("tableBody");
    const rows = Array.from(tbody.querySelectorAll("tr"));

    rows.reverse();
    tbody.innerHTML = "";
    rows.forEach(r => tbody.appendChild(r));

    asc = !asc;
    updateButton();
}

// initialize correct label
updateButton();
</script>

</body>
</html>

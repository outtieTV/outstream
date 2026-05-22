<?php

$dir = "/var/www/vod";

$file = isset($_GET['file'])
    ? basename($_GET['file'])
    : '';

if (!$file) {
    die("Missing file.");
}

$path = $dir . '/' . $file;

if (!file_exists($path)) {
    die("VOD not found.");
}

$baseName = pathinfo($file, PATHINFO_FILENAME);

$jsonPath = $dir . '/' . $baseName . '.json';

$metadata = [
    "title" => "",
    "description" => ""
];

if (file_exists($jsonPath)) {

    $decoded = json_decode(file_get_contents($jsonPath), true);

    if (is_array($decoded)) {
        $metadata = array_merge($metadata, $decoded);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $metadata["title"] =
        trim($_POST['title'] ?? '');

    $metadata["description"] =
        trim($_POST['description'] ?? '');

    file_put_contents(
        $jsonPath,
        json_encode($metadata, JSON_PRETTY_PRINT)
    );

    header("Location: /vods/");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<title>Edit Metadata</title>

<style>

body {
    background: #0b0f14;
    color: white;
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: auto;
    padding: 30px;
}

input,
textarea {
    width: 100%;
    background: #161b22;
    color: white;
    border: 1px solid #30363d;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 20px;
    font-size: 15px;
}

textarea {
    min-height: 180px;
    resize: vertical;
}

button {
    background: #238636;
    border: none;
    color: white;
    padding: 12px 18px;
    border-radius: 10px;
    cursor: pointer;
}

button:hover {
    background: #2ea043;
}

.back {
    display: inline-block;
    margin-bottom: 24px;
    color: #58a6ff;
    text-decoration: none;
}

</style>
</head>
<body>

<a class="back" href="/vods/">
    ← Back to VOD Library
</a>

<h1>Edit Metadata</h1>

<p>
    Editing:
    <strong><?php echo htmlspecialchars($file); ?></strong>
</p>

<form method="POST">

    <label>Title</label>

    <input
        type="text"
        name="title"
        value="<?php echo htmlspecialchars($metadata["title"]); ?>"
    >

    <label>Description</label>

    <textarea
        name="description"
    ><?php echo htmlspecialchars($metadata["description"]); ?></textarea>

    <button type="submit">
        Save Metadata
    </button>

</form>

</body>
</html>


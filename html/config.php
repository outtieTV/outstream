<?php
// config.php

function load_config() {
    $jsonPath = __DIR__ . '/metadata.json';

    if (!file_exists($jsonPath)) {
        // Fallback defaults if the file is missing
        return [
            'streamKey' => 'test',
            'downloadBaseUrl' => 'http://localhost/clip/'
        ];
    }

    $jsonData = file_get_contents($jsonPath);
    $config = json_decode($jsonData, true);

    // Verify JSON parsed correctly, otherwise return defaults
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'streamKey' => 'test',
            'downloadBaseUrl' => 'http://localhost/clip/'
        ];
    }

    return $config;
}

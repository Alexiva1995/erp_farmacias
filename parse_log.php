<?php
$logFile = 'storage/logs/laravel.log';
$content = file_get_contents($logFile);
$entries = preg_split('/\[20\d{2}-\d{2}-\d{2}/', $content);

foreach (array_slice($entries, -15) as $entry) {
    $lines = explode("\n", $entry);
    $firstLine = trim($lines[0]);
    if ($firstLine) {
        echo "[2" . substr($firstLine, 0, 200) . "\n";
    }
}

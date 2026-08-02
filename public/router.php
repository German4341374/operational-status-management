<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_string($path) && '/' !== $path) {
    $file = __DIR__ . $path;
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/index.php';

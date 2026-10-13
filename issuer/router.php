<?php
declare(strict_types=1);

if (in_array(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), array('/app.css', '/app.js'), true)) {
    return false;
}
require __DIR__ . '/public/index.php';

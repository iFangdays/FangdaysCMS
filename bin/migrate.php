<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/autoload.php';

use FangdaysCMS\Cms\Database;
use FangdaysCMS\Cms\Migrator;

$configFile = $argv[1] ?? dirname(__DIR__) . '/config/app.php';
if (! is_file($configFile)) {
    fwrite(STDERR, "FangdaysCMS config/app.php does not exist.\n");
    exit(1);
}
try {
    $config = require $configFile;
    if (! is_array($config) || ! is_array($config['database'] ?? null)) {
        throw new RuntimeException('Invalid database configuration.');
    }
    Migrator::ensure(Database::connect($config['database']));
    echo 'FangdaysCMS schema version ' . Migrator::VERSION . " is ready.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration failed: ' . $error->getMessage() . "\n");
    exit(1);
}

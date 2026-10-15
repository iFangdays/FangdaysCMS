<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');

spl_autoload_register(static function (string $class): void {
    $prefix = 'FangdaysCMS\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

try {
    (new FangdaysCMS\Cms\App(dirname(__DIR__)))->run();
} catch (Throwable $error) {
    error_log('FangdaysCMS: ' . $error);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo '站点暂时无法处理请求。';
}

<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/autoload.php';

use FangdaysCMS\Cms\Quickstart;

try {
    if ($argc > 3 || in_array('--help', $argv, true)) {
        throw new RuntimeException('用法：php bin/quickstart.php [站点域名] [站点名称]');
    }
    $home = getenv('HOME');
    if (! is_string($home) || $home === '' || ! is_dir($home)) {
        throw new RuntimeException('无法确定 HOME 目录；请在正常用户的命令行运行。');
    }
    $result = Quickstart::install(dirname(__DIR__), $home . '/.fangdayscms/issuer.key', $argv[1] ?? '127.0.0.1', $argv[2] ?? 'FangdaysCMS');
    echo "FangdaysCMS 安装完成。\n";
    echo '站点域名：' . $result['domain'] . "\n";
    echo '后台账号：' . $result['username'] . "\n";
    echo '后台密码（只显示这一次）：' . $result['password'] . "\n";
    echo '签发私钥：' . $result['key_file'] . "\n";
    echo "启动：php -S 127.0.0.1:8000 -t public public/router.php\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/autoload.php';

use FangdaysCMS\Licensing\IssuerRegistry;

function issuerHelp(): never
{
    fwrite(STDERR, "Usage:\n"
        . "  php bin/issuer.php setup /outside-project/private.key KEY_ID\n"
        . "  php bin/issuer.php serve [PORT]                # local browser UI, default 8890\n"
        . "  php bin/issuer.php public-key\n"
        . "  php bin/issuer.php reset-password\n"
        . "  php bin/issuer.php issue DOMAIN CUSTOMER [YYYY-MM-DD|never]\n"
        . "  php bin/issuer.php import /path/to/license.code [CUSTOMER]\n"
        . "  php bin/issuer.php list [SEARCH]\n"
        . "Data directory: FANGDAYS_ISSUER_HOME or ~/.fangdayscms (outside CMS project).\n");
    exit(1);
}

try {
    $command = $argv[1] ?? '';
    $directory = IssuerRegistry::defaultDirectory();
    if ($command === 'setup' && $argc === 4) {
        $result = IssuerRegistry::setup($directory, $argv[2], $argv[3]);
        echo "授权中心已初始化。请将以下登录密码保存在密码管理器中；它只显示这一次。\n";
        echo 'password=' . $result['password'] . PHP_EOL;
        echo 'key_id=' . $result['key_id'] . PHP_EOL;
        echo 'public_key=' . $result['public_key'] . PHP_EOL;
        $localCode = dirname(__DIR__) . '/var/license.code';
        if (is_file($localCode)) {
            try {
                (new IssuerRegistry($directory))->import((string) file_get_contents($localCode), '本机站点', '初始化时导入');
                echo "已导入当前 FangdaysCMS 站点的授权码。\n";
            } catch (Throwable $error) {
                echo '当前站点授权码未导入：' . $error->getMessage() . PHP_EOL;
            }
        }
        exit(0);
    }
    if ($command === 'serve' && ($argc === 2 || $argc === 3)) {
        new IssuerRegistry($directory);
        $port = $argc === 3 ? (int) $argv[2] : 8890;
        if ((string) $port !== ($argv[2] ?? '8890') || $port < 1024 || $port > 65535) {
            throw new InvalidArgumentException('Port must be between 1024 and 65535.');
        }
        $root = dirname(__DIR__);
        echo "授权中心：http://127.0.0.1:$port/\n按 Ctrl+C 停止。\n";
        $commandLine = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port
            . ' -t ' . escapeshellarg($root . '/issuer/public')
            . ' ' . escapeshellarg($root . '/issuer/router.php');
        passthru($commandLine, $exitCode);
        exit($exitCode);
    }
    $registry = new IssuerRegistry($directory);
    if ($command === 'public-key' && $argc === 2) {
        echo 'key_id=' . $registry->keyId() . PHP_EOL;
        echo 'public_key=' . $registry->publicKey() . PHP_EOL;
        exit(0);
    }
    if ($command === 'reset-password' && $argc === 2) {
        echo 'password=' . $registry->resetPassword() . PHP_EOL;
        exit(0);
    }
    if ($command === 'issue' && ($argc === 4 || $argc === 5)) {
        $result = $registry->issue($argv[2], $argv[3], $argv[4] ?? 'never');
        echo $result['code'] . PHP_EOL;
        exit(0);
    }
    if ($command === 'import' && ($argc === 3 || $argc === 4)) {
        $code = file_get_contents($argv[2]);
        if ($code === false) {
            throw new RuntimeException('Cannot read license code file.');
        }
        $result = $registry->import($code, $argv[3] ?? '');
        echo 'imported=' . $result['claims']['license_id'] . PHP_EOL;
        exit(0);
    }
    if ($command === 'list' && ($argc === 2 || $argc === 3)) {
        foreach ($registry->list($argv[2] ?? '') as $row) {
            $expiry = $row['expires_at'] === null ? '永久' : gmdate('Y-m-d', (int) $row['expires_at']);
            echo implode("\t", array($row['license_id'], $row['domains'], $row['customer'], $expiry)) . PHP_EOL;
        }
        exit(0);
    }
    issuerHelp();
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

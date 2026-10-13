<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/autoload.php';

use FangdaysCMS\Licensing\LicenseCode;
use FangdaysCMS\Licensing\LicenseStore;

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function privatePath(string $path): string
{
    $directory = realpath(dirname($path));
    $project = realpath(dirname(__DIR__));
    if ($directory === false || $project === false) {
        fail('Private-key directory must already exist.');
    }
    if ($directory === $project || str_starts_with($directory, $project . DIRECTORY_SEPARATOR)) {
        fail('Private key must be outside the FangdaysCMS project.');
    }
    return $directory . DIRECTORY_SEPARATOR . basename($path);
}

function loadPrivate(string $path): string
{
    $encoded = @file_get_contents(privatePath($path));
    $key = $encoded === false ? false : base64_decode(trim($encoded), true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        fail('Private key is missing or invalid.');
    }
    return $key;
}

function loadStore(string $file): LicenseStore
{
    if (! is_file($file)) {
        fail('License config file does not exist.');
    }
    $config = require $file;
    if (! is_array($config) || ! is_array($config['license_public_keys'] ?? null) || ! is_string($config['site_domain'] ?? null) || ! is_string($config['license_file'] ?? null)) {
        fail('License config is invalid.');
    }
    return new LicenseStore($config['license_file'], $config['license_public_keys'], $config['site_domain']);
}

try {
    $command = $argv[1] ?? '';
    if ($command === 'keygen' && $argc === 3) {
        $path = privatePath($argv[2]);
        $pair = sodium_crypto_sign_keypair();
        $secret = base64_encode(sodium_crypto_sign_secretkey($pair)) . PHP_EOL;
        $oldUmask = umask(0077);
        $handle = @fopen($path, 'x');
        umask($oldUmask);
        if ($handle === false) {
            fail('Private key already exists or cannot be created.');
        }
        $written = fwrite($handle, $secret);
        fclose($handle);
        if ($written !== strlen($secret)) {
            unlink($path);
            fail('Could not write complete private key.');
        }
        chmod($path, 0600);
        echo 'public_key=' . base64_encode(sodium_crypto_sign_publickey($pair)) . PHP_EOL;
        exit(0);
    }

    if ($command === 'issue' && ($argc === 6 || $argc === 7)) {
        $private = loadPrivate($argv[2]);
        $domains = array();
        foreach (explode(',', $argv[4]) as $input) {
            $domain = LicenseCode::normalizeDomain($input);
            if ($domain === null || in_array($domain, $domains, true)) {
                fail('Domains must be distinct hostnames separated by commas.');
            }
            $domains[] = $domain;
        }
        $expires = null;
        if ($argv[5] !== 'never') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $argv[5], new DateTimeZone('UTC'));
            if ($date === false || $date->format('Y-m-d') !== $argv[5]) {
                fail('Expiry must be YYYY-MM-DD or never.');
            }
            $expires = $date->modify('+1 day')->getTimestamp() - 1;
        }
        $now = time();
        if ($expires !== null && $expires < $now) {
            fail('Expiry is already in the past.');
        }
        $features = $argc === 7 && $argv[6] !== '' ? explode(',', $argv[6]) : array();
        $claims = array(
            'schema' => 1,
            'product' => 'FangdaysCMS',
            'key_id' => $argv[3],
            'license_id' => bin2hex(random_bytes(16)),
            'domains' => $domains,
            'features' => $features,
            'issued_at' => $now,
            'not_before' => $now,
            'expires_at' => $expires
        );
        echo LicenseCode::issue($claims, $private) . PHP_EOL;
        exit(0);
    }

    if ($command === 'verify' && $argc === 5) {
        $code = trim(stream_get_contents(STDIN));
        $result = LicenseCode::verify($code, array($argv[2] => $argv[3]), $argv[4]);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit($result['valid'] ? 0 : 2);
    }

    if ($command === 'activate' && $argc === 3) {
        $result = loadStore($argv[2])->activate(stream_get_contents(STDIN));
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit($result['valid'] ? 0 : 2);
    }

    if ($command === 'status' && $argc === 3) {
        $result = loadStore($argv[2])->status();
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit($result['valid'] ? 0 : 2);
    }

    fail("Usage:\n  php bin/license.php keygen /outside-project/private.key\n  php bin/license.php issue /outside-project/private.key main example.com,www.example.com 2027-12-31 [feature1,feature2]\n  php bin/license.php verify KEY_ID PUBLIC_KEY DOMAIN < license.code\n  php bin/license.php activate config/app.php < license.code\n  php bin/license.php status config/app.php");
} catch (Throwable $error) {
    fail($error->getMessage());
}

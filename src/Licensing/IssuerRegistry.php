<?php
declare(strict_types=1);

namespace FangdaysCMS\Licensing;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Owner-side license ledger. Never deploy its database or signing key to a customer site. */
final class IssuerRegistry
{
    private PDO $pdo;

    public static function defaultDirectory(): string
    {
        $configured = getenv('FANGDAYS_ISSUER_HOME');
        $userHome = getenv('HOME');
        $home = $configured !== false && $configured !== '' ? $configured : ($userHome === false ? '' : $userHome . '/.fangdayscms');
        if (! is_string($home) || $home === '' || $home[0] !== '/') {
            throw new RuntimeException('Set FANGDAYS_ISSUER_HOME to an absolute path outside the CMS project.');
        }
        return $home;
    }

    public static function setup(string $directory, string $keyPath, string $keyId): array
    {
        if (! preg_match('/^[a-zA-Z0-9_-]{1,32}$/D', $keyId)) {
            throw new InvalidArgumentException('Key ID must be 1–32 letters, digits, underscores or hyphens.');
        }
        self::outsideProject($keyPath, true);
        $secret = self::secretFromFile($keyPath);
        if (! is_dir($directory)) {
            if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Cannot create issuer data directory.');
            }
        }
        self::outsideProject($directory, false);
        if ((fileperms($directory) & 0077) !== 0) {
            throw new RuntimeException('Issuer data directory must be private (mode 0700).');
        }
        $dbFile = $directory . '/issuer.sqlite';
        if (is_file($dbFile)) {
            throw new RuntimeException('Issuer is already set up; existing records were not changed.');
        }
        $registry = new self($directory, true);
        $password = bin2hex(random_bytes(16));
        $registry->set('key_path', realpath($keyPath));
        $registry->set('key_id', $keyId);
        $registry->set('public_key', base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret)));
        $registry->set('password_hash', password_hash($password, PASSWORD_DEFAULT));
        return array('password' => $password, 'public_key' => $registry->publicKey(), 'key_id' => $keyId);
    }

    public function __construct(string $directory, bool $create = false)
    {
        self::outsideProject($directory, false);
        if ((fileperms($directory) & 0077) !== 0) {
            throw new RuntimeException('Issuer data directory must be private (mode 0700).');
        }
        $dbFile = $directory . '/issuer.sqlite';
        if (! $create && ! is_file($dbFile)) {
            throw new RuntimeException('Issuer is not set up. Run: php bin/issuer.php setup /path/to/private.key KEY_ID');
        }
        $this->pdo = new PDO('sqlite:' . $dbFile, null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS settings (name TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS licenses (
            license_id TEXT PRIMARY KEY, code TEXT NOT NULL, domains TEXT NOT NULL,
            customer TEXT NOT NULL, notes TEXT NOT NULL, key_id TEXT NOT NULL,
            issued_at INTEGER NOT NULL, expires_at INTEGER, source TEXT NOT NULL,
            recorded_at INTEGER NOT NULL
        )');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS licenses_issued_at ON licenses(issued_at DESC)');
        chmod($dbFile, 0600);
    }

    public function keyId(): string
    {
        return $this->setting('key_id');
    }

    public function publicKey(): string
    {
        return $this->setting('public_key');
    }

    public function authenticate(string $password): bool
    {
        return password_verify($password, $this->setting('password_hash'));
    }

    public function resetPassword(): string
    {
        $password = bin2hex(random_bytes(16));
        $stmt = $this->pdo->prepare('UPDATE settings SET value = ? WHERE name = ?');
        $stmt->execute(array(password_hash($password, PASSWORD_DEFAULT), 'password_hash'));
        return $password;
    }

    public function issue(string $domainInput, string $customer, string $expiresInput, string $notes = ''): array
    {
        $domains = array();
        foreach (explode(',', $domainInput) as $input) {
            $domain = LicenseCode::normalizeDomain($input);
            if ($domain === null || in_array($domain, $domains, true)) {
                throw new InvalidArgumentException('Enter distinct valid domains separated by commas.');
            }
            $domains[] = $domain;
        }
        if (count($domains) > 50) {
            throw new InvalidArgumentException('A license supports at most 50 domains.');
        }
        self::validateMeta($customer, $notes);
        $expires = null;
        $expiresInput = trim($expiresInput);
        if ($expiresInput !== '' && $expiresInput !== 'never') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expiresInput, new DateTimeZone('UTC'));
            if ($date === false || $date->format('Y-m-d') !== $expiresInput) {
                throw new InvalidArgumentException('Expiry must be YYYY-MM-DD or left blank.');
            }
            $expires = $date->modify('+1 day')->getTimestamp() - 1;
        }
        $now = time();
        if ($expires !== null && $expires < $now) {
            throw new InvalidArgumentException('Expiry is already in the past.');
        }
        $secret = self::secretFromFile($this->setting('key_path'));
        if (base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret)) !== $this->publicKey()) {
            throw new RuntimeException('Signing key does not match the configured public key.');
        }
        $claims = array(
            'schema' => 1,
            'product' => 'FangdaysCMS',
            'key_id' => $this->keyId(),
            'license_id' => bin2hex(random_bytes(16)),
            'domains' => $domains,
            'features' => array(),
            'issued_at' => $now,
            'not_before' => $now,
            'expires_at' => $expires
        );
        $code = LicenseCode::issue($claims, $secret);
        $this->save($code, $claims, trim($customer), trim($notes), 'issued');
        return array('code' => $code, 'claims' => $claims);
    }

    public function import(string $code, string $customer = '', string $notes = ''): array
    {
        self::validateMeta($customer, $notes);
        $code = trim($code);
        if (strlen($code) > 8192 || ! preg_match('/^FD1\.([A-Za-z0-9_-]+)\.[A-Za-z0-9_-]+$/D', $code, $match)) {
            throw new InvalidArgumentException('License code format is invalid.');
        }
        $body = base64_decode(strtr($match[1], '-_', '+/') . str_repeat('=', (4 - strlen($match[1]) % 4) % 4), true);
        $claims = $body === false ? null : json_decode($body, true, 32);
        $domain = is_array($claims) && is_array($claims['domains'] ?? null) ? ($claims['domains'][0] ?? null) : null;
        if (! is_string($domain)) {
            throw new InvalidArgumentException('License code has no valid domain.');
        }
        $issuedAt = is_int($claims['issued_at'] ?? null) ? $claims['issued_at'] : time();
        $result = LicenseCode::verify($code, array($this->keyId() => $this->publicKey()), $domain, $issuedAt);
        if (! $result['valid']) {
            throw new InvalidArgumentException('License verification failed: ' . $result['reason']);
        }
        if ($this->get($result['claims']['license_id']) !== null) {
            throw new InvalidArgumentException('This license is already in the registry.');
        }
        $this->save($code, $result['claims'], trim($customer), trim($notes), 'imported');
        return array('code' => $code, 'claims' => $result['claims']);
    }

    public function get(string $licenseId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM licenses WHERE license_id = ?');
        $stmt->execute(array($licenseId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function list(string $search = ''): array
    {
        $search = trim($search);
        if (mb_strlen($search) > 100) {
            throw new InvalidArgumentException('Search is too long.');
        }
        if ($search === '') {
            return $this->pdo->query('SELECT * FROM licenses ORDER BY issued_at DESC, license_id DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = $this->pdo->prepare('SELECT * FROM licenses WHERE customer LIKE ? OR domains LIKE ? OR notes LIKE ? OR license_id LIKE ? ORDER BY issued_at DESC, license_id DESC LIMIT 500');
        $needle = '%' . $search . '%';
        $stmt->execute(array($needle, $needle, $needle, $needle));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM licenses')->fetchColumn();
    }

    public function updateMeta(string $licenseId, string $customer, string $notes): void
    {
        self::validateMeta($customer, $notes);
        $stmt = $this->pdo->prepare('UPDATE licenses SET customer = ?, notes = ? WHERE license_id = ?');
        $stmt->execute(array(trim($customer), trim($notes), $licenseId));
        if ($stmt->rowCount() === 0 && $this->get($licenseId) === null) {
            throw new InvalidArgumentException('License was not found.');
        }
    }

    private function save(string $code, array $claims, string $customer, string $notes, string $source): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO licenses (license_id, code, domains, customer, notes, key_id, issued_at, expires_at, source, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(array(
            $claims['license_id'], $code, implode(', ', $claims['domains']), $customer, $notes,
            $claims['key_id'], $claims['issued_at'], $claims['expires_at'], $source, time()
        ));
    }

    private static function validateMeta(string $customer, string $notes): void
    {
        if (mb_strlen(trim($customer)) > 100 || mb_strlen(trim($notes)) > 2000) {
            throw new InvalidArgumentException('Customer or notes are too long.');
        }
    }

    private function setting(string $name): string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM settings WHERE name = ?');
        $stmt->execute(array($name));
        $value = $stmt->fetchColumn();
        if (! is_string($value)) {
            throw new RuntimeException('Issuer setting is missing: ' . $name);
        }
        return $value;
    }

    private function set(string $name, string $value): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?)');
        $stmt->execute(array($name, $value));
    }

    private static function secretFromFile(string $path): string
    {
        self::outsideProject($path, true);
        if ((fileperms($path) & 0077) !== 0) {
            throw new InvalidArgumentException('Signing private key must be private (mode 0600).');
        }
        $contents = @file_get_contents($path);
        $secret = $contents === false ? false : base64_decode(trim($contents), true);
        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException('Signing private key is missing or invalid.');
        }
        return $secret;
    }

    private static function outsideProject(string $path, bool $file): void
    {
        $real = realpath($path);
        $project = realpath(dirname(__DIR__, 2));
        if ($real === false || $project === false || $real === $project || str_starts_with($real, $project . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Issuer data and private key must exist outside the FangdaysCMS project.');
        }
        if ($file && ! is_file($real)) {
            throw new InvalidArgumentException('Signing private key does not exist.');
        }
    }
}

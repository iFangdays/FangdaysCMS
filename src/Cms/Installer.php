<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use FangdaysCMS\Licensing\LicenseCode;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class Installer
{
    public static function install(array $input, string $root): void
    {
        $configFile = $root . '/config/app.php';
        if (is_file($configFile)) {
            throw new RuntimeException('FangdaysCMS is already installed.');
        }
        if (! is_dir($root . '/config') && ! mkdir($root . '/config', 0700, true) && ! is_dir($root . '/config')) {
            throw new RuntimeException('Cannot create configuration directory.');
        }
        $siteName = trim(self::field($input, 'site_name'));
        $domain = LicenseCode::normalizeDomain(self::field($input, 'site_domain'));
        $username = trim(self::field($input, 'admin_username'));
        $password = self::field($input, 'admin_password');
        $keyId = trim(self::field($input, 'license_key_id'));
        $publicKey = trim(self::field($input, 'license_public_key'));
        $decodedKey = base64_decode($publicKey, true);
        if ($siteName === '' || mb_strlen($siteName) > 100 || $domain === null) {
            throw new InvalidArgumentException('Site name or domain is invalid.');
        }
        if (! preg_match('/^[a-zA-Z0-9_-]{3,32}$/D', $username) || strlen($password) < 12) {
            throw new InvalidArgumentException('Admin username or password is invalid; password needs at least 12 characters.');
        }
        if (! preg_match('/^[a-zA-Z0-9_-]{1,32}$/D', $keyId) || $decodedKey === false || strlen($decodedKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new InvalidArgumentException('License public key or key ID is invalid.');
        }
        $driver = self::field($input, 'database_driver', 'sqlite');
        if ($driver === 'sqlite') {
            $dbConfig = array('driver' => 'sqlite', 'path' => $root . '/var/content.sqlite');
            if (! is_dir($root . '/var') && ! mkdir($root . '/var', 0700, true)) {
                throw new RuntimeException('Cannot create data directory.');
            }
        } elseif ($driver === 'mysql') {
            $name = trim(self::field($input, 'mysql_name'));
            $host = trim(self::field($input, 'mysql_host'));
            $port = trim(self::field($input, 'mysql_port', '3306'));
            $user = trim(self::field($input, 'mysql_user'));
            if (! preg_match('/^[a-zA-Z0-9_]+$/D', $name) || ! preg_match('/^[a-zA-Z0-9.:-]+$/D', $host) || ! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535 || $user === '') {
                throw new InvalidArgumentException('MySQL connection settings are invalid.');
            }
            $dbConfig = array(
                'driver' => 'mysql',
                'host' => $host,
                'port' => $port,
                'name' => $name,
                'user' => $user,
                'password' => self::field($input, 'mysql_password')
            );
        } else {
            throw new InvalidArgumentException('Database driver must be sqlite or mysql.');
        }

        $pdo = Database::connect($dbConfig);
        self::createSchema($pdo, $driver);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            throw new RuntimeException('This database already contains a CMS administrator.');
        }
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, ?)');
        $stmt->execute(array($username, password_hash($password, PASSWORD_DEFAULT), time()));
        Migrator::ensure($pdo);

        $config = array(
            'site_name' => $siteName,
            'site_domain' => $domain,
            'database' => $dbConfig,
            'license_public_keys' => array($keyId => $publicKey),
            'license_file' => $root . '/var/license.code'
        );
        $contents = "<?php\ndeclare(strict_types=1);\nreturn " . var_export($config, true) . ";\n";
        $oldUmask = umask(0077);
        $handle = @fopen($configFile, 'x');
        umask($oldUmask);
        if ($handle === false) {
            throw new RuntimeException('Cannot create config/app.php.');
        }
        $written = fwrite($handle, $contents);
        fclose($handle);
        if ($written !== strlen($contents)) {
            unlink($configFile);
            throw new RuntimeException('Cannot write complete CMS configuration.');
        }
        chmod($configFile, 0600);
    }

    private static function createSchema(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $statements = array(
                'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, created_at INTEGER NOT NULL)',
                'CREATE TABLE IF NOT EXISTS categories (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_id INTEGER NULL REFERENCES categories(id) ON DELETE RESTRICT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, sort_order INTEGER NOT NULL DEFAULT 0)',
                'CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER NULL REFERENCES categories(id) ON DELETE SET NULL, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, body TEXT NOT NULL, status TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)'
            );
        } else {
            $statements = array(
                'CREATE TABLE IF NOT EXISTS users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(32) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS categories (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, parent_id BIGINT UNSIGNED NULL, name VARCHAR(191) NOT NULL, slug VARCHAR(191) NOT NULL UNIQUE, sort_order INT NOT NULL DEFAULT 0, CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS posts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id BIGINT UNSIGNED NULL, title VARCHAR(191) NOT NULL, slug VARCHAR(191) NOT NULL UNIQUE, body LONGTEXT NOT NULL, status VARCHAR(16) NOT NULL, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL, CONSTRAINT fk_posts_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
    }

    private static function field(array $input, string $name, string $default = ''): string
    {
        return is_string($input[$name] ?? null) ? $input[$name] : $default;
    }
}

<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;
use PDO;

final class Database
{
    public static function connect(array $config): PDO
    {
        $driver = $config['driver'] ?? '';
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        );
        if ($driver === 'sqlite') {
            $path = $config['path'] ?? '';
            if (! is_string($path) || $path === '') {
                throw new InvalidArgumentException('SQLite database path is missing.');
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        }
        if ($driver === 'mysql') {
            foreach (array('host', 'port', 'name', 'user') as $field) {
                if (! is_string($config[$field] ?? null) || $config[$field] === '') {
                    throw new InvalidArgumentException('MySQL configuration is incomplete.');
                }
            }
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['name']);
            return new PDO($dsn, $config['user'], (string) ($config['password'] ?? ''), $options);
        }
        throw new InvalidArgumentException('Unsupported database driver.');
    }
}

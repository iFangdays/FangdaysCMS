<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use PDO;

/** Additive schema upgrade from the original FangdaysCMS installation. */
final class Migrator
{
    public const VERSION = 5;

    public static function ensure(PDO $pdo): void
    {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql
            ? 'CREATE TABLE IF NOT EXISTS cms_schema (id TINYINT UNSIGNED PRIMARY KEY, version INT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            : 'CREATE TABLE IF NOT EXISTS cms_schema (id INTEGER PRIMARY KEY, version INTEGER NOT NULL)');
        $version = $pdo->query('SELECT version FROM cms_schema WHERE id = 1')->fetchColumn();
        if ($version !== false && (int) $version >= self::VERSION) {
            return;
        }
        if (! $mysql) {
            $pdo->beginTransaction();
        }
        try {
            foreach (self::statements($mysql) as $statement) {
                $pdo->exec($statement);
            }
            $ignore = $mysql ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
            foreach (array('owner' => '站点所有者', 'editor' => '编辑', 'viewer' => '只读') as $slug => $name) {
                $stmt = $pdo->prepare($ignore . ' INTO roles (slug, name) VALUES (?, ?)');
                $stmt->execute(array($slug, $name));
            }
            $roleIds = array();
            foreach ($pdo->query('SELECT id, slug FROM roles') as $role) {
                $roleIds[$role['slug']] = (int) $role['id'];
            }
            foreach (array(
                'editor' => array('dashboard.view', 'content.view', 'content.manage', 'taxonomy.manage', 'media.manage'),
                'viewer' => array('dashboard.view', 'content.view')
            ) as $role => $permissions) {
                $stmt = $pdo->prepare($ignore . ' INTO role_permissions (role_id, permission) VALUES (?, ?)');
                foreach ($permissions as $permission) {
                    $stmt->execute(array($roleIds[$role], $permission));
                }
            }
            $pdo->exec($ignore . ' INTO user_roles (user_id, role_id) SELECT id, ' . $roleIds['owner'] . ' FROM users');
            $pdo->exec($mysql
                ? 'INSERT INTO cms_schema (id, version) VALUES (1, 5) ON DUPLICATE KEY UPDATE version = 5'
                : 'INSERT INTO cms_schema (id, version) VALUES (1, 5) ON CONFLICT(id) DO UPDATE SET version = 5');
            if (! $mysql) {
                $pdo->commit();
            }
        } catch (\Throwable $error) {
            if (! $mysql && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private static function statements(bool $mysql): array
    {
        if ($mysql) {
            return array(
                'CREATE TABLE IF NOT EXISTS roles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS role_permissions (role_id BIGINT UNSIGNED NOT NULL, permission VARCHAR(64) NOT NULL, PRIMARY KEY (role_id, permission), FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS user_roles (user_id BIGINT UNSIGNED PRIMARY KEY, role_id BIGINT UNSIGNED NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (role_id) REFERENCES roles(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS tags (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, slug VARCHAR(160) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS post_tags (post_id BIGINT UNSIGNED NOT NULL, tag_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (post_id, tag_id), FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE, FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS members (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(191) NOT NULL UNIQUE, display_name VARCHAR(100) NOT NULL, password_hash VARCHAR(255) NOT NULL, status VARCHAR(16) NOT NULL, created_at BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS post_visibility (post_id BIGINT UNSIGNED PRIMARY KEY, visibility VARCHAR(16) NOT NULL, FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS pages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(191) NOT NULL, slug VARCHAR(160) NOT NULL UNIQUE, body LONGTEXT NOT NULL, status VARCHAR(16) NOT NULL, menu_order INT NOT NULL DEFAULT 0, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS site_settings (name VARCHAR(64) PRIMARY KEY, value TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS media (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, path VARCHAR(255) NOT NULL UNIQUE, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(40) NOT NULL, bytes BIGINT NOT NULL, created_at BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS post_cover (post_id BIGINT UNSIGNED PRIMARY KEY, media_id BIGINT UNSIGNED NOT NULL, FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE, FOREIGN KEY (media_id) REFERENCES media(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        return array(
            'CREATE TABLE IF NOT EXISTS roles (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, name TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS role_permissions (role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE, permission TEXT NOT NULL, PRIMARY KEY (role_id, permission))',
            'CREATE TABLE IF NOT EXISTS user_roles (user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, role_id INTEGER NOT NULL REFERENCES roles(id))',
            'CREATE TABLE IF NOT EXISTS tags (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE)',
            'CREATE TABLE IF NOT EXISTS post_tags (post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE, tag_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE, PRIMARY KEY (post_id, tag_id))',
            'CREATE TABLE IF NOT EXISTS members (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL COLLATE NOCASE UNIQUE, display_name TEXT NOT NULL, password_hash TEXT NOT NULL, status TEXT NOT NULL, created_at INTEGER NOT NULL)',
            'CREATE TABLE IF NOT EXISTS post_visibility (post_id INTEGER PRIMARY KEY REFERENCES posts(id) ON DELETE CASCADE, visibility TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS pages (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, body TEXT NOT NULL, status TEXT NOT NULL, menu_order INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)',
            'CREATE TABLE IF NOT EXISTS site_settings (name TEXT PRIMARY KEY, value TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS media (id INTEGER PRIMARY KEY AUTOINCREMENT, path TEXT NOT NULL UNIQUE, original_name TEXT NOT NULL, mime_type TEXT NOT NULL, bytes INTEGER NOT NULL, created_at INTEGER NOT NULL)',
            'CREATE TABLE IF NOT EXISTS post_cover (post_id INTEGER PRIMARY KEY REFERENCES posts(id) ON DELETE CASCADE, media_id INTEGER NOT NULL REFERENCES media(id))'
        );
    }
}

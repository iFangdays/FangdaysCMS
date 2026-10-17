<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use PDO;

/** Additive schema upgrade from the original FangdaysCMS installation. */
final class Migrator
{
    public const VERSION = 18;

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
            if (! self::hasColumn($pdo, $mysql, 'categories', 'parent_id')) {
                $pdo->exec($mysql
                    ? 'ALTER TABLE categories ADD COLUMN parent_id BIGINT UNSIGNED NULL, ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE RESTRICT'
                    : 'ALTER TABLE categories ADD COLUMN parent_id INTEGER NULL REFERENCES categories(id) ON DELETE RESTRICT');
            }
            if (! self::hasColumn($pdo, $mysql, 'post_meta', 'body_format')) {
                $pdo->exec($mysql
                    ? "ALTER TABLE post_meta ADD COLUMN body_format VARCHAR(16) NOT NULL DEFAULT 'plain'"
                    : "ALTER TABLE post_meta ADD COLUMN body_format TEXT NOT NULL DEFAULT 'plain'");
            }
            if (! self::hasColumn($pdo, $mysql, 'pages', 'body_format')) {
                $pdo->exec($mysql
                    ? "ALTER TABLE pages ADD COLUMN body_format VARCHAR(16) NOT NULL DEFAULT 'plain'"
                    : "ALTER TABLE pages ADD COLUMN body_format TEXT NOT NULL DEFAULT 'plain'");
            }
            if (! self::hasColumn($pdo, $mysql, 'category_settings', 'single_body_format')) {
                $pdo->exec($mysql
                    ? "ALTER TABLE category_settings ADD COLUMN single_body_format VARCHAR(16) NOT NULL DEFAULT 'plain'"
                    : "ALTER TABLE category_settings ADD COLUMN single_body_format TEXT NOT NULL DEFAULT 'plain'");
            }
            if (! self::hasColumn($pdo, $mysql, 'post_meta', 'published_at')) {
                $pdo->exec($mysql
                    ? 'ALTER TABLE post_meta ADD COLUMN published_at BIGINT NOT NULL DEFAULT 0'
                    : 'ALTER TABLE post_meta ADD COLUMN published_at INTEGER NOT NULL DEFAULT 0');
            }
            if (! self::hasColumn($pdo, $mysql, 'post_meta', 'sort_order')) {
                $pdo->exec($mysql
                    ? 'ALTER TABLE post_meta ADD COLUMN sort_order INT NOT NULL DEFAULT 255'
                    : 'ALTER TABLE post_meta ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 255');
            }
            $ignore = $mysql ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
            $existingRoles = $pdo->query('SELECT slug FROM roles')->fetchAll(PDO::FETCH_COLUMN);
            foreach (array('owner' => '站点所有者', 'editor' => '编辑', 'viewer' => '只读') as $slug => $name) {
                $stmt = $pdo->prepare($ignore . ' INTO roles (slug, name) VALUES (?, ?)');
                $stmt->execute(array($slug, $name));
            }
            $roleIds = array();
            foreach ($pdo->query('SELECT id, slug FROM roles') as $role) {
                $roleIds[$role['slug']] = (int) $role['id'];
            }
            foreach (array(
                'editor' => array('dashboard.view', 'content.view', 'content.manage', 'taxonomy.manage', 'media.manage', 'operations.manage'),
                'viewer' => array('dashboard.view', 'content.view')
            ) as $role => $permissions) {
                if (in_array($role, $existingRoles, true)) {
                    continue;
                }
                $stmt = $pdo->prepare($ignore . ' INTO role_permissions (role_id, permission) VALUES (?, ?)');
                foreach ($permissions as $permission) {
                    $stmt->execute(array($roleIds[$role], $permission));
                }
            }
            $pdo->exec($ignore . ' INTO user_roles (user_id, role_id) SELECT id, ' . $roleIds['owner'] . ' FROM users');
            $pdo->exec($ignore . " INTO content_models (slug, name) VALUES ('article', '文章模型')");
            $pdo->exec($mysql
                ? 'INSERT INTO cms_schema (id, version) VALUES (1, 18) ON DUPLICATE KEY UPDATE version = 18'
                : 'INSERT INTO cms_schema (id, version) VALUES (1, 18) ON CONFLICT(id) DO UPDATE SET version = 18');
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

    private static function hasColumn(PDO $pdo, bool $mysql, string $table, string $name): bool
    {
        if ($mysql) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $stmt->execute(array($table, $name));
            return (bool) $stmt->fetchColumn();
        }
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')') as $column) {
            if ($column['name'] === $name) {
                return true;
            }
        }
        return false;
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
                "CREATE TABLE IF NOT EXISTS pages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(191) NOT NULL, slug VARCHAR(160) NOT NULL UNIQUE, body LONGTEXT NOT NULL, body_format VARCHAR(16) NOT NULL DEFAULT 'plain', status VARCHAR(16) NOT NULL, menu_order INT NOT NULL DEFAULT 0, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                'CREATE TABLE IF NOT EXISTS site_settings (name VARCHAR(64) PRIMARY KEY, value TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS media (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, path VARCHAR(255) NOT NULL UNIQUE, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(40) NOT NULL, bytes BIGINT NOT NULL, created_at BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS post_cover (post_id BIGINT UNSIGNED PRIMARY KEY, media_id BIGINT UNSIGNED NOT NULL, FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE, FOREIGN KEY (media_id) REFERENCES media(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS post_gallery (post_id BIGINT UNSIGNED NOT NULL, position INT NOT NULL, media_id BIGINT UNSIGNED NOT NULL, title VARCHAR(150) NOT NULL DEFAULT \'\', PRIMARY KEY (post_id, position), FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE, FOREIGN KEY (media_id) REFERENCES media(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS post_attachment (post_id BIGINT UNSIGNED PRIMARY KEY, path VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, bytes BIGINT NOT NULL, FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS category_cover (category_id BIGINT UNSIGNED PRIMARY KEY, media_id BIGINT UNSIGNED NOT NULL, FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE, FOREIGN KEY (media_id) REFERENCES media(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                "CREATE TABLE IF NOT EXISTS category_gallery (category_id BIGINT UNSIGNED NOT NULL, position INT NOT NULL, media_id BIGINT UNSIGNED NOT NULL, title VARCHAR(150) NOT NULL DEFAULT '', PRIMARY KEY (category_id, position), FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE, FOREIGN KEY (media_id) REFERENCES media(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                'CREATE TABLE IF NOT EXISTS category_attachment (category_id BIGINT UNSIGNED PRIMARY KEY, path VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, bytes BIGINT NOT NULL, FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS page_cover (page_id BIGINT UNSIGNED PRIMARY KEY, media_id BIGINT UNSIGNED NOT NULL, FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE, FOREIGN KEY (media_id) REFERENCES media(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                "CREATE TABLE IF NOT EXISTS page_gallery (page_id BIGINT UNSIGNED NOT NULL, position INT NOT NULL, media_id BIGINT UNSIGNED NOT NULL, title VARCHAR(150) NOT NULL DEFAULT '', PRIMARY KEY (page_id, position), FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE, FOREIGN KEY (media_id) REFERENCES media(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                'CREATE TABLE IF NOT EXISTS page_attachment (page_id BIGINT UNSIGNED PRIMARY KEY, path VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, bytes BIGINT NOT NULL, FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS category_images (category_id BIGINT UNSIGNED NOT NULL, kind VARCHAR(16) NOT NULL, media_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (category_id, kind), FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE, FOREIGN KEY (media_id) REFERENCES media(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS content_models (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS model_fields (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, model_id BIGINT UNSIGNED NOT NULL, name VARCHAR(64) NOT NULL, label VARCHAR(100) NOT NULL, field_type VARCHAR(20) NOT NULL, options_json TEXT NOT NULL, is_required TINYINT NOT NULL DEFAULT 0, sort_order INT NOT NULL DEFAULT 0, UNIQUE KEY model_field_name (model_id, name), FOREIGN KEY (model_id) REFERENCES content_models(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS category_models (category_id BIGINT UNSIGNED PRIMARY KEY, model_id BIGINT UNSIGNED NOT NULL, FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE, FOREIGN KEY (model_id) REFERENCES content_models(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                "CREATE TABLE IF NOT EXISTS category_settings (category_id BIGINT UNSIGNED PRIMARY KEY, type VARCHAR(16) NOT NULL DEFAULT 'list', outlink VARCHAR(2048) NOT NULL DEFAULT '', seo_title VARCHAR(255) NOT NULL DEFAULT '', seo_keywords VARCHAR(255) NOT NULL DEFAULT '', seo_description TEXT NOT NULL, single_title VARCHAR(191) NOT NULL DEFAULT '', single_body LONGTEXT NOT NULL, single_body_format VARCHAR(16) NOT NULL DEFAULT 'plain', single_status VARCHAR(16) NOT NULL DEFAULT 'draft', FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS post_meta (post_id BIGINT UNSIGNED PRIMARY KEY, subtitle VARCHAR(255) NOT NULL DEFAULT '', author VARCHAR(100) NOT NULL DEFAULT '', source VARCHAR(100) NOT NULL DEFAULT '', title_color VARCHAR(7) NOT NULL DEFAULT '', outlink VARCHAR(2048) NOT NULL DEFAULT '', seo_keywords VARCHAR(255) NOT NULL DEFAULT '', seo_description TEXT NOT NULL, is_top TINYINT NOT NULL DEFAULT 0, is_recommended TINYINT NOT NULL DEFAULT 0, is_headline TINYINT NOT NULL DEFAULT 0, body_format VARCHAR(16) NOT NULL DEFAULT 'plain', published_at BIGINT NOT NULL DEFAULT 0, sort_order INT NOT NULL DEFAULT 255, FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                'CREATE TABLE IF NOT EXISTS post_field_values (post_id BIGINT UNSIGNED NOT NULL, field_id BIGINT UNSIGNED NOT NULL, value TEXT NOT NULL, PRIMARY KEY (post_id, field_id), FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE, FOREIGN KEY (field_id) REFERENCES model_fields(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                'CREATE TABLE IF NOT EXISTS category_field_values (category_id BIGINT UNSIGNED NOT NULL, field_id BIGINT UNSIGNED NOT NULL, value TEXT NOT NULL, PRIMARY KEY (category_id, field_id), FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE, FOREIGN KEY (field_id) REFERENCES model_fields(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                "CREATE TABLE IF NOT EXISTS slides (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, group_id INT NOT NULL DEFAULT 1, media_id BIGINT UNSIGNED NOT NULL, title VARCHAR(150) NOT NULL DEFAULT '', subtitle VARCHAR(255) NOT NULL DEFAULT '', link VARCHAR(2048) NOT NULL DEFAULT '', sort_order INT NOT NULL DEFAULT 255, FOREIGN KEY (media_id) REFERENCES media(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS friend_links (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, group_id INT NOT NULL DEFAULT 1, name VARCHAR(150) NOT NULL, link VARCHAR(2048) NOT NULL, media_id BIGINT UNSIGNED NULL, sort_order INT NOT NULL DEFAULT 255, FOREIGN KEY (media_id) REFERENCES media(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS custom_labels (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64) NOT NULL UNIQUE, description VARCHAR(150) NOT NULL DEFAULT '', value TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS company_profile (id TINYINT UNSIGNED PRIMARY KEY, name VARCHAR(150) NOT NULL DEFAULT '', address VARCHAR(255) NOT NULL DEFAULT '', postcode VARCHAR(30) NOT NULL DEFAULT '', contact VARCHAR(100) NOT NULL DEFAULT '', mobile VARCHAR(50) NOT NULL DEFAULT '', phone VARCHAR(50) NOT NULL DEFAULT '', fax VARCHAR(50) NOT NULL DEFAULT '', email VARCHAR(191) NOT NULL DEFAULT '', qq VARCHAR(50) NOT NULL DEFAULT '', weixin VARCHAR(255) NOT NULL DEFAULT '', blicense VARCHAR(255) NOT NULL DEFAULT '', other TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
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
            "CREATE TABLE IF NOT EXISTS pages (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, body TEXT NOT NULL, body_format TEXT NOT NULL DEFAULT 'plain', status TEXT NOT NULL, menu_order INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)",
            'CREATE TABLE IF NOT EXISTS site_settings (name TEXT PRIMARY KEY, value TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS media (id INTEGER PRIMARY KEY AUTOINCREMENT, path TEXT NOT NULL UNIQUE, original_name TEXT NOT NULL, mime_type TEXT NOT NULL, bytes INTEGER NOT NULL, created_at INTEGER NOT NULL)',
            'CREATE TABLE IF NOT EXISTS post_cover (post_id INTEGER PRIMARY KEY REFERENCES posts(id) ON DELETE CASCADE, media_id INTEGER NOT NULL REFERENCES media(id))',
            "CREATE TABLE IF NOT EXISTS post_gallery (post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE, position INTEGER NOT NULL, media_id INTEGER NOT NULL REFERENCES media(id), title TEXT NOT NULL DEFAULT '', PRIMARY KEY (post_id, position))",
            'CREATE TABLE IF NOT EXISTS post_attachment (post_id INTEGER PRIMARY KEY REFERENCES posts(id) ON DELETE CASCADE, path TEXT NOT NULL, original_name TEXT NOT NULL, bytes INTEGER NOT NULL)',
            'CREATE TABLE IF NOT EXISTS category_cover (category_id INTEGER PRIMARY KEY REFERENCES categories(id) ON DELETE CASCADE, media_id INTEGER NOT NULL REFERENCES media(id))',
            "CREATE TABLE IF NOT EXISTS category_gallery (category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE, position INTEGER NOT NULL, media_id INTEGER NOT NULL REFERENCES media(id), title TEXT NOT NULL DEFAULT '', PRIMARY KEY (category_id, position))",
            'CREATE TABLE IF NOT EXISTS category_attachment (category_id INTEGER PRIMARY KEY REFERENCES categories(id) ON DELETE CASCADE, path TEXT NOT NULL, original_name TEXT NOT NULL, bytes INTEGER NOT NULL)',
            'CREATE TABLE IF NOT EXISTS page_cover (page_id INTEGER PRIMARY KEY REFERENCES pages(id) ON DELETE CASCADE, media_id INTEGER NOT NULL REFERENCES media(id))',
            "CREATE TABLE IF NOT EXISTS page_gallery (page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE, position INTEGER NOT NULL, media_id INTEGER NOT NULL REFERENCES media(id), title TEXT NOT NULL DEFAULT '', PRIMARY KEY (page_id, position))",
            'CREATE TABLE IF NOT EXISTS page_attachment (page_id INTEGER PRIMARY KEY REFERENCES pages(id) ON DELETE CASCADE, path TEXT NOT NULL, original_name TEXT NOT NULL, bytes INTEGER NOT NULL)',
            "CREATE TABLE IF NOT EXISTS category_images (category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE, kind TEXT NOT NULL CHECK (kind IN ('thumbnail', 'banner')), media_id INTEGER NOT NULL REFERENCES media(id), PRIMARY KEY (category_id, kind))",
            'CREATE TABLE IF NOT EXISTS content_models (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, name TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS model_fields (id INTEGER PRIMARY KEY AUTOINCREMENT, model_id INTEGER NOT NULL REFERENCES content_models(id) ON DELETE CASCADE, name TEXT NOT NULL, label TEXT NOT NULL, field_type TEXT NOT NULL, options_json TEXT NOT NULL, is_required INTEGER NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 0, UNIQUE (model_id, name))',
            'CREATE TABLE IF NOT EXISTS category_models (category_id INTEGER PRIMARY KEY REFERENCES categories(id) ON DELETE CASCADE, model_id INTEGER NOT NULL REFERENCES content_models(id))',
            "CREATE TABLE IF NOT EXISTS category_settings (category_id INTEGER PRIMARY KEY REFERENCES categories(id) ON DELETE CASCADE, type TEXT NOT NULL DEFAULT 'list', outlink TEXT NOT NULL DEFAULT '', seo_title TEXT NOT NULL DEFAULT '', seo_keywords TEXT NOT NULL DEFAULT '', seo_description TEXT NOT NULL DEFAULT '', single_title TEXT NOT NULL DEFAULT '', single_body TEXT NOT NULL DEFAULT '', single_body_format TEXT NOT NULL DEFAULT 'plain', single_status TEXT NOT NULL DEFAULT 'draft')",
            "CREATE TABLE IF NOT EXISTS post_meta (post_id INTEGER PRIMARY KEY REFERENCES posts(id) ON DELETE CASCADE, subtitle TEXT NOT NULL DEFAULT '', author TEXT NOT NULL DEFAULT '', source TEXT NOT NULL DEFAULT '', title_color TEXT NOT NULL DEFAULT '', outlink TEXT NOT NULL DEFAULT '', seo_keywords TEXT NOT NULL DEFAULT '', seo_description TEXT NOT NULL DEFAULT '', is_top INTEGER NOT NULL DEFAULT 0, is_recommended INTEGER NOT NULL DEFAULT 0, is_headline INTEGER NOT NULL DEFAULT 0, body_format TEXT NOT NULL DEFAULT 'plain', published_at INTEGER NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 255)",
            'CREATE TABLE IF NOT EXISTS post_field_values (post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE, field_id INTEGER NOT NULL REFERENCES model_fields(id) ON DELETE CASCADE, value TEXT NOT NULL, PRIMARY KEY (post_id, field_id))',
            'CREATE TABLE IF NOT EXISTS category_field_values (category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE, field_id INTEGER NOT NULL REFERENCES model_fields(id) ON DELETE CASCADE, value TEXT NOT NULL, PRIMARY KEY (category_id, field_id))',
            "CREATE TABLE IF NOT EXISTS slides (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL DEFAULT 1, media_id INTEGER NOT NULL REFERENCES media(id), title TEXT NOT NULL DEFAULT '', subtitle TEXT NOT NULL DEFAULT '', link TEXT NOT NULL DEFAULT '', sort_order INTEGER NOT NULL DEFAULT 255)",
            "CREATE TABLE IF NOT EXISTS friend_links (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL DEFAULT 1, name TEXT NOT NULL, link TEXT NOT NULL, media_id INTEGER NULL REFERENCES media(id), sort_order INTEGER NOT NULL DEFAULT 255)",
            "CREATE TABLE IF NOT EXISTS custom_labels (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, description TEXT NOT NULL DEFAULT '', value TEXT NOT NULL)",
            "CREATE TABLE IF NOT EXISTS company_profile (id INTEGER PRIMARY KEY, name TEXT NOT NULL DEFAULT '', address TEXT NOT NULL DEFAULT '', postcode TEXT NOT NULL DEFAULT '', contact TEXT NOT NULL DEFAULT '', mobile TEXT NOT NULL DEFAULT '', phone TEXT NOT NULL DEFAULT '', fax TEXT NOT NULL DEFAULT '', email TEXT NOT NULL DEFAULT '', qq TEXT NOT NULL DEFAULT '', weixin TEXT NOT NULL DEFAULT '', blicense TEXT NOT NULL DEFAULT '', other TEXT NOT NULL DEFAULT '')"
        );
    }
}

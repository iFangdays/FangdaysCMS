<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;
use PDO;

final class PageRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT id, title, slug, body, status, menu_order, created_at, updated_at FROM pages ORDER BY menu_order ASC, id ASC')->fetchAll();
    }

    public function published(): array
    {
        return $this->pdo->query("SELECT id, title, slug, menu_order FROM pages WHERE status = 'published' ORDER BY menu_order ASC, id ASC")->fetchAll();
    }

    public function byId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, title, slug, body, status, menu_order, created_at, updated_at FROM pages WHERE id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function publishedBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, title, slug, body, status, menu_order, created_at, updated_at FROM pages WHERE slug = ? AND status = 'published'");
        $stmt->execute(array($slug));
        return $stmt->fetch() ?: null;
    }

    public function save(?int $id, string $title, string $slug, string $body, string $status, int $menuOrder): int
    {
        $title = trim($title);
        $slug = trim($slug);
        if ($title === '' || mb_strlen($title) > 100 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 160 || strlen($body) > 2000000 || ! in_array($status, array('draft', 'published'), true) || $menuOrder < 0 || $menuOrder > 1000000) {
            throw new InvalidArgumentException('单页标题、别名、正文、状态或排序无效。');
        }
        $now = time();
        if ($id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO pages (title, slug, body, status, menu_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute(array($title, $slug, $body, $status, $menuOrder, $now, $now));
            return (int) $this->pdo->lastInsertId();
        }
        $stmt = $this->pdo->prepare('UPDATE pages SET title = ?, slug = ?, body = ?, status = ?, menu_order = ?, updated_at = ? WHERE id = ?');
        $stmt->execute(array($title, $slug, $body, $status, $menuOrder, $now, $id));
        return $id;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM pages WHERE id = ?');
        $stmt->execute(array($id));
    }
}

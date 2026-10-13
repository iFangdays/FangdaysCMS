<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;
use PDO;

final class TagRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT id, name, slug FROM tags ORDER BY name, id')->fetchAll();
    }

    public function byId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug FROM tags WHERE id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function bySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug FROM tags WHERE slug = ?');
        $stmt->execute(array($slug));
        return $stmt->fetch() ?: null;
    }

    public function save(?int $id, string $name, string $slug): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 160) {
            throw new InvalidArgumentException('标签名称或别名无效。');
        }
        if ($id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
            $stmt->execute(array($name, $slug));
            return (int) $this->pdo->lastInsertId();
        }
        $stmt = $this->pdo->prepare('UPDATE tags SET name = ?, slug = ? WHERE id = ?');
        $stmt->execute(array($name, $slug, $id));
        return $id;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tags WHERE id = ?');
        $stmt->execute(array($id));
    }

    public function forPost(int $postId): array
    {
        $stmt = $this->pdo->prepare('SELECT t.id, t.name, t.slug FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name');
        $stmt->execute(array($postId));
        return $stmt->fetchAll();
    }

    public function setForPost(int $postId, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) > 30) {
            throw new InvalidArgumentException('一篇文章最多使用 30 个标签。');
        }
        foreach ($ids as $id) {
            if ($id < 1 || $this->byId($id) === null) {
                throw new InvalidArgumentException('所选标签不存在。');
            }
        }
        $delete = $this->pdo->prepare('DELETE FROM post_tags WHERE post_id = ?');
        $delete->execute(array($postId));
        $insert = $this->pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)');
        foreach ($ids as $id) {
            $insert->execute(array($postId, $id));
        }
    }
}

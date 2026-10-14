<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;
use PDO;

final class ContentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function categories(): array
    {
        return $this->pdo->query('SELECT c.id, c.name, c.slug, c.sort_order, cm.model_id FROM categories c LEFT JOIN category_models cm ON cm.category_id = c.id ORDER BY c.sort_order ASC, c.id ASC')->fetchAll();
    }

    public function categoriesWithPostCounts(): array
    {
        return $this->pdo->query('SELECT c.id, c.name, c.slug, c.sort_order, cm.model_id, m.name AS model_name, COUNT(p.id) AS post_count FROM categories c LEFT JOIN category_models cm ON cm.category_id = c.id LEFT JOIN content_models m ON m.id = cm.model_id LEFT JOIN posts p ON p.category_id = c.id GROUP BY c.id, c.name, c.slug, c.sort_order, cm.model_id, m.name ORDER BY c.sort_order ASC, c.id ASC')->fetchAll();
    }

    public function category(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT c.id, c.name, c.slug, c.sort_order, cm.model_id FROM categories c LEFT JOIN category_models cm ON cm.category_id = c.id WHERE c.id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function categoryBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT c.id, c.name, c.slug, c.sort_order, cm.model_id FROM categories c LEFT JOIN category_models cm ON cm.category_id = c.id WHERE c.slug = ?');
        $stmt->execute(array($slug));
        return $stmt->fetch() ?: null;
    }

    public function saveCategory(?int $id, string $name, string $slug, int $sortOrder): int
    {
        $name = trim($name);
        self::checkSlug($slug);
        if ($name === '' || strlen($name) > 300 || $sortOrder < 0 || $sortOrder > 1000000) {
            throw new InvalidArgumentException('Category name or order is invalid.');
        }
        if ($id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, ?)');
            $stmt->execute(array($name, $slug, $sortOrder));
            return (int) $this->pdo->lastInsertId();
        }
        $stmt = $this->pdo->prepare('UPDATE categories SET name = ?, slug = ?, sort_order = ? WHERE id = ?');
        $stmt->execute(array($name, $slug, $sortOrder, $id));
        return $id;
    }

    public function deleteCategory(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute(array($id));
    }

    public function posts(?string $status = null, ?int $categoryId = null, bool $includeMembers = false, ?int $tagId = null, ?string $query = null, int $page = 1): array
    {
        [$where, $params] = self::postFilters($status, $categoryId, $includeMembers, $tagId, $query);
        $sql = "SELECT p.id, p.category_id, p.title, p.slug, p.body, p.status, p.created_at, p.updated_at, c.name AS category_name, COALESCE(v.visibility, 'public') AS visibility, m.path AS cover_path FROM posts p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN post_visibility v ON v.post_id = p.id LEFT JOIN post_cover pc ON pc.post_id = p.id LEFT JOIN media m ON m.id = pc.media_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $index => $param) {
            $stmt->bindValue($index + 1, $param);
        }
        $stmt->bindValue(count($params) + 1, 20, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, (max(1, $page) - 1) * 20, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countPosts(?string $status = null, ?int $categoryId = null, bool $includeMembers = false, ?int $tagId = null, ?string $query = null): int
    {
        [$where, $params] = self::postFilters($status, $categoryId, $includeMembers, $tagId, $query);
        $sql = 'SELECT COUNT(*) FROM posts p LEFT JOIN post_visibility v ON v.post_id = p.id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function post(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT p.id, p.category_id, p.title, p.slug, p.body, p.status, p.created_at, p.updated_at, COALESCE(v.visibility, 'public') AS visibility, pc.media_id AS cover_media_id FROM posts p LEFT JOIN post_visibility v ON v.post_id = p.id LEFT JOIN post_cover pc ON pc.post_id = p.id WHERE p.id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function publishedPostBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT p.id, p.category_id, p.title, p.slug, p.body, p.status, p.created_at, p.updated_at, c.name AS category_name, c.slug AS category_slug, COALESCE(v.visibility, 'public') AS visibility, m.path AS cover_path FROM posts p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN post_visibility v ON v.post_id = p.id LEFT JOIN post_cover pc ON pc.post_id = p.id LEFT JOIN media m ON m.id = pc.media_id WHERE p.slug = ? AND p.status = 'published'");
        $stmt->execute(array($slug));
        return $stmt->fetch() ?: null;
    }

    public function savePost(?int $id, ?int $categoryId, string $title, string $slug, string $body, string $status): int
    {
        $title = trim($title);
        self::checkSlug($slug);
        if ($title === '' || strlen($title) > 300 || strlen($body) > 2000000 || ! in_array($status, array('draft', 'published'), true)) {
            throw new InvalidArgumentException('Post title, body, or status is invalid.');
        }
        if ($categoryId !== null && $this->category($categoryId) === null) {
            throw new InvalidArgumentException('Category does not exist.');
        }
        $now = time();
        if ($id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO posts (category_id, title, slug, body, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute(array($categoryId, $title, $slug, $body, $status, $now, $now));
            return (int) $this->pdo->lastInsertId();
        }
        $stmt = $this->pdo->prepare('UPDATE posts SET category_id = ?, title = ?, slug = ?, body = ?, status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute(array($categoryId, $title, $slug, $body, $status, $now, $id));
        return $id;
    }

    public function deletePost(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM posts WHERE id = ?');
        $stmt->execute(array($id));
    }

    public function bulkPosts(array $ids, string $action): int
    {
        if (! in_array($action, array('publish', 'draft', 'delete'), true) || count($ids) < 1 || count($ids) > 20) {
            throw new InvalidArgumentException('请选择本页文章，并指定有效操作。');
        }
        $validated = array();
        foreach ($ids as $id) {
            if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1) {
                throw new InvalidArgumentException('所选文章无效。');
            }
            $validated[] = (int) $id;
        }
        $validated = array_values(array_unique($validated));
        $placeholders = implode(', ', array_fill(0, count($validated), '?'));
        if ($action === 'delete') {
            $stmt = $this->pdo->prepare('DELETE FROM posts WHERE id IN (' . $placeholders . ')');
            $stmt->execute($validated);
            return $stmt->rowCount();
        }
        $status = $action === 'publish' ? 'published' : 'draft';
        $stmt = $this->pdo->prepare('UPDATE posts SET status = ?, updated_at = ? WHERE id IN (' . $placeholders . ')');
        $stmt->execute(array_merge(array($status, time()), $validated));
        return $stmt->rowCount();
    }

    public function setVisibility(int $postId, string $visibility): void
    {
        if (! in_array($visibility, array('public', 'members'), true)) {
            throw new InvalidArgumentException('文章可见范围无效。');
        }
        $stmt = $this->pdo->prepare('DELETE FROM post_visibility WHERE post_id = ?');
        $stmt->execute(array($postId));
        if ($visibility === 'members') {
            $stmt = $this->pdo->prepare('INSERT INTO post_visibility (post_id, visibility) VALUES (?, ?)');
            $stmt->execute(array($postId, $visibility));
        }
    }

    private static function checkSlug(string $slug): void
    {
        if (strlen($slug) > 160 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw new InvalidArgumentException('Slug must use lowercase letters, digits, and single hyphens.');
        }
    }

    private static function postFilters(?string $status, ?int $categoryId, bool $includeMembers, ?int $tagId, ?string $query): array
    {
        $where = array();
        $params = array();
        if ($status !== null) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($categoryId !== null) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        if ($status === 'published' && ! $includeMembers) {
            $where[] = "COALESCE(v.visibility, 'public') = 'public'";
        }
        if ($tagId !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM post_tags pt WHERE pt.post_id = p.id AND pt.tag_id = ?)';
            $params[] = $tagId;
        }
        if ($query !== null && trim($query) !== '') {
            $where[] = "(p.title LIKE ? OR p.body LIKE ? OR EXISTS (SELECT 1 FROM post_field_values pf JOIN posts active_post ON active_post.id = pf.post_id JOIN model_fields mf ON mf.id = pf.field_id LEFT JOIN category_models cm ON cm.category_id = active_post.category_id WHERE pf.post_id = p.id AND mf.model_id = COALESCE(cm.model_id, (SELECT id FROM content_models WHERE slug = 'article')) AND pf.value LIKE ?))";
            $params[] = '%' . trim($query) . '%';
            $params[] = '%' . trim($query) . '%';
            $params[] = '%' . trim($query) . '%';
        }
        return array($where, $params);
    }
}

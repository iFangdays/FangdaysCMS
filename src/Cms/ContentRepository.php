<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;
use PDO;

final class ContentRepository
{
    private const POST_META_SELECT = "COALESCE(pm.subtitle, '') AS subtitle, COALESCE(pm.author, '') AS author, COALESCE(pm.source, '') AS source, COALESCE(pm.title_color, '') AS title_color, COALESCE(pm.outlink, '') AS outlink, COALESCE(pm.seo_keywords, '') AS seo_keywords, COALESCE(pm.seo_description, '') AS seo_description, COALESCE(pm.is_top, 0) AS is_top, COALESCE(pm.is_recommended, 0) AS is_recommended, COALESCE(pm.is_headline, 0) AS is_headline, COALESCE(pm.body_format, 'plain') AS body_format";

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function categories(): array
    {
        $rows = $this->pdo->query("SELECT c.id, c.parent_id, c.name, c.slug, c.sort_order, cm.model_id, COALESCE(s.type, 'list') AS type, COALESCE(s.outlink, '') AS outlink, COALESCE(s.single_status, 'draft') AS single_status FROM categories c LEFT JOIN category_models cm ON cm.category_id = c.id LEFT JOIN category_settings s ON s.category_id = c.id ORDER BY c.sort_order ASC, c.id ASC")->fetchAll();
        return self::treeRows($rows);
    }

    public function categoriesWithPostCounts(): array
    {
        $rows = $this->pdo->query("SELECT c.id, c.parent_id, c.name, c.slug, c.sort_order, cm.model_id, m.name AS model_name, COALESCE(s.type, 'list') AS type, COALESCE(s.outlink, '') AS outlink, COALESCE(s.single_status, 'draft') AS single_status, COUNT(p.id) AS post_count FROM categories c LEFT JOIN category_models cm ON cm.category_id = c.id LEFT JOIN category_settings s ON s.category_id = c.id LEFT JOIN content_models m ON m.id = cm.model_id LEFT JOIN posts p ON p.category_id = c.id GROUP BY c.id, c.parent_id, c.name, c.slug, c.sort_order, cm.model_id, m.name, s.type, s.outlink, s.single_status ORDER BY c.sort_order ASC, c.id ASC")->fetchAll();
        $rows = self::treeRows($rows);
        $counts = array();
        foreach ($rows as $row) {
            $counts[(int) $row['id']] = (int) $row['post_count'];
        }
        foreach (array_reverse($rows) as $row) {
            if ($row['parent_id'] !== null) {
                $counts[(int) $row['parent_id']] = ($counts[(int) $row['parent_id']] ?? 0) + $counts[(int) $row['id']];
            }
        }
        foreach ($rows as &$row) {
            $row['post_count'] = $counts[(int) $row['id']];
        }
        return $rows;
    }

    public function rootCategories(): array
    {
        return array_values(array_filter($this->categories(), static fn (array $category): bool => $category['parent_id'] === null && self::isPublicCategory($category)));
    }

    public function childCategories(int $parentId): array
    {
        return array_values(array_filter($this->categories(), static fn (array $category): bool => (int) $category['parent_id'] === $parentId && self::isPublicCategory($category)));
    }

    public function parentChoices(?int $categoryId): array
    {
        $excluded = $categoryId === null ? array() : array_flip($this->categoryDescendantIds($categoryId));
        return array_values(array_filter($this->categories(), static fn (array $category): bool => ! isset($excluded[(int) $category['id']])));
    }

    public function categoryAncestors(int $categoryId): array
    {
        $lookup = array_column($this->categories(), null, 'id');
        $ancestors = array();
        $seen = array();
        $current = $categoryId;
        while (isset($lookup[$current]) && ! isset($seen[$current])) {
            $seen[$current] = true;
            array_unshift($ancestors, $lookup[$current]);
            $current = (int) ($lookup[$current]['parent_id'] ?? 0);
        }
        return $ancestors;
    }

    public function categoryDescendantIds(int $categoryId): array
    {
        $children = array();
        foreach ($this->categories() as $category) {
            $children[(int) ($category['parent_id'] ?? 0)][] = (int) $category['id'];
        }
        $ids = array();
        $queue = array($categoryId);
        while ($queue) {
            $current = array_shift($queue);
            if (isset($ids[$current])) {
                continue;
            }
            $ids[$current] = true;
            foreach ($children[$current] ?? array() as $childId) {
                $queue[] = $childId;
            }
        }
        return array_keys($ids);
    }

    public function category(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT c.id, c.parent_id, c.name, c.slug, c.sort_order, cm.model_id, COALESCE(s.type, 'list') AS type, COALESCE(s.outlink, '') AS outlink, COALESCE(s.seo_title, '') AS seo_title, COALESCE(s.seo_keywords, '') AS seo_keywords, COALESCE(s.seo_description, '') AS seo_description, COALESCE(s.single_title, '') AS single_title, COALESCE(s.single_body, '') AS single_body, COALESCE(s.single_status, 'draft') AS single_status FROM categories c LEFT JOIN category_models cm ON cm.category_id = c.id LEFT JOIN category_settings s ON s.category_id = c.id WHERE c.id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function categoryBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT c.id, c.parent_id, c.name, c.slug, c.sort_order, cm.model_id, COALESCE(s.type, 'list') AS type, COALESCE(s.outlink, '') AS outlink, COALESCE(s.seo_title, '') AS seo_title, COALESCE(s.seo_keywords, '') AS seo_keywords, COALESCE(s.seo_description, '') AS seo_description, COALESCE(s.single_title, '') AS single_title, COALESCE(s.single_body, '') AS single_body, COALESCE(s.single_status, 'draft') AS single_status FROM categories c LEFT JOIN category_models cm ON cm.category_id = c.id LEFT JOIN category_settings s ON s.category_id = c.id WHERE c.slug = ?");
        $stmt->execute(array($slug));
        return $stmt->fetch() ?: null;
    }

    public function saveCategorySettings(int $categoryId, string $type, string $outlink, string $seoTitle, string $seoKeywords, string $seoDescription): void
    {
        $outlink = trim($outlink);
        $seoTitle = trim($seoTitle);
        $seoKeywords = trim($seoKeywords);
        $seoDescription = trim($seoDescription);
        if (! in_array($type, array('list', 'single', 'link'), true)
            || mb_strlen($seoTitle) > 255 || mb_strlen($seoKeywords) > 255 || mb_strlen($seoDescription) > 500) {
            throw new InvalidArgumentException('栏目类型或 SEO 信息无效。');
        }
        if ($type !== 'list') {
            $count = $this->pdo->prepare('SELECT COUNT(*) FROM posts WHERE category_id = ?');
            $count->execute(array($categoryId));
            if ((int) $count->fetchColumn() > 0) {
                throw new InvalidArgumentException('该栏目下已有文章，请先将直属文章移到列表栏目。');
            }
        }
        if ($type === 'link') {
            if (! self::validRedirectTarget($outlink)) {
                throw new InvalidArgumentException('跳转链接须为 http(s) 地址或站内绝对路径。');
            }
            $category = $this->category($categoryId);
            if ($category !== null && $outlink === '/category/' . $category['slug']) {
                throw new InvalidArgumentException('跳转链接不能指向栏目自身。');
            }
        } else {
            $outlink = '';
        }
        $exists = $this->pdo->prepare('SELECT category_id FROM category_settings WHERE category_id = ?');
        $exists->execute(array($categoryId));
        if ($exists->fetchColumn() === false) {
            $stmt = $this->pdo->prepare('INSERT INTO category_settings (category_id, type, outlink, seo_title, seo_keywords, seo_description, single_title, single_body, single_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute(array($categoryId, $type, $outlink, $seoTitle, $seoKeywords, $seoDescription, '', '', 'draft'));
        } else {
            $stmt = $this->pdo->prepare('UPDATE category_settings SET type = ?, outlink = ?, seo_title = ?, seo_keywords = ?, seo_description = ? WHERE category_id = ?');
            $stmt->execute(array($type, $outlink, $seoTitle, $seoKeywords, $seoDescription, $categoryId));
        }
    }

    public function saveSingle(int $categoryId, string $title, string $body, string $status): void
    {
        $category = $this->category($categoryId);
        if ($category === null || $category['type'] !== 'single') {
            throw new InvalidArgumentException('请选择单页类型栏目。');
        }
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 100 || strlen($body) > 2000000 || ! in_array($status, array('draft', 'published'), true)) {
            throw new InvalidArgumentException('单页标题、正文或状态无效。');
        }
        $stmt = $this->pdo->prepare('UPDATE category_settings SET single_title = ?, single_body = ?, single_status = ? WHERE category_id = ?');
        $stmt->execute(array($title, $body, $status, $categoryId));
    }

    private static function isPublicCategory(array $category): bool
    {
        return $category['type'] !== 'single' || $category['single_status'] === 'published';
    }

    public function saveCategory(?int $id, string $name, string $slug, int $sortOrder, ?int $parentId = null): int
    {
        $name = trim($name);
        self::checkSlug($slug);
        if ($name === '' || strlen($name) > 300 || $sortOrder < 0 || $sortOrder > 1000000) {
            throw new InvalidArgumentException('Category name or order is invalid.');
        }
        if ($id !== null && $this->category($id) === null) {
            throw new InvalidArgumentException('栏目不存在。');
        }
        if ($parentId !== null && ($this->category($parentId) === null || ($id !== null && in_array($parentId, $this->categoryDescendantIds($id), true)))) {
            throw new InvalidArgumentException('上级栏目不存在，或会形成循环层级。');
        }
        if ($id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO categories (parent_id, name, slug, sort_order) VALUES (?, ?, ?, ?)');
            $stmt->execute(array($parentId, $name, $slug, $sortOrder));
            return (int) $this->pdo->lastInsertId();
        }
        $stmt = $this->pdo->prepare('UPDATE categories SET parent_id = ?, name = ?, slug = ?, sort_order = ? WHERE id = ?');
        $stmt->execute(array($parentId, $name, $slug, $sortOrder, $id));
        return $id;
    }

    public function deleteCategory(int $id): void
    {
        if (count($this->categoryDescendantIds($id)) > 1) {
            throw new InvalidArgumentException('该栏目还有子栏目，请先调整子栏目归属。');
        }
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute(array($id));
    }

    private static function treeRows(array $rows): array
    {
        $children = array();
        foreach ($rows as $row) {
            $children[(int) ($row['parent_id'] ?? 0)][] = $row;
        }
        $result = array();
        $seen = array();
        $walk = static function (int $parentId, int $depth) use (&$walk, &$result, &$seen, $children): void {
            foreach ($children[$parentId] ?? array() as $row) {
                $id = (int) $row['id'];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $row['depth'] = $depth;
                $result[] = $row;
                $walk($id, $depth + 1);
            }
        };
        $walk(0, 0);
        foreach ($rows as $row) {
            if (! isset($seen[(int) $row['id']])) {
                $walk((int) $row['parent_id'], 0);
            }
        }
        return $result;
    }

    public function posts(?string $status = null, ?int $categoryId = null, bool $includeMembers = false, ?int $tagId = null, ?string $query = null, int $page = 1, ?string $feature = null): array
    {
        [$where, $params] = $this->postFilters($status, $categoryId, $includeMembers, $tagId, $query, $feature);
        $sql = "SELECT p.id, p.category_id, p.title, p.slug, p.body, p.status, p.created_at, p.updated_at, c.name AS category_name, COALESCE(v.visibility, 'public') AS visibility, m.path AS cover_path, " . self::POST_META_SELECT . " FROM posts p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN post_visibility v ON v.post_id = p.id LEFT JOIN post_cover pc ON pc.post_id = p.id LEFT JOIN media m ON m.id = pc.media_id LEFT JOIN post_meta pm ON pm.post_id = p.id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY COALESCE(pm.is_top, 0) DESC, COALESCE(pm.is_recommended, 0) DESC, COALESCE(pm.is_headline, 0) DESC, p.created_at DESC, p.id DESC LIMIT ? OFFSET ?';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $index => $param) {
            $stmt->bindValue($index + 1, $param);
        }
        $stmt->bindValue(count($params) + 1, 20, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, (max(1, $page) - 1) * 20, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countPosts(?string $status = null, ?int $categoryId = null, bool $includeMembers = false, ?int $tagId = null, ?string $query = null, ?string $feature = null): int
    {
        [$where, $params] = $this->postFilters($status, $categoryId, $includeMembers, $tagId, $query, $feature);
        $sql = 'SELECT COUNT(*) FROM posts p LEFT JOIN post_visibility v ON v.post_id = p.id LEFT JOIN post_meta pm ON pm.post_id = p.id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function post(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT p.id, p.category_id, p.title, p.slug, p.body, p.status, p.created_at, p.updated_at, COALESCE(v.visibility, 'public') AS visibility, pc.media_id AS cover_media_id, " . self::POST_META_SELECT . " FROM posts p LEFT JOIN post_visibility v ON v.post_id = p.id LEFT JOIN post_cover pc ON pc.post_id = p.id LEFT JOIN post_meta pm ON pm.post_id = p.id WHERE p.id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function publishedPostBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT p.id, p.category_id, p.title, p.slug, p.body, p.status, p.created_at, p.updated_at, c.name AS category_name, c.slug AS category_slug, COALESCE(v.visibility, 'public') AS visibility, m.path AS cover_path, " . self::POST_META_SELECT . " FROM posts p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN post_visibility v ON v.post_id = p.id LEFT JOIN post_cover pc ON pc.post_id = p.id LEFT JOIN media m ON m.id = pc.media_id LEFT JOIN post_meta pm ON pm.post_id = p.id WHERE p.slug = ? AND p.status = 'published'");
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
        if ($categoryId !== null) {
            $category = $this->category($categoryId);
            if ($category === null || $category['type'] !== 'list') {
                throw new InvalidArgumentException('请选择列表类型栏目。');
            }
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

    public function savePostMeta(int $postId, array $attributes): void
    {
        $post = $this->post($postId);
        if ($post === null) {
            throw new InvalidArgumentException('文章不存在。');
        }
        $values = array();
        foreach (array('subtitle' => 255, 'author' => 100, 'source' => 100, 'seo_keywords' => 255, 'seo_description' => 500) as $name => $limit) {
            $value = $attributes[$name] ?? '';
            if (! is_string($value) || mb_strlen(trim($value)) > $limit) {
                throw new InvalidArgumentException('文章附加信息长度无效。');
            }
            $values[$name] = trim($value);
        }
        $color = $attributes['title_color'] ?? '';
        if (! is_string($color) || ($color !== '' && ! preg_match('/^#[0-9a-fA-F]{6}$/D', $color))) {
            throw new InvalidArgumentException('标题颜色须为六位十六进制色值。');
        }
        $values['title_color'] = strtolower($color);
        $outlink = $attributes['outlink'] ?? '';
        if (! is_string($outlink)) {
            throw new InvalidArgumentException('文章跳转链接无效。');
        }
        $outlink = trim($outlink);
        if ($outlink !== '' && (! self::validRedirectTarget($outlink) || $outlink === '/post/' . $post['slug'])) {
            throw new InvalidArgumentException('文章跳转链接须为 http(s) 地址或站内绝对路径，且不能指向自身。');
        }
        $values['outlink'] = $outlink;
        $format = $attributes['body_format'] ?? $post['body_format'];
        if (! in_array($format, array('plain', 'html'), true)) {
            throw new InvalidArgumentException('正文格式无效。');
        }
        $values['body_format'] = $format;
        foreach (array('is_top', 'is_recommended', 'is_headline') as $name) {
            $flag = $attributes[$name] ?? '0';
            if (! in_array($flag, array('0', '1'), true)) {
                throw new InvalidArgumentException('文章推荐属性无效。');
            }
            $values[$name] = (int) $flag;
        }
        $exists = $this->pdo->prepare('SELECT post_id FROM post_meta WHERE post_id = ?');
        $exists->execute(array($postId));
        $columns = array('subtitle', 'author', 'source', 'title_color', 'outlink', 'seo_keywords', 'seo_description', 'is_top', 'is_recommended', 'is_headline', 'body_format');
        if ($exists->fetchColumn() === false) {
            $stmt = $this->pdo->prepare('INSERT INTO post_meta (post_id, ' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns) + 1, '?')) . ')');
            $stmt->execute(array_merge(array($postId), array_map(static fn (string $name) => $values[$name], $columns)));
        } else {
            $assignments = implode(', ', array_map(static fn (string $name): string => $name . ' = ?', $columns));
            $stmt = $this->pdo->prepare('UPDATE post_meta SET ' . $assignments . ' WHERE post_id = ?');
            $stmt->execute(array_merge(array_map(static fn (string $name) => $values[$name], $columns), array($postId)));
        }
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

    private static function validRedirectTarget(string $url): bool
    {
        if ($url === '' || strlen($url) > 2048) {
            return false;
        }
        $parts = parse_url($url);
        $absolute = $parts !== false && in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true)
            && ! empty($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
        $relative = (bool) preg_match('~^/(?!/)[^\x00-\x20\x7f\\\\]*$~D', $url);
        return $absolute || $relative;
    }

    private function postFilters(?string $status, ?int $categoryId, bool $includeMembers, ?int $tagId, ?string $query, ?string $feature): array
    {
        $where = array();
        $params = array();
        if ($status !== null) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($categoryId !== null) {
            $categoryIds = $this->categoryDescendantIds($categoryId);
            $where[] = 'p.category_id IN (' . implode(', ', array_fill(0, count($categoryIds), '?')) . ')';
            array_push($params, ...$categoryIds);
        }
        if ($status === 'published' && ! $includeMembers) {
            $where[] = "COALESCE(v.visibility, 'public') = 'public'";
        }
        if ($tagId !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM post_tags pt WHERE pt.post_id = p.id AND pt.tag_id = ?)';
            $params[] = $tagId;
        }
        if ($feature !== null) {
            $column = array('top' => 'is_top', 'recommended' => 'is_recommended', 'headline' => 'is_headline')[$feature] ?? null;
            if ($column === null) {
                throw new InvalidArgumentException('文章属性筛选无效。');
            }
            $where[] = 'COALESCE(pm.' . $column . ', 0) = 1';
        }
        if ($query !== null && trim($query) !== '') {
            $where[] = "(p.title LIKE ? OR p.body LIKE ? OR pm.subtitle LIKE ? OR pm.author LIKE ? OR pm.source LIKE ? OR pm.seo_keywords LIKE ? OR EXISTS (SELECT 1 FROM post_field_values pf JOIN posts active_post ON active_post.id = pf.post_id JOIN model_fields mf ON mf.id = pf.field_id LEFT JOIN category_models cm ON cm.category_id = active_post.category_id WHERE pf.post_id = p.id AND mf.model_id = COALESCE(cm.model_id, (SELECT id FROM content_models WHERE slug = 'article')) AND pf.value LIKE ?))";
            for ($i = 0; $i < 7; $i++) {
                $params[] = '%' . trim($query) . '%';
            }
        }
        return array($where, $params);
    }
}

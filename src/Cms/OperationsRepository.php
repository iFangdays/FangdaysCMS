<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;
use PDO;

final class OperationsRepository
{
    private const COMPANY_FIELDS = array('name', 'address', 'postcode', 'contact', 'mobile', 'phone', 'fax', 'email', 'qq', 'weixin', 'blicense', 'other');

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function slides(?int $groupId = null, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare('SELECT s.id, s.group_id, s.media_id, s.title, s.subtitle, s.link, s.sort_order, m.path AS image_path, m.original_name AS image_name FROM slides s JOIN media m ON m.id = s.media_id WHERE (? IS NULL OR s.group_id = ?) ORDER BY s.sort_order, s.id LIMIT ?');
        $stmt->bindValue(1, $groupId, $groupId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(2, $groupId, $groupId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(3, max(1, min($limit, 200)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function slide(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT s.id, s.group_id, s.media_id, s.title, s.subtitle, s.link, s.sort_order, m.path AS image_path FROM slides s JOIN media m ON m.id = s.media_id WHERE s.id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function saveSlide(?int $id, array $input): int
    {
        $group = self::number($input['group_id'] ?? '', '分组', 1, 1000000);
        $media = self::number($input['media_id'] ?? '', '轮播图片', 1, PHP_INT_MAX);
        $this->requireMedia($media);
        $title = self::shortText($input['title'] ?? '', '标题', 150);
        $subtitle = self::shortText($input['subtitle'] ?? '', '副标题', 255);
        $link = self::link($input['link'] ?? '', false);
        $order = self::number($input['sort_order'] ?? '', '排序', 0, 1000000);
        if ($id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO slides (group_id, media_id, title, subtitle, link, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute(array($group, $media, $title, $subtitle, $link, $order));
            return (int) $this->pdo->lastInsertId();
        }
        if ($this->slide($id) === null) {
            throw new InvalidArgumentException('轮播图不存在。');
        }
        $stmt = $this->pdo->prepare('UPDATE slides SET group_id = ?, media_id = ?, title = ?, subtitle = ?, link = ?, sort_order = ? WHERE id = ?');
        $stmt->execute(array($group, $media, $title, $subtitle, $link, $order, $id));
        return $id;
    }

    public function deleteSlide(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM slides WHERE id = ?');
        $stmt->execute(array($id));
    }

    public function links(?int $groupId = null, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare('SELECT l.id, l.group_id, l.name, l.link, l.media_id, l.sort_order, m.path AS logo_path FROM friend_links l LEFT JOIN media m ON m.id = l.media_id WHERE (? IS NULL OR l.group_id = ?) ORDER BY l.sort_order, l.id LIMIT ?');
        $stmt->bindValue(1, $groupId, $groupId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(2, $groupId, $groupId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(3, max(1, min($limit, 200)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function friendLink(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT l.id, l.group_id, l.name, l.link, l.media_id, l.sort_order, m.path AS logo_path FROM friend_links l LEFT JOIN media m ON m.id = l.media_id WHERE l.id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function saveLink(?int $id, array $input): int
    {
        $group = self::number($input['group_id'] ?? '', '分组', 1, 1000000);
        $name = self::shortText($input['name'] ?? '', '友链名称', 150, true);
        $link = self::link($input['link'] ?? '', true);
        if (! is_string($input['media_id'] ?? '')) {
            throw new InvalidArgumentException('Logo 图片无效。');
        }
        $media = trim($input['media_id'] ?? '');
        $mediaId = $media === '' ? null : self::number($media, 'Logo 图片', 1, PHP_INT_MAX);
        if ($mediaId !== null) {
            $this->requireMedia($mediaId);
        }
        $order = self::number($input['sort_order'] ?? '', '排序', 0, 1000000);
        if ($id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO friend_links (group_id, name, link, media_id, sort_order) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute(array($group, $name, $link, $mediaId, $order));
            return (int) $this->pdo->lastInsertId();
        }
        if ($this->friendLink($id) === null) {
            throw new InvalidArgumentException('友情链接不存在。');
        }
        $stmt = $this->pdo->prepare('UPDATE friend_links SET group_id = ?, name = ?, link = ?, media_id = ?, sort_order = ? WHERE id = ?');
        $stmt->execute(array($group, $name, $link, $mediaId, $order, $id));
        return $id;
    }

    public function deleteLink(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM friend_links WHERE id = ?');
        $stmt->execute(array($id));
    }

    public function labels(): array
    {
        return $this->pdo->query('SELECT id, name, description, value FROM custom_labels ORDER BY id DESC LIMIT 200')->fetchAll();
    }

    public function label(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, description, value FROM custom_labels WHERE id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function labelValues(): array
    {
        $values = array();
        foreach ($this->pdo->query('SELECT name, value FROM custom_labels') as $row) {
            $values[$row['name']] = $row['value'];
        }
        return $values;
    }

    public function saveLabel(?int $id, array $input): int
    {
        if (! is_string($input['name'] ?? '')) {
            throw new InvalidArgumentException('标签标识无效。');
        }
        $name = trim($input['name'] ?? '');
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $name) || strlen($name) > 64) {
            throw new InvalidArgumentException('标签标识须以字母开头，只能包含字母、数字和下划线，最多 64 个字符。');
        }
        $description = self::shortText($input['description'] ?? '', '说明', 150);
        if (! is_string($input['value'] ?? '')) {
            throw new InvalidArgumentException('标签内容无效。');
        }
        $value = trim($input['value'] ?? '');
        if (mb_strlen($value) > 2000) {
            throw new InvalidArgumentException('标签内容最多 2000 个字符。');
        }
        if ($id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO custom_labels (name, description, value) VALUES (?, ?, ?)');
            $stmt->execute(array($name, $description, $value));
            return (int) $this->pdo->lastInsertId();
        }
        if ($this->label($id) === null) {
            throw new InvalidArgumentException('自定义标签不存在。');
        }
        $stmt = $this->pdo->prepare('UPDATE custom_labels SET name = ?, description = ?, value = ? WHERE id = ?');
        $stmt->execute(array($name, $description, $value, $id));
        return $id;
    }

    public function deleteLabel(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM custom_labels WHERE id = ?');
        $stmt->execute(array($id));
    }

    public function company(): array
    {
        $row = $this->pdo->query('SELECT * FROM company_profile WHERE id = 1')->fetch() ?: array();
        $values = array_fill_keys(self::COMPANY_FIELDS, '');
        foreach (self::COMPANY_FIELDS as $field) {
            $values[$field] = (string) ($row[$field] ?? '');
        }
        return $values;
    }

    public function saveCompany(array $input): void
    {
        $values = array();
        $maxLengths = array('name' => 150, 'address' => 255, 'postcode' => 30, 'contact' => 100, 'mobile' => 50, 'phone' => 50, 'fax' => 50, 'email' => 191, 'qq' => 50, 'weixin' => 255, 'blicense' => 255, 'other' => 2000);
        foreach (self::COMPANY_FIELDS as $field) {
            $values[$field] = self::shortText($input[$field] ?? '', $field, $maxLengths[$field]);
        }
        if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('公司邮箱格式无效。');
        }
        $columns = implode(', ', self::COMPANY_FIELDS);
        $params = array_values($values);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM company_profile WHERE id = 1');
            $stmt = $this->pdo->prepare('INSERT INTO company_profile (id, ' . $columns . ') VALUES (' . implode(', ', array_fill(0, count($params) + 1, '?')) . ')');
            $stmt->execute(array_merge(array(1), $params));
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    private function requireMedia(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM media WHERE id = ?');
        $stmt->execute(array($id));
        if (! $stmt->fetchColumn()) {
            throw new InvalidArgumentException('所选图片不存在，请先到媒体图片上传。');
        }
    }

    private static function number(mixed $value, string $label, int $min, int $max): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => $min, 'max_range' => $max)));
        if ($number === false) {
            throw new InvalidArgumentException($label . '须为 ' . $min . ' 到 ' . $max . ' 的整数。');
        }
        return $number;
    }

    private static function shortText(mixed $value, string $label, int $max, bool $required = false): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException($label . '无效。');
        }
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value) > $max) {
            throw new InvalidArgumentException($label . '不能为空或超过 ' . $max . ' 个字符。');
        }
        return $value;
    }

    private static function link(mixed $value, bool $required): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('链接格式无效。');
        }
        $value = trim($value);
        if ($value === '' && ! $required) {
            return '';
        }
        $parts = parse_url($value);
        $absolute = $parts !== false && in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true)
            && ! empty($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
        $relative = (bool) preg_match('~^/(?!/)[^\x00-\x20\x7f\\\\]*$~D', $value);
        if (strlen($value) > 2048 || (! $absolute && ! $relative)) {
            throw new InvalidArgumentException('链接只支持 http(s) 地址或站内绝对路径。');
        }
        return $value;
    }
}

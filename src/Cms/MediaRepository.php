<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class MediaRepository
{
    private const TYPES = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif');

    public function __construct(private readonly PDO $pdo, private readonly string $publicRoot)
    {
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT id, path, original_name, mime_type, bytes, created_at FROM media ORDER BY id DESC LIMIT 200')->fetchAll();
    }

    public function byId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, path, original_name, mime_type, bytes, created_at FROM media WHERE id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function upload(array $file): int
    {
        if (! isset($file['error'], $file['tmp_name'], $file['size'], $file['name']) || $file['error'] !== UPLOAD_ERR_OK || ! is_string($file['tmp_name']) || ! is_string($file['name']) || ! is_int($file['size']) || $file['size'] < 1 || $file['size'] > 5 * 1024 * 1024 || ! is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('请选择小于 5 MB 的 JPG、PNG、WebP 或 GIF 图片。');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $dimensions = @getimagesize($file['tmp_name']);
        if (! is_string($mime) || ! isset(self::TYPES[$mime]) || $dimensions === false || $dimensions['mime'] !== $mime || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 10000 || $dimensions[1] > 10000) {
            throw new InvalidArgumentException('图片格式或尺寸无效。');
        }
        $relative = '/uploads/' . gmdate('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        $destination = $this->publicRoot . $relative;
        $directory = dirname($destination);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('无法创建媒体目录。');
        }
        if (! move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('无法保存图片。');
        }
        chmod($destination, 0644);
        try {
            $stmt = $this->pdo->prepare('INSERT INTO media (path, original_name, mime_type, bytes, created_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute(array($relative, mb_substr(basename($file['name']), 0, 150), $mime, $file['size'], time()));
            return (int) $this->pdo->lastInsertId();
        } catch (\Throwable $error) {
            unlink($destination);
            throw $error;
        }
    }

    public function setCover(int $postId, ?int $mediaId): void
    {
        if ($mediaId !== null && $this->byId($mediaId) === null) {
            throw new InvalidArgumentException('封面图片不存在。');
        }
        $stmt = $this->pdo->prepare('DELETE FROM post_cover WHERE post_id = ?');
        $stmt->execute(array($postId));
        if ($mediaId !== null) {
            $stmt = $this->pdo->prepare('INSERT INTO post_cover (post_id, media_id) VALUES (?, ?)');
            $stmt->execute(array($postId, $mediaId));
        }
    }

    public function galleryForPost(int $postId): array
    {
        $stmt = $this->pdo->prepare('SELECT g.media_id, g.title, m.path, m.original_name FROM post_gallery g JOIN media m ON m.id = g.media_id WHERE g.post_id = ? ORDER BY g.position');
        $stmt->execute(array($postId));
        return $stmt->fetchAll();
    }

    public function setGallery(int $postId, array $ids, array $titles): void
    {
        if (count($ids) > 30 || count($ids) !== count($titles)) {
            throw new InvalidArgumentException('多图最多 30 张，图片和标题必须一一对应。');
        }
        $items = array();
        foreach ($ids as $position => $id) {
            if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1 || isset($items[(int) $id]) || ! is_string($titles[$position]) || mb_strlen($titles[$position]) > 150 || $this->byId((int) $id) === null) {
                throw new InvalidArgumentException('多图包含无效、重复或不存在的图片。');
            }
            $items[(int) $id] = trim($titles[$position]);
        }
        $stmt = $this->pdo->prepare('DELETE FROM post_gallery WHERE post_id = ?');
        $stmt->execute(array($postId));
        $stmt = $this->pdo->prepare('INSERT INTO post_gallery (post_id, position, media_id, title) VALUES (?, ?, ?, ?)');
        $position = 0;
        foreach ($items as $id => $title) {
            $stmt->execute(array($postId, $position++, $id, $title));
        }
    }

    public function delete(int $id): void
    {
        $media = $this->byId($id);
        if ($media === null) {
            throw new InvalidArgumentException('图片不存在。');
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM post_cover WHERE media_id = ?');
        $stmt->execute(array($id));
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('图片正被文章封面使用。');
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM post_gallery WHERE media_id = ?');
        $stmt->execute(array($id));
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('图片正被文章多图使用。');
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM posts WHERE body LIKE ?');
        $stmt->execute(array('%' . $media['path'] . '%'));
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('图片正被文章正文使用。');
        }
        $stmt = $this->pdo->prepare('DELETE FROM media WHERE id = ?');
        $stmt->execute(array($id));
        $path = $this->publicRoot . $media['path'];
        if (preg_match('#^/uploads/[0-9]{4}/[0-9]{2}/[a-f0-9]{32}\.(jpg|png|webp|gif)$#D', $media['path']) && is_file($path)) {
            unlink($path);
        }
    }
}

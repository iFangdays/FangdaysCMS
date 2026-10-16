<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class AttachmentRepository
{
    private const EXTENSIONS = array('pdf', 'txt', 'zip', 'docx', 'xlsx', 'pptx');

    public function __construct(private readonly PDO $pdo, private readonly string $storageRoot)
    {
    }

    public function forPost(int $postId): ?array
    {
        return $this->forOwner('post_attachment', 'post_id', $postId);
    }

    public function forCategory(int $categoryId): ?array
    {
        return $this->forOwner('category_attachment', 'category_id', $categoryId);
    }

    private function forOwner(string $table, string $key, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT path, original_name, bytes FROM ' . $table . ' WHERE ' . $key . ' = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function validateUpload(array $file): void
    {
        if (! isset($file['error'], $file['tmp_name'], $file['size'], $file['name']) || $file['error'] !== UPLOAD_ERR_OK || ! is_string($file['tmp_name']) || ! is_string($file['name']) || ! is_int($file['size']) || $file['size'] < 1 || $file['size'] > 20 * 1024 * 1024 || ! is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('请选择小于 20 MB 的 PDF、TXT、ZIP 或 Office 附件。');
        }
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (! in_array($extension, self::EXTENSIONS, true) || ! is_string($mime)) {
            throw new InvalidArgumentException('附件格式无效。');
        }
        $expected = match ($extension) {
            'pdf' => array('application/pdf'),
            'txt' => array('text/plain'),
            'docx' => array('application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'xlsx' => array('application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'pptx' => array('application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'),
            default => array('application/zip', 'application/x-zip-compressed', 'application/octet-stream')
        };
        if (! in_array($mime, $expected, true)) {
            throw new InvalidArgumentException('附件内容与文件类型不符。');
        }
        if ($extension === 'pdf' && file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') {
            throw new InvalidArgumentException('PDF 文件无效。');
        }
        if (in_array($extension, array('zip', 'docx', 'xlsx', 'pptx'), true) && ! in_array(file_get_contents($file['tmp_name'], false, null, 0, 4), array("PK\x03\x04", "PK\x05\x06"), true)) {
            throw new InvalidArgumentException('压缩文件无效。');
        }
    }

    public function replace(int $postId, array $file): ?string
    {
        return $this->replaceForOwner('post_attachment', 'post_id', $postId, $file);
    }

    public function replaceForCategory(int $categoryId, array $file): ?string
    {
        return $this->replaceForOwner('category_attachment', 'category_id', $categoryId, $file);
    }

    private function replaceForOwner(string $table, string $key, int $id, array $file): ?string
    {
        $this->validateUpload($file);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $path = bin2hex(random_bytes(16)) . '.' . $extension;
        $directory = $this->storageRoot . '/attachments';
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('无法创建附件目录。');
        }
        $destination = $directory . '/' . $path;
        if (! move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('无法保存附件。');
        }
        chmod($destination, 0600);
        $old = $this->forOwner($table, $key, $id);
        try {
            $stmt = $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE ' . $key . ' = ?');
            $stmt->execute(array($id));
            $name = mb_substr(basename(str_replace('\\', '/', $file['name'])), 0, 150);
            $name = preg_replace('/[\x00-\x1f\x7f]/', '', $name) ?: 'attachment.' . $extension;
            $stmt = $this->pdo->prepare('INSERT INTO ' . $table . ' (' . $key . ', path, original_name, bytes) VALUES (?, ?, ?, ?)');
            $stmt->execute(array($id, $path, $name, $file['size']));
        } catch (\Throwable $error) {
            unlink($destination);
            throw $error;
        }
        return $old['path'] ?? null;
    }

    public function remove(int $postId): ?string
    {
        return $this->removeForOwner('post_attachment', 'post_id', $postId);
    }

    public function removeForCategory(int $categoryId): ?string
    {
        return $this->removeForOwner('category_attachment', 'category_id', $categoryId);
    }

    private function removeForOwner(string $table, string $key, int $id): ?string
    {
        $old = $this->forOwner($table, $key, $id);
        $stmt = $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE ' . $key . ' = ?');
        $stmt->execute(array($id));
        return $old['path'] ?? null;
    }

    public function deleteFile(?string $path): void
    {
        if ($path !== null && preg_match('/^[a-f0-9]{32}\.(pdf|txt|zip|docx|xlsx|pptx)$/D', $path)) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM post_attachment WHERE path = ?');
            $stmt->execute(array($path));
            if ((int) $stmt->fetchColumn() > 0) {
                return;
            }
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM category_attachment WHERE path = ?');
            $stmt->execute(array($path));
            if ((int) $stmt->fetchColumn() > 0) {
                return;
            }
            $full = $this->storageRoot . '/attachments/' . $path;
            if (is_file($full)) {
                unlink($full);
            }
        }
    }

    public function send(array $attachment): void
    {
        $path = $attachment['path'];
        if (! is_string($path) || ! preg_match('/^[a-f0-9]{32}\.(pdf|txt|zip|docx|xlsx|pptx)$/D', $path)) {
            throw new RuntimeException('附件不存在。');
        }
        $full = $this->storageRoot . '/attachments/' . $path;
        if (! is_file($full)) {
            throw new RuntimeException('附件不存在。');
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="download.' . pathinfo($path, PATHINFO_EXTENSION) . '"; filename*=UTF-8\'\'' . rawurlencode($attachment['original_name']));
        header('Content-Length: ' . filesize($full));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($full);
    }
}

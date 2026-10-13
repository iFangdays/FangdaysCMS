<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;
use PDO;

final class MemberRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function register(string $email, string $displayName, string $password): int
    {
        $email = strtolower(trim($email));
        $displayName = trim($displayName);
        if (strlen($email) > 191 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $displayName === '' || mb_strlen($displayName) > 100 || strlen($password) < 12) {
            throw new InvalidArgumentException('邮箱、昵称或密码无效；密码至少 12 位。');
        }
        $stmt = $this->pdo->prepare('INSERT INTO members (email, display_name, password_hash, status, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute(array($email, $displayName, password_hash($password, PASSWORD_DEFAULT), 'active', time()));
        return (int) $this->pdo->lastInsertId();
    }

    public function authenticate(string $email, string $password): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, email, display_name, password_hash, status FROM members WHERE email = ?');
        $stmt->execute(array(strtolower(trim($email))));
        $member = $stmt->fetch();
        if (! $member || $member['status'] !== 'active' || ! password_verify($password, $member['password_hash'])) {
            return null;
        }
        unset($member['password_hash']);
        return $member;
    }

    public function byId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, email, display_name, status, created_at FROM members WHERE id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT id, email, display_name, status, created_at FROM members ORDER BY id DESC')->fetchAll();
    }

    public function updateName(int $id, string $displayName): void
    {
        $displayName = trim($displayName);
        if ($displayName === '' || mb_strlen($displayName) > 100) {
            throw new InvalidArgumentException('昵称不能为空且不超过 100 个字。');
        }
        $stmt = $this->pdo->prepare('UPDATE members SET display_name = ? WHERE id = ?');
        $stmt->execute(array($displayName, $id));
    }

    public function changePassword(int $id, string $currentPassword, string $newPassword): void
    {
        if (strlen($newPassword) < 12) {
            throw new InvalidArgumentException('新密码至少 12 位。');
        }
        $stmt = $this->pdo->prepare('SELECT password_hash FROM members WHERE id = ? AND status = ?');
        $stmt->execute(array($id, 'active'));
        $hash = $stmt->fetchColumn();
        if (! is_string($hash) || ! password_verify($currentPassword, $hash)) {
            throw new InvalidArgumentException('当前密码不正确。');
        }
        $stmt = $this->pdo->prepare('UPDATE members SET password_hash = ? WHERE id = ?');
        $stmt->execute(array(password_hash($newPassword, PASSWORD_DEFAULT), $id));
    }

    public function setStatus(int $id, string $status): void
    {
        if (! in_array($status, array('active', 'disabled'), true)) {
            throw new InvalidArgumentException('会员状态无效。');
        }
        $stmt = $this->pdo->prepare('UPDATE members SET status = ? WHERE id = ?');
        $stmt->execute(array($status, $id));
    }
}

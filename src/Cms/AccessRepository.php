<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;
use PDO;

final class AccessRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function roles(): array
    {
        return $this->pdo->query('SELECT r.id, r.slug, r.name, COUNT(ur.user_id) AS user_count FROM roles r LEFT JOIN user_roles ur ON ur.role_id = r.id GROUP BY r.id, r.slug, r.name ORDER BY r.id')->fetchAll();
    }

    public function role(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, slug, name FROM roles WHERE id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function grants(int $roleId): array
    {
        $stmt = $this->pdo->prepare('SELECT permission FROM role_permissions WHERE role_id = ?');
        $stmt->execute(array($roleId));
        return array_column($stmt->fetchAll(), 'permission');
    }

    public function saveRole(?int $id, string $name, string $slug, array $permissions): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100 || ! preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $slug) || $slug === 'owner') {
            throw new InvalidArgumentException('角色名称或标识无效。');
        }
        $permissions = array_values(array_unique($permissions));
        foreach ($permissions as $permission) {
            if (! is_string($permission) || ! array_key_exists($permission, Permissions::CATALOG)) {
                throw new InvalidArgumentException('包含未知权限。');
            }
        }
        if (! in_array('dashboard.view', $permissions, true)) {
            $permissions[] = 'dashboard.view';
        }
        if ($id !== null && ($this->role($id)['slug'] ?? null) === 'owner') {
            throw new InvalidArgumentException('站点所有者角色不可修改。');
        }
        $this->pdo->beginTransaction();
        try {
            if ($id === null) {
                $stmt = $this->pdo->prepare('INSERT INTO roles (name, slug) VALUES (?, ?)');
                $stmt->execute(array($name, $slug));
                $id = (int) $this->pdo->lastInsertId();
            } else {
                $stmt = $this->pdo->prepare('UPDATE roles SET name = ?, slug = ? WHERE id = ?');
                $stmt->execute(array($name, $slug, $id));
            }
            $stmt = $this->pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?');
            $stmt->execute(array($id));
            $stmt = $this->pdo->prepare('INSERT INTO role_permissions (role_id, permission) VALUES (?, ?)');
            foreach ($permissions as $permission) {
                $stmt->execute(array($id, $permission));
            }
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function deleteRole(int $id): void
    {
        $role = $this->role($id);
        if ($role === null || $role['slug'] === 'owner') {
            throw new InvalidArgumentException('角色不可删除。');
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_roles WHERE role_id = ?');
        $stmt->execute(array($id));
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('该角色仍有后台账号使用。');
        }
        $stmt = $this->pdo->prepare('DELETE FROM roles WHERE id = ?');
        $stmt->execute(array($id));
    }

    public function admins(): array
    {
        return $this->pdo->query('SELECT u.id, u.username, u.created_at, r.id AS role_id, r.name AS role_name, r.slug AS role_slug FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id ORDER BY u.id')->fetchAll();
    }

    public function admin(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT u.id, u.username, r.id AS role_id, r.slug AS role_slug FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public function createAdmin(string $username, string $password, int $roleId, bool $actorIsOwner): int
    {
        $role = $this->role($roleId);
        if (! preg_match('/^[a-zA-Z0-9_-]{3,32}$/D', $username) || strlen($password) < 12 || $role === null) {
            throw new InvalidArgumentException('后台账号、密码或角色无效；密码至少 12 位。');
        }
        if ($role['slug'] === 'owner' && ! $actorIsOwner) {
            throw new InvalidArgumentException('只有站点所有者可以创建所有者账号。');
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, ?)');
            $stmt->execute(array($username, password_hash($password, PASSWORD_DEFAULT), time()));
            $id = (int) $this->pdo->lastInsertId();
            $stmt = $this->pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
            $stmt->execute(array($id, $roleId));
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function updateAdmin(int $id, int $roleId, string $newPassword, bool $actorIsOwner): void
    {
        $admin = $this->admin($id);
        $role = $this->role($roleId);
        if ($admin === null || $role === null || ($newPassword !== '' && strlen($newPassword) < 12)) {
            throw new InvalidArgumentException('账号、角色或新密码无效；新密码至少 12 位。');
        }
        if (($admin['role_slug'] === 'owner' && $role['slug'] !== 'owner') || (! $actorIsOwner && ($admin['role_slug'] === 'owner' || $role['slug'] === 'owner'))) {
            throw new InvalidArgumentException('无权修改站点所有者角色。');
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE user_roles SET role_id = ? WHERE user_id = ?');
            $stmt->execute(array($roleId, $id));
            if ($newPassword !== '') {
                $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute(array(password_hash($newPassword, PASSWORD_DEFAULT), $id));
            }
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function deleteAdmin(int $id, int $currentId): void
    {
        $stmt = $this->pdo->prepare('SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?');
        $stmt->execute(array($id));
        if ($id === $currentId || $stmt->fetchColumn() === 'owner') {
            throw new InvalidArgumentException('当前账号或站点所有者不可删除。');
        }
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute(array($id));
    }
}

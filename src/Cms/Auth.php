<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use PDO;

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(array(
            'httponly' => true,
            'secure' => ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
            'path' => '/'
        ));
        session_start();
    }

    public static function csrfToken(): string
    {
        if (! isset($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function validCsrf(?string $value): bool
    {
        return is_string($value) && hash_equals(self::csrfToken(), $value);
    }

    public static function login(PDO $pdo, string $username, string $password): bool
    {
        $failed = $_SESSION['login_failed'] ?? array('count' => 0, 'since' => time());
        if (time() - (int) $failed['since'] > 300) {
            $failed = array('count' => 0, 'since' => time());
        }
        if ($failed['count'] >= 5) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT u.id, u.password_hash FROM users u JOIN user_roles ur ON ur.user_id = u.id WHERE u.username = ?');
        $stmt->execute(array($username));
        $user = $stmt->fetch();
        if (! $user || ! password_verify($password, $user['password_hash'])) {
            $failed['count']++;
            $_SESSION['login_failed'] = $failed;
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        unset($_SESSION['login_failed']);
        return true;
    }

    public static function user(PDO $pdo): ?array
    {
        $id = $_SESSION['admin_id'] ?? null;
        if (! is_int($id)) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT u.id, u.username, r.id AS role_id, r.name AS role_name, r.slug AS role_slug FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    public static function logout(): void
    {
        unset($_SESSION['admin_id']);
        session_regenerate_id(true);
    }

    public static function member(PDO $pdo): ?array
    {
        $id = $_SESSION['member_id'] ?? null;
        if (! is_int($id)) {
            return null;
        }
        $member = (new MemberRepository($pdo))->byId($id);
        if ($member === null || $member['status'] !== 'active') {
            unset($_SESSION['member_id']);
            return null;
        }
        return $member;
    }

    public static function memberLogin(PDO $pdo, string $email, string $password): bool
    {
        $failed = $_SESSION['member_login_failed'] ?? array('count' => 0, 'since' => time());
        if (time() - (int) $failed['since'] > 300) {
            $failed = array('count' => 0, 'since' => time());
        }
        if ($failed['count'] >= 5) {
            return false;
        }
        $member = (new MemberRepository($pdo))->authenticate($email, $password);
        if ($member === null) {
            $failed['count']++;
            $_SESSION['member_login_failed'] = $failed;
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['member_id'] = (int) $member['id'];
        unset($_SESSION['member_login_failed']);
        return true;
    }

    public static function memberLogout(): void
    {
        unset($_SESSION['member_id']);
        session_regenerate_id(true);
    }
}

<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use PDO;

final class Permissions
{
    public const CATALOG = array(
        'dashboard.view' => '查看概览',
        'content.view' => '查看内容',
        'content.manage' => '管理文章与单页',
        'taxonomy.manage' => '管理栏目与标签',
        'media.manage' => '管理媒体图片',
        'members.manage' => '管理会员',
        'access.manage' => '管理后台账号与角色',
        'license.manage' => '管理授权码',
        'settings.manage' => '管理站点设置'
    );

    public static function can(PDO $pdo, array $user, string $permission): bool
    {
        if (! array_key_exists($permission, self::CATALOG)) {
            return false;
        }
        if (($user['role_slug'] ?? null) === 'owner') {
            return true;
        }
        $stmt = $pdo->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission = ?');
        $stmt->execute(array((int) ($user['role_id'] ?? 0), $permission));
        return (bool) $stmt->fetchColumn();
    }
}

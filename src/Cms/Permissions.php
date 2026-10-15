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
        'operations.manage' => '管理轮播、友链、公司信息与自定义标签',
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
        $grants = $permission === 'content.view' ? array('content.view', 'content.manage') : array($permission);
        $placeholders = implode(', ', array_fill(0, count($grants), '?'));
        $stmt = $pdo->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission IN (' . $placeholders . ')');
        $stmt->execute(array_merge(array((int) ($user['role_id'] ?? 0)), $grants));
        return (bool) $stmt->fetchColumn();
    }
}

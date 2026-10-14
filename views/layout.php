<?php
$routePath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$adminGroups = array(
    '工作台' => array(array('/admin', '概览', 'dashboard.view', '▦')),
    '内容管理' => array(
        array('/admin/posts', '文章', 'content.view', '文'),
        array('/admin/pages', '单页', 'content.view', '页'),
        array('/admin/categories', '栏目', 'taxonomy.manage', '栏'),
        array('/admin/tags', '标签', 'taxonomy.manage', '签'),
        array('/admin/media', '媒体图片', 'media.manage', '图')
    ),
    '用户与权限' => array(
        array('/admin/members', '会员', 'members.manage', '员'),
        array('/admin/roles', '角色权限', 'access.manage', '权'),
        array('/admin/admins', '后台账号', 'access.manage', '账')
    ),
    '系统设置' => array(
        array('/admin/settings', '站点设置', 'settings.manage', '设'),
        array('/admin/license', '离线授权', 'license.manage', '授')
    )
);
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= \FangdaysCMS\Cms\View::e($pageTitle) ?> · <?= \FangdaysCMS\Cms\View::e($siteName) ?></title>
  <link rel="stylesheet" href="/assets/app.css">
  <?php if ($admin): ?><link rel="stylesheet" href="/assets/admin.css"><?php endif; ?>
</head>
<body class="<?= $admin ? 'admin-body' : '' ?>">
<?php if ($admin): ?>
  <div class="admin-shell">
    <aside class="admin-sidebar" id="admin-navigation" aria-label="后台导航">
      <a class="admin-brand" href="/admin"><span class="admin-brand-mark" aria-hidden="true">F</span><span>FangdaysCMS<small>内容管理中心</small></span></a>
      <nav class="admin-side-nav" aria-label="管理菜单">
        <?php foreach ($adminGroups as $group => $items): ?>
          <?php $visible = array_values(array_filter($items, static fn (array $item): bool => $can[$item[2]] ?? false)); ?>
          <?php if (! $visible) { continue; } ?>
          <div class="admin-nav-group">
            <p class="admin-nav-heading"><?= \FangdaysCMS\Cms\View::e($group) ?></p>
            <?php foreach ($visible as [$href, $label, $permission, $icon]): ?>
              <?php $current = $href === '/admin' ? $routePath === '/admin' : str_starts_with($routePath, $href); ?>
              <a class="admin-nav-link<?= $current ? ' is-current' : '' ?>" href="<?= \FangdaysCMS\Cms\View::e($href) ?>" <?= $current ? 'aria-current="page"' : '' ?>><span class="admin-nav-icon" aria-hidden="true"><?= \FangdaysCMS\Cms\View::e($icon) ?></span><?= \FangdaysCMS\Cms\View::e($label) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </nav>
      <div class="admin-sidebar-foot"><?= \FangdaysCMS\Cms\View::e($siteName) ?></div>
    </aside>
    <button class="admin-scrim" type="button" aria-label="关闭导航" hidden></button>
    <div class="admin-workspace">
      <header class="admin-topbar">
        <div class="admin-topbar-title"><button class="admin-nav-toggle" type="button" aria-controls="admin-navigation" aria-expanded="false" aria-label="打开导航">☰</button><span>管理后台</span><span class="admin-title-divider">/</span><strong><?= \FangdaysCMS\Cms\View::e($pageTitle) ?></strong></div>
        <div class="admin-topbar-actions"><a href="/" target="_blank" rel="noopener">查看网站 ↗</a><span class="admin-account"><?= \FangdaysCMS\Cms\View::e($user['username']) ?> · <?= \FangdaysCMS\Cms\View::e($user['role_name']) ?></span><form method="post" action="/logout" class="inline-form"><?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="admin-logout">退出</button></form></div>
      </header>
      <main class="admin-content" id="main-content">
        <?php if ($flash): ?><div class="<?= str_starts_with((string) $flash, '操作失败：') ? 'error' : 'notice' ?>" role="status"><?= \FangdaysCMS\Cms\View::e($flash) ?></div><?php endif; ?>
        <?= $content ?>
      </main>
      <footer class="admin-footer">FangdaysCMS · 内容管理中心</footer>
    </div>
  </div>
  <script>
    (() => {
      const toggle = document.querySelector('.admin-nav-toggle');
      const scrim = document.querySelector('.admin-scrim');
      const close = () => { document.body.classList.remove('admin-nav-open'); toggle.setAttribute('aria-expanded', 'false'); toggle.setAttribute('aria-label', '打开导航'); scrim.hidden = true; };
      toggle.addEventListener('click', () => {
        if (document.body.classList.contains('admin-nav-open')) { close(); return; }
        document.body.classList.add('admin-nav-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', '关闭导航');
        scrim.hidden = false;
      });
      scrim.addEventListener('click', close);
      document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    })();
  </script>
<?php else: ?>
  <header class="topbar">
    <div class="wrap topbar-inner">
      <a class="brand" href="/"><?= \FangdaysCMS\Cms\View::e($siteName) ?></a>
      <nav aria-label="主导航">
        <a href="/">首页</a><a href="/search">搜索</a>
        <?php if ($member ?? null): ?><a href="/member/profile">会员中心</a><form method="post" action="/member/logout" class="inline-form"><?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="link-button">退出会员</button></form>
        <?php else: ?><a href="/member/login">会员登录</a><a href="/member/register">注册</a><?php endif; ?>
        <a href="/login">后台</a>
      </nav>
    </div>
  </header>
  <main class="wrap">
    <?php if ($flash): ?><div class="notice" role="status"><?= \FangdaysCMS\Cms\View::e($flash) ?></div><?php endif; ?>
    <?= $content ?>
  </main>
  <footer class="wrap footer">FangdaysCMS · 独立内容管理系统</footer>
<?php endif; ?>
</body>
</html>

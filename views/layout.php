<?php
$routePath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$loginPage = $routePath === '/login';
$adminGroups = array(
    '工作台' => array(array('/admin', '管理概览', 'dashboard.view', 'fa-tachometer')),
    '内容管理' => array(
        array('/admin/posts', '文章管理', 'content.view', 'fa-file-text-o'),
        array('/admin/pages', '单页管理', 'content.view', 'fa-file-o'),
        array('/admin/categories', '栏目管理', 'taxonomy.manage', 'fa-folder-o'),
        array('/admin/models', '内容模型', 'taxonomy.manage', 'fa-cubes'),
        array('/admin/tags', '标签管理', 'taxonomy.manage', 'fa-tags'),
        array('/admin/media', '媒体图片', 'media.manage', 'fa-picture-o')
    ),
    '用户与权限' => array(
        array('/admin/members', '会员管理', 'members.manage', 'fa-users'),
        array('/admin/roles', '角色权限', 'access.manage', 'fa-shield'),
        array('/admin/admins', '后台账号', 'access.manage', 'fa-user-circle-o')
    ),
    '系统设置' => array(
        array('/admin/settings', '站点设置', 'settings.manage', 'fa-cog'),
        array('/admin/license', '离线授权', 'license.manage', 'fa-key')
    )
);
$adminGroupIcons = array('工作台' => 'fa-tachometer', '内容管理' => 'fa-list-alt', '用户与权限' => 'fa-users', '系统设置' => 'fa-cogs');
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= \FangdaysCMS\Cms\View::e($pageTitle) ?> · <?= \FangdaysCMS\Cms\View::e($siteName) ?></title>
  <link rel="stylesheet" href="/assets/app.css">
  <?php if ($admin): ?>
    <link rel="stylesheet" href="/assets/pboot-admin/layui/css/layui.css">
    <link rel="stylesheet" href="/assets/pboot-admin/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="/assets/pboot-admin/css/comm.css">
    <link rel="stylesheet" href="/assets/admin.css">
  <?php elseif ($loginPage): ?>
    <link rel="stylesheet" href="/assets/pboot-admin/layui/css/layui.css">
    <link rel="stylesheet" href="/assets/pboot-admin/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="/assets/pboot-admin/css/login.css">
    <link rel="stylesheet" href="/assets/admin-login.css">
  <?php endif; ?>
</head>
<body class="<?= $admin ? 'admin-body' : ($loginPage ? 'admin-login-body' : '') ?>">
<?php if ($admin): ?>
  <div class="layui-layout layui-layout-admin admin-shell">
    <header class="layui-header">
      <div class="layui-logo"><a href="/admin"><i class="fa fa-leaf" aria-hidden="true"></i> FangdaysCMS</a></div>
      <button class="menu-ico admin-nav-toggle" type="button" aria-controls="admin-navigation" aria-expanded="false" aria-label="显示或隐藏侧边栏"><i class="fa fa-bars" aria-hidden="true"></i></button>
      <ul class="layui-nav layui-layout-right admin-header-links">
        <li class="layui-nav-item"><a href="/" target="_blank" rel="noopener"><i class="fa fa-home" aria-hidden="true"></i> 网站主页</a></li>
        <li class="layui-nav-item admin-account"><i class="fa fa-user-circle-o" aria-hidden="true"></i> <?= \FangdaysCMS\Cms\View::e($user['username']) ?> <span class="admin-role"><?= \FangdaysCMS\Cms\View::e($user['role_name']) ?></span></li>
        <li class="layui-nav-item"><form method="post" action="/logout" class="inline-form"><?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="admin-logout"><i class="fa fa-sign-out" aria-hidden="true"></i> 退出登录</button></form></li>
      </ul>
    </header>
    <aside class="layui-side layui-bg-black admin-sidebar" id="admin-navigation" aria-label="后台导航">
      <nav class="layui-side-scroll" aria-label="管理菜单">
        <ul class="layui-nav layui-nav-tree" id="nav">
        <?php foreach ($adminGroups as $group => $items): ?>
          <?php $visible = array_values(array_filter($items, static fn (array $item): bool => $can[$item[2]] ?? false)); ?>
          <?php if (! $visible) { continue; } ?>
          <?php $expanded = false; foreach ($visible as $item) { if ($item[0] === '/admin' ? $routePath === '/admin' : str_starts_with($routePath, $item[0])) { $expanded = true; break; } } ?>
          <li class="layui-nav-item admin-nav-group<?= $expanded ? ' layui-nav-itemed' : '' ?>">
            <button class="admin-group-toggle" type="button" aria-expanded="<?= $expanded ? 'true' : 'false' ?>"><i class="fa <?= $adminGroupIcons[$group] ?>" aria-hidden="true"></i><?= \FangdaysCMS\Cms\View::e($group) ?><i class="fa fa-angle-down admin-chevron" aria-hidden="true"></i></button>
            <dl class="layui-nav-child">
            <?php foreach ($visible as [$href, $label, $permission, $icon]): ?>
              <?php $current = $href === '/admin' ? $routePath === '/admin' : str_starts_with($routePath, $href); ?>
              <dd class="<?= $current ? 'layui-this' : '' ?>"><a href="<?= \FangdaysCMS\Cms\View::e($href) ?>" <?= $current ? 'aria-current="page"' : '' ?>><i class="fa <?= \FangdaysCMS\Cms\View::e($icon) ?>" aria-hidden="true"></i><?= \FangdaysCMS\Cms\View::e($label) ?></a></dd>
            <?php endforeach; ?>
            </dl>
          </li>
        <?php endforeach; ?>
        </ul>
      </nav>
    </aside>
    <button class="admin-scrim" type="button" aria-label="关闭导航" hidden></button>
    <main class="layui-body admin-content" id="main-content">
        <?php if ($flash): ?><div class="<?= str_starts_with((string) $flash, '操作失败：') ? 'error' : 'notice' ?>" role="status"><?= \FangdaysCMS\Cms\View::e($flash) ?></div><?php endif; ?>
        <?= $content ?>
    </main>
  </div>
  <script>
    (() => {
      const toggle = document.querySelector('.admin-nav-toggle');
      const scrim = document.querySelector('.admin-scrim');
      const mobile = () => window.matchMedia('(max-width: 750px)').matches;
      const sync = () => toggle.setAttribute('aria-expanded', String(mobile() ? document.body.classList.contains('admin-nav-open') : !document.body.classList.contains('admin-nav-collapsed')));
      const close = () => { document.body.classList.remove('admin-nav-open'); scrim.hidden = true; sync(); };
      toggle.addEventListener('click', () => {
        if (mobile()) {
          if (document.body.classList.contains('admin-nav-open')) { close(); return; }
          document.body.classList.add('admin-nav-open');
          scrim.hidden = false;
        } else {
          document.body.classList.toggle('admin-nav-collapsed');
        }
        sync();
      });
      scrim.addEventListener('click', close);
      document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
      window.addEventListener('resize', close);
      sync();
      document.querySelectorAll('.admin-group-toggle').forEach((button) => button.addEventListener('click', () => {
        const group = button.closest('.layui-nav-item');
        group.classList.toggle('layui-nav-itemed');
        button.setAttribute('aria-expanded', String(group.classList.contains('layui-nav-itemed')));
      }));
    })();
  </script>
<?php elseif ($loginPage): ?>
  <?= $content ?>
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

<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= \FangdaysCMS\Cms\View::e($pageTitle) ?> · <?= \FangdaysCMS\Cms\View::e($siteName) ?></title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <header class="topbar">
    <div class="wrap topbar-inner">
      <a class="brand" href="/"><?= \FangdaysCMS\Cms\View::e($siteName) ?></a>
      <nav aria-label="主导航">
        <?php if ($admin): ?>
          <?php if ($can['dashboard.view'] ?? false): ?><a href="/admin">概览</a><?php endif; ?>
          <?php if ($can['taxonomy.manage'] ?? false): ?><a href="/admin/categories">栏目</a><a href="/admin/tags">标签</a><?php endif; ?>
          <?php if ($can['content.view'] ?? false): ?><a href="/admin/posts">内容</a><a href="/admin/pages">单页</a><?php endif; ?>
          <?php if ($can['media.manage'] ?? false): ?><a href="/admin/media">媒体</a><?php endif; ?>
          <?php if ($can['members.manage'] ?? false): ?><a href="/admin/members">会员</a><?php endif; ?>
          <?php if ($can['access.manage'] ?? false): ?><a href="/admin/roles">权限</a><a href="/admin/admins">账号</a><?php endif; ?>
          <?php if ($can['license.manage'] ?? false): ?><a href="/admin/license">授权</a><?php endif; ?>
          <?php if ($can['settings.manage'] ?? false): ?><a href="/admin/settings">站点</a><?php endif; ?>
          <form method="post" action="/logout" class="inline-form"><?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="link-button">退出</button></form>
        <?php else: ?>
          <a href="/">首页</a><a href="/search">搜索</a>
          <?php if ($member ?? null): ?><a href="/member/profile">会员中心</a><form method="post" action="/member/logout" class="inline-form"><?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="link-button">退出会员</button></form>
          <?php else: ?><a href="/member/login">会员登录</a><a href="/member/register">注册</a><?php endif; ?>
          <a href="/login">后台</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main class="wrap">
    <?php if ($flash): ?><div class="notice" role="status"><?= \FangdaysCMS\Cms\View::e($flash) ?></div><?php endif; ?>
    <?= $content ?>
  </main>
  <footer class="wrap footer">FangdaysCMS · 独立内容管理系统</footer>
</body>
</html>

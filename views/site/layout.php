<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="<?= \FangdaysCMS\Cms\View::e($site_description ?: $pageTitle . ' - ' . $siteName) ?>">
  <title><?= \FangdaysCMS\Cms\View::e($pageTitle) ?> · <?= \FangdaysCMS\Cms\View::e($siteName) ?></title>
  <link rel="stylesheet" href="/assets/theme/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/theme/aoyun.css">
  <link rel="stylesheet" href="/assets/theme/site.css">
</head>
<body>
<header class="site-header"><div class="container d-flex align-items-center justify-content-between flex-wrap py-3">
  <a class="site-brand" href="/"><?= \FangdaysCMS\Cms\View::e($siteName) ?></a>
  <nav class="site-nav" aria-label="主导航"><a href="/">首页</a><?php foreach (array_slice($categories ?? array(), 0, 6) as $category): ?><a href="/category/<?= \FangdaysCMS\Cms\View::e($category['slug']) ?>"><?= \FangdaysCMS\Cms\View::e($category['name']) ?></a><?php endforeach; ?><?php foreach (array_slice($pages ?? array(), 0, 4) as $navPage): ?><a href="/page/<?= \FangdaysCMS\Cms\View::e($navPage['slug']) ?>"><?= \FangdaysCMS\Cms\View::e($navPage['title']) ?></a><?php endforeach; ?><a href="/search">搜索</a><a href="/member/profile">会员</a><a href="/login">后台</a></nav>
</div></header>
<main><?php if ($flash): ?><div class="container mt-3"><div class="alert alert-success" role="status"><?= \FangdaysCMS\Cms\View::e($flash) ?></div></div><?php endif; ?><?= $content ?></main>
<footer class="site-footer"><div class="container d-flex flex-wrap justify-content-between"><span><?= \FangdaysCMS\Cms\View::e($siteName) ?></span><span><?= \FangdaysCMS\Cms\View::e($footer_text ?? 'Powered by FangdaysCMS') ?></span></div></footer>
</body></html>

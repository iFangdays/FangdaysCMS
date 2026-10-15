<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="<?= \FangdaysCMS\Cms\View::e(($seo_description ?? '') ?: ($site_description ?: $pageTitle . ' - ' . $siteName)) ?>">
  <?php if (! empty($seo_keywords)): ?><meta name="keywords" content="<?= \FangdaysCMS\Cms\View::e($seo_keywords) ?>"><?php endif; ?>
  <title><?= \FangdaysCMS\Cms\View::e(($seo_title ?? '') ?: $pageTitle . ' · ' . $siteName) ?></title>
  <link rel="stylesheet" href="/assets/theme/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/theme/aoyun.css">
  <link rel="stylesheet" href="/assets/theme/site.css">
  <?php if (! empty($slides)): ?><link rel="stylesheet" href="/assets/splide/splide.min.css"><?php endif; ?>
</head>
<body>
<header class="site-header"><div class="container d-flex align-items-center justify-content-between flex-wrap py-3">
  <a class="site-brand" href="/"><?= \FangdaysCMS\Cms\View::e($siteName) ?></a>
  <nav class="site-nav" aria-label="主导航"><a href="/">首页</a><?php foreach (array_slice($categories ?? array(), 0, 6) as $category): ?><a href="<?= \FangdaysCMS\Cms\View::e(\FangdaysCMS\Cms\View::categoryLink($category)) ?>"><?= \FangdaysCMS\Cms\View::e($category['name']) ?></a><?php endforeach; ?><?php foreach (array_slice($pages ?? array(), 0, 4) as $navPage): ?><a href="/page/<?= \FangdaysCMS\Cms\View::e($navPage['slug']) ?>"><?= \FangdaysCMS\Cms\View::e($navPage['title']) ?></a><?php endforeach; ?><a href="/search">搜索</a><a href="/member/profile">会员</a><a href="/login">后台</a></nav>
</div></header>
<main><?php if ($flash): ?><div class="container mt-3"><div class="alert alert-success" role="status"><?= \FangdaysCMS\Cms\View::e($flash) ?></div></div><?php endif; ?><?= $content ?></main>
<footer class="site-footer"><div class="container"><?php if ($friend_links ?? array()): ?><nav class="site-friend-links" aria-label="友情链接"><strong>友情链接</strong><?php foreach ($friend_links as $friend): ?><a href="<?= \FangdaysCMS\Cms\View::e($friend['link']) ?>" target="_blank" rel="noopener noreferrer"><?php if ($friend['logo_path']): ?><img src="<?= \FangdaysCMS\Cms\View::e($friend['logo_path']) ?>" alt="" loading="lazy"><?php endif; ?><?= \FangdaysCMS\Cms\View::e($friend['name']) ?></a><?php endforeach; ?></nav><?php endif; ?><div class="d-flex flex-wrap justify-content-between"><span><?= \FangdaysCMS\Cms\View::e(($company['name'] ?? '') ?: $siteName) ?><?php if (! empty($company['phone'])): ?> · <?= \FangdaysCMS\Cms\View::e($company['phone']) ?><?php endif; ?></span><span><?= \FangdaysCMS\Cms\View::e(\FangdaysCMS\Cms\OperationTags::plain($footer_text ?? 'Powered by FangdaysCMS', $custom_labels ?? array(), $company ?? array())) ?></span></div></div></footer>
<?php if (! empty($slides)): ?><script src="/assets/splide/splide.min.js"></script><script>new Splide('.site-slides', { type: <?= count($slides) > 1 ? "'loop'" : "'slide'" ?>, perPage: 1, autoplay: <?= count($slides) > 1 ? 'true' : 'false' ?>, interval: 5000, pauseOnHover: true, pauseOnFocus: true, arrows: <?= count($slides) > 1 ? 'true' : 'false' ?>, pagination: <?= count($slides) > 1 ? 'true' : 'false' ?> }).mount();</script><?php endif; ?>
</body></html>

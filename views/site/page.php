<section class="site-page-heading"><div class="container"><p class="site-kicker">单页</p><h1><?= \FangdaysCMS\Cms\View::e($page['title']) ?></h1></div></section>
<div class="container py-5"><div class="row justify-content-center"><article class="col-lg-9">
  <?php if ($cover): ?><img class="site-post-cover mb-4" src="<?= \FangdaysCMS\Cms\View::e($cover['path']) ?>" alt="<?= \FangdaysCMS\Cms\View::e($page['title']) ?>"><?php endif; ?>
  <div class="site-article-body"><?= \FangdaysCMS\Cms\RichText::render($page['body'], $page['body_format']) ?></div>
  <?php if ($gallery): ?><section class="site-post-gallery mt-4" aria-label="单页多图"><h2>图片集</h2><div class="site-post-gallery-grid"><?php foreach ($gallery as $image): ?><figure><img src="<?= \FangdaysCMS\Cms\View::e($image['path']) ?>" alt="<?= \FangdaysCMS\Cms\View::e($image['title'] ?: $image['original_name']) ?>" loading="lazy"><figcaption><?= \FangdaysCMS\Cms\View::e($image['title'] ?: $image['original_name']) ?></figcaption></figure><?php endforeach; ?></div></section><?php endif; ?>
  <?php if ($attachment): ?><p class="site-post-attachment mt-4"><a href="/page/<?= \FangdaysCMS\Cms\View::e($page['slug']) ?>/attachment">下载附件：<?= \FangdaysCMS\Cms\View::e($attachment['original_name']) ?></a> <small>（<?= (int) $attachment['bytes'] ?> 字节）</small></p><?php endif; ?>
  <p class="mt-5"><a href="/">← 返回首页</a></p>
</article></div></div>

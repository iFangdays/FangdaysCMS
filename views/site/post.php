<section class="site-page-heading"><div class="container"><p class="site-kicker"><?php foreach ($breadcrumb as $crumb): ?><a href="<?= \FangdaysCMS\Cms\View::e(\FangdaysCMS\Cms\View::categoryLink($crumb)) ?>"><?= \FangdaysCMS\Cms\View::e($crumb['name']) ?></a> / <?php endforeach; ?><?= date('Y-m-d', (int) $post['created_at']) ?></p><h1><?= \FangdaysCMS\Cms\View::e($post['title']) ?></h1></div></section>
<div class="container py-5"><div class="row justify-content-center"><article class="col-lg-9">
  <?php if ($post['cover_path']): ?><img class="site-post-cover mb-4" src="<?= \FangdaysCMS\Cms\View::e($post['cover_path']) ?>" alt="<?= \FangdaysCMS\Cms\View::e($post['title']) ?>"><?php endif; ?>
  <?php if ($tags): ?><div class="site-tags mb-4"><?php foreach ($tags as $tag): ?><a href="/tag/<?= \FangdaysCMS\Cms\View::e($tag['slug']) ?>">#<?= \FangdaysCMS\Cms\View::e($tag['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
  <div class="site-article-body"><?= nl2br(\FangdaysCMS\Cms\View::e($post['body'])) ?></div>
  <?php if ($extra_fields): ?><dl class="site-post-fields mt-4 border-top pt-3"><?php foreach ($extra_fields as $field): ?><div class="row py-2"><dt class="col-sm-3"><?= \FangdaysCMS\Cms\View::e($field['label']) ?></dt><dd class="col-sm-9 mb-0"><?= nl2br(\FangdaysCMS\Cms\View::e($field['value'])) ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
  <p class="mt-5"><a href="/">← 返回首页</a></p>
</article></div></div>

<section class="site-page-heading"><div class="container"><p class="site-kicker"><?php if ($post['category_slug']): ?><a href="/category/<?= \FangdaysCMS\Cms\View::e($post['category_slug']) ?>"><?= \FangdaysCMS\Cms\View::e($post['category_name']) ?></a> · <?php endif; ?><?= date('Y-m-d', (int) $post['created_at']) ?></p><h1><?= \FangdaysCMS\Cms\View::e($post['title']) ?></h1></div></section>
<div class="container py-5"><div class="row justify-content-center"><article class="col-lg-9">
  <?php if ($post['cover_path']): ?><img class="site-post-cover mb-4" src="<?= \FangdaysCMS\Cms\View::e($post['cover_path']) ?>" alt="<?= \FangdaysCMS\Cms\View::e($post['title']) ?>"><?php endif; ?>
  <?php if ($tags): ?><div class="site-tags mb-4"><?php foreach ($tags as $tag): ?><a href="/tag/<?= \FangdaysCMS\Cms\View::e($tag['slug']) ?>">#<?= \FangdaysCMS\Cms\View::e($tag['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
  <div class="site-article-body"><?= nl2br(\FangdaysCMS\Cms\View::e($post['body'])) ?></div>
  <p class="mt-5"><a href="/">← 返回首页</a></p>
</article></div></div>

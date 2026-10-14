<section class="site-hero text-white"><div class="container py-5">
  <p class="site-kicker text-uppercase">FangdaysCMS · 企业内容管理</p>
  <h1 class="display-4 font-weight-bold"><?= \FangdaysCMS\Cms\View::e($siteName) ?></h1>
  <p class="lead mb-4">发现最新动态，浏览专题内容。</p>
  <a class="btn btn-light" href="#latest">浏览最新内容</a>
</div></section>
<div class="container py-5">
  <div class="text-center mb-4"><p class="site-kicker text-success mb-1">EXPLORE</p><h2>内容分类</h2></div>
  <div class="row">
    <?php foreach ($categories as $category): ?><div class="col-md-4 mb-3"><a class="site-category d-block h-100 p-4" href="<?= \FangdaysCMS\Cms\View::e(\FangdaysCMS\Cms\View::categoryLink($category)) ?>"><span class="text-muted small">栏目</span><h3 class="h5 mt-2 mb-0"><?= \FangdaysCMS\Cms\View::e($category['name']) ?> →</h3></a></div><?php endforeach; ?>
    <?php if (! $categories): ?><div class="col-12 text-center text-muted">栏目正在准备中。</div><?php endif; ?>
  </div>
</div>
<section class="bg-white py-5" id="latest"><div class="container">
  <div class="text-center mb-4"><p class="site-kicker text-success mb-1">LATEST</p><h2>最新发布</h2></div>
  <div class="row">
    <?php foreach ($posts as $post): ?><div class="col-md-6 col-lg-4 mb-4"><article class="card h-100 site-card"><?php if ($post['cover_path']): ?><a href="/post/<?= \FangdaysCMS\Cms\View::e($post['slug']) ?>"><img class="site-cover" src="<?= \FangdaysCMS\Cms\View::e($post['cover_path']) ?>" alt="" loading="lazy"></a><?php endif; ?><div class="card-body">
      <p class="small text-muted mb-2"><?= \FangdaysCMS\Cms\View::e($post['category_name'] ?? '未分类') ?> · <?= date('Y-m-d', (int) $post['created_at']) ?><?= $post['visibility'] === 'members' ? ' · 会员' : '' ?></p>
      <h3 class="h5"><a href="/post/<?= \FangdaysCMS\Cms\View::e($post['slug']) ?>"><?= \FangdaysCMS\Cms\View::e($post['title']) ?></a></h3>
      <p class="text-muted mb-0"><?= \FangdaysCMS\Cms\View::e(mb_substr($post['body'], 0, 90)) ?></p>
    </div></article></div><?php endforeach; ?>
    <?php if (! $posts): ?><div class="col-12 text-center text-muted">尚无已发布的内容。</div><?php endif; ?>
  </div>
  <?= \FangdaysCMS\Cms\View::pagination($page, $total_pages, '/') ?>
</div></section>
<?php if ($tags): ?><div class="container py-5"><h2 class="h4 mb-3">热门标签</h2><div class="site-tags"><?php foreach ($tags as $tag): ?><a href="/tag/<?= \FangdaysCMS\Cms\View::e($tag['slug']) ?>">#<?= \FangdaysCMS\Cms\View::e($tag['name']) ?></a><?php endforeach; ?></div></div><?php endif; ?>

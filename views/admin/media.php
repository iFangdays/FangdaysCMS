<div class="page-heading"><div><p class="eyebrow">内容素材</p><h1>媒体图片</h1></div><a href="/admin/posts">返回文章</a></div>
<section class="panel mb-4"><h2>上传图片</h2>
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="/admin/media" enctype="multipart/form-data" class="stack">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>图片文件<input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required></label>
    <p class="muted">支持 JPG、PNG、WebP、GIF，单张不超过 5 MB，宽高不超过 10000 像素。</p>
    <div><button type="submit">上传图片</button></div>
  </form>
</section>
<section class="panel"><h2>最近上传</h2><div class="media-grid">
<?php foreach ($media as $image): ?><article class="media-item"><img src="<?= \FangdaysCMS\Cms\View::e($image['path']) ?>" alt="<?= \FangdaysCMS\Cms\View::e($image['original_name']) ?>" loading="lazy"><div><strong><?= \FangdaysCMS\Cms\View::e($image['original_name']) ?></strong><p class="muted"><?= \FangdaysCMS\Cms\View::e($image['path']) ?></p><form method="post" action="/admin/media/<?= (int) $image['id'] ?>/delete" onsubmit="return confirm('确定删除这张图片？')"><?= \FangdaysCMS\Cms\View::csrf() ?><button class="link-button danger" type="submit">删除</button></form></div></article><?php endforeach; ?>
<?php if (! $media): ?><p class="muted">尚未上传图片。</p><?php endif; ?>
</div></section>

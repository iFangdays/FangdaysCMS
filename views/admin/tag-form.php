<div class="page-heading"><div><p class="eyebrow">标签</p><h1><?= \FangdaysCMS\Cms\View::e($pageTitle) ?></h1></div><a href="/admin/tags">返回标签</a></div>
<section class="panel narrow">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= $tag && $tag['id'] ? '/admin/tags/' . (int) $tag['id'] . '/edit' : '/admin/tags' ?>" class="stack">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>名称<input name="name" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($tag['name'] ?? '') ?>"></label>
    <label>URL 别名<input name="slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= \FangdaysCMS\Cms\View::e($tag['slug'] ?? '') ?>"></label>
    <button type="submit">保存标签</button>
  </form>
</section>

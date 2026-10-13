<section class="panel narrow"><p class="eyebrow">内容</p><h1><?= $page ? '编辑单页' : '新建单页' ?></h1>
<?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
<form class="stack" method="post" action="<?= $page && $page['id'] ? '/admin/pages/' . (int) $page['id'] . '/edit' : '/admin/pages' ?>">
  <?= \FangdaysCMS\Cms\View::csrf() ?>
  <label>标题<input name="title" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($page['title'] ?? '') ?>"></label>
  <label>URL 别名<input name="slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="160" value="<?= \FangdaysCMS\Cms\View::e($page['slug'] ?? '') ?>"></label>
  <label>正文<textarea name="body" rows="12"><?= \FangdaysCMS\Cms\View::e($page['body'] ?? '') ?></textarea></label>
  <div class="field-row"><label>状态<select name="status"><option value="draft" <?= ($page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>草稿</option><option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>>发布</option></select></label><label>导航排序<input type="number" min="0" max="1000000" name="menu_order" value="<?= \FangdaysCMS\Cms\View::e($page['menu_order'] ?? '10') ?>"></label></div>
  <p class="muted">已发布的单页会出现在前台导航，最多显示前四个。</p>
  <div><button type="submit">保存单页</button> <a href="/admin/pages">返回列表</a></div>
</form></section>

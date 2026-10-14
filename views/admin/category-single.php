<header class="admin-page-heading"><div><p class="eyebrow">内容管理 / 单页栏目</p><h1><?= \FangdaysCMS\Cms\View::e($category['name']) ?></h1><p class="muted">编辑此栏目的单页正文，发布后可通过栏目地址访问。</p></div><a class="admin-back-link" href="/admin/categories"><i class="fa fa-angle-left" aria-hidden="true"></i> 返回栏目</a></header>
<section class="panel narrow">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/single" class="stack">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>内容标题<input name="title" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($category['single_title'] ?: $category['name']) ?>"></label>
    <label>正文<textarea name="body" rows="14"><?= \FangdaysCMS\Cms\View::e($category['single_body']) ?></textarea><small>正文目前按纯文本安全展示，换行会保留。</small></label>
    <label>状态<select name="status"><option value="draft" <?= $category['single_status'] === 'draft' ? 'selected' : '' ?>>草稿</option><option value="published" <?= $category['single_status'] === 'published' ? 'selected' : '' ?>>发布</option></select></label>
    <div class="admin-form-actions"><button class="layui-btn" type="submit">保存内容</button><?php if ($category['single_status'] === 'published'): ?><a href="/category/<?= \FangdaysCMS\Cms\View::e($category['slug']) ?>" target="_blank" rel="noopener">查看前台单页 ↗</a><?php endif; ?></div>
  </form>
</section>

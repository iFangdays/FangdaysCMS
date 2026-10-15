<header class="admin-page-heading"><div><p class="eyebrow">内容管理 / 单页</p><h1><?= $page ? '编辑单页' : '新建单页' ?></h1><p class="muted">编辑单页正文，发布后可从前台导航访问。</p></div><a class="admin-back-link" href="/admin/pages"><i class="fa fa-angle-left" aria-hidden="true"></i> 返回单页列表</a></header>
<section class="panel">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form class="stack js-single-editor-form" method="post" action="<?= $page && $page['id'] ? '/admin/pages/' . (int) $page['id'] . '/edit' : '/admin/pages' ?>">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>标题<input name="title" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($page['title'] ?? '') ?>"></label>
    <label>URL 别名<input name="slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="160" value="<?= \FangdaysCMS\Cms\View::e($page['slug'] ?? '') ?>"></label>
    <?php $editorBody = $page['body'] ?? ''; $editorFormat = $page['body_format'] ?? 'plain'; require __DIR__ . '/partials/single-editor.php'; ?>
    <div class="field-row"><label>状态<select name="status"><option value="draft" <?= ($page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>草稿</option><option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>>发布</option></select></label><label>导航排序<input type="number" min="0" max="1000000" name="menu_order" value="<?= \FangdaysCMS\Cms\View::e($page['menu_order'] ?? '10') ?>"></label></div>
    <p class="muted">已发布的单页会出现在前台导航，最多显示前四个。</p>
    <div class="admin-form-actions"><button class="layui-btn" type="submit">保存单页</button> <a href="/admin/pages">返回列表</a></div>
  </form>
</section>

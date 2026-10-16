<header class="admin-page-heading"><div><p class="eyebrow">内容管理 / 单页</p><h1><?= $page ? '编辑单页' : '新建单页' ?></h1><p class="muted">编辑单页正文，发布后可从前台导航访问。</p></div><a class="admin-back-link" href="/admin/pages"><i class="fa fa-angle-left" aria-hidden="true"></i> 返回单页列表</a></header>
<section class="panel">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form class="stack js-single-editor-form" method="post" enctype="multipart/form-data" action="<?= $page && $page['id'] ? '/admin/pages/' . (int) $page['id'] . '/edit' : '/admin/pages' ?>">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>标题<input name="title" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($page['title'] ?? '') ?>"></label>
    <label>URL 别名<input name="slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="160" value="<?= \FangdaysCMS\Cms\View::e($page['slug'] ?? '') ?>"></label>
    <?php $editorBody = $page['body'] ?? ''; $editorFormat = $page['body_format'] ?? 'plain'; require __DIR__ . '/partials/single-editor.php'; ?>
    <fieldset class="stack"><legend>展示图片</legend>
      <label>封面图片<select name="cover_media_id"><option value="">无封面</option><?php foreach ($media as $image): ?><option value="<?= (int) $image['id'] ?>" <?= (string) ($page['cover_media_id'] ?? '') === (string) $image['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($image['original_name']) ?> (#<?= (int) $image['id'] ?>)</option><?php endforeach; ?></select></label>
      <small>先到 <a href="/admin/media">媒体图片</a> 上传，再选择封面。</small>
    </fieldset>
    <fieldset class="stack"><legend>轮播多图</legend>
      <p class="muted">选择媒体库图片并填写标题；顺序即前台展示顺序，最多 30 张。</p>
      <div id="gallery-rows" class="admin-gallery-rows">
        <?php foreach ($gallery as $item): ?><div class="admin-gallery-row"><img class="admin-gallery-preview" src="<?= \FangdaysCMS\Cms\View::e($item['path']) ?>" alt="" <?= $item['path'] === '' ? 'hidden' : '' ?>><select name="gallery_media_ids[]" aria-label="多图图片"><option value="">选择图片</option><?php foreach ($media as $image): ?><option value="<?= (int) $image['id'] ?>" data-path="<?= \FangdaysCMS\Cms\View::e($image['path']) ?>" <?= (string) $item['media_id'] === (string) $image['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($image['original_name']) ?> (#<?= (int) $image['id'] ?>)</option><?php endforeach; ?></select><input name="gallery_titles[]" aria-label="图片标题" maxlength="150" placeholder="图片标题" value="<?= \FangdaysCMS\Cms\View::e($item['title']) ?>"><div class="admin-gallery-actions"><button type="button" data-gallery-action="up" aria-label="上移">↑</button><button type="button" data-gallery-action="down" aria-label="下移">↓</button><button type="button" data-gallery-action="remove" aria-label="移除">×</button></div></div><?php endforeach; ?>
      </div>
      <button class="layui-btn layui-btn-primary" id="gallery-add" type="button">添加图片</button>
    </fieldset>
    <fieldset class="stack"><legend>附件</legend>
      <?php if ($attachment): ?><p>当前附件：<strong><?= \FangdaysCMS\Cms\View::e($attachment['original_name']) ?></strong>（<?= (int) $attachment['bytes'] ?> 字节）</p><label class="check"><input type="checkbox" name="remove_attachment" value="1"> 删除当前附件</label><?php endif; ?>
      <label>上传或替换附件<input type="file" name="attachment" accept=".pdf,.txt,.zip,.docx,.xlsx,.pptx"></label>
      <small>支持 PDF、TXT、ZIP 和 Office 文件，最大 20 MB。草稿附件不会对外开放。</small>
    </fieldset>
    <div class="field-row"><label>状态<select name="status"><option value="draft" <?= ($page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>草稿</option><option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>>发布</option></select></label><label>导航排序<input type="number" min="0" max="1000000" name="menu_order" value="<?= \FangdaysCMS\Cms\View::e($page['menu_order'] ?? '10') ?>"></label></div>
    <p class="muted">已发布的单页会出现在前台导航，最多显示前四个。</p>
    <div class="admin-form-actions"><button class="layui-btn" type="submit">保存单页</button> <a href="/admin/pages">返回列表</a></div>
  </form>
</section>
<template id="gallery-row-template"><div class="admin-gallery-row"><img class="admin-gallery-preview" alt="" hidden><select name="gallery_media_ids[]" aria-label="多图图片"><option value="">选择图片</option><?php foreach ($media as $image): ?><option value="<?= (int) $image['id'] ?>" data-path="<?= \FangdaysCMS\Cms\View::e($image['path']) ?>"><?= \FangdaysCMS\Cms\View::e($image['original_name']) ?> (#<?= (int) $image['id'] ?>)</option><?php endforeach; ?></select><input name="gallery_titles[]" aria-label="图片标题" maxlength="150" placeholder="图片标题"><div class="admin-gallery-actions"><button type="button" data-gallery-action="up" aria-label="上移">↑</button><button type="button" data-gallery-action="down" aria-label="下移">↓</button><button type="button" data-gallery-action="remove" aria-label="移除">×</button></div></div></template>
<script>
  (() => {
    const rows = document.getElementById('gallery-rows');
    const template = document.getElementById('gallery-row-template');
    document.getElementById('gallery-add').addEventListener('click', () => {
      if (rows.children.length < 30) rows.append(template.content.cloneNode(true));
    });
    rows.addEventListener('click', (event) => {
      const button = event.target.closest('[data-gallery-action]');
      if (!button) return;
      const row = button.closest('.admin-gallery-row');
      if (button.dataset.galleryAction === 'remove') row.remove();
      if (button.dataset.galleryAction === 'up' && row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
      if (button.dataset.galleryAction === 'down' && row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
    });
    rows.addEventListener('change', (event) => {
      if (!event.target.matches('select[name="gallery_media_ids[]"]')) return;
      const preview = event.target.closest('.admin-gallery-row').querySelector('.admin-gallery-preview');
      const path = event.target.selectedOptions[0]?.dataset.path || '';
      preview.hidden = !path;
      if (path) preview.src = path;
      else preview.removeAttribute('src');
    });
  })();
</script>

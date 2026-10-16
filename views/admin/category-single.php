<header class="admin-page-heading"><div><p class="eyebrow">内容管理 / 单页栏目</p><h1><?= \FangdaysCMS\Cms\View::e($category['name']) ?></h1><p class="muted">编辑此栏目的单页正文，发布后可通过栏目地址访问。</p></div><a class="admin-back-link" href="/admin/categories"><i class="fa fa-angle-left" aria-hidden="true"></i> 返回栏目</a></header>
<section class="panel">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" action="/admin/categories/<?= (int) $category['id'] ?>/single" class="stack js-single-editor-form">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>内容标题<input name="title" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($category['single_title'] ?: $category['name']) ?>"></label>
    <?php $editorBody = $category['single_body']; $editorFormat = $category['single_body_format']; require __DIR__ . '/partials/single-editor.php'; ?>
    <?php if ($fields): ?><fieldset class="admin-model-fields"><legend><?= \FangdaysCMS\Cms\View::e($model['name']) ?> · 扩展字段</legend><div class="stack">
      <?php foreach ($fields as $field): ?>
        <?php $fieldId = (int) $field['id']; $rawFieldValue = $extra_values[$fieldId] ?? $extra_values[(string) $fieldId] ?? ''; $fieldValue = $field['field_type'] === 'multi' && is_array($rawFieldValue) ? $rawFieldValue : (is_string($rawFieldValue) ? $rawFieldValue : ''); ?>
        <?php if ($field['field_type'] === 'textarea'): ?><label><?= \FangdaysCMS\Cms\View::e($field['label']) ?><textarea name="extra[<?= $fieldId ?>]" rows="4" maxlength="5000" <?= $field['is_required'] ? 'required' : '' ?>><?= \FangdaysCMS\Cms\View::e($fieldValue) ?></textarea></label>
        <?php elseif ($field['field_type'] === 'select'): ?><label><?= \FangdaysCMS\Cms\View::e($field['label']) ?><select name="extra[<?= $fieldId ?>]" <?= $field['is_required'] ? 'required' : '' ?>><option value="">请选择</option><?php foreach ($field['options'] as $option): ?><option value="<?= \FangdaysCMS\Cms\View::e($option) ?>" <?= $fieldValue === $option ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($option) ?></option><?php endforeach; ?></select></label>
        <?php elseif ($field['field_type'] === 'multi'): ?><?php $selected = is_array($fieldValue) ? $fieldValue : array_filter(explode(',', (string) $fieldValue)); ?><fieldset><legend><?= \FangdaysCMS\Cms\View::e($field['label']) ?></legend><div class="check-grid"><?php foreach ($field['options'] as $option): ?><label class="check"><input type="checkbox" name="extra[<?= $fieldId ?>][]" value="<?= \FangdaysCMS\Cms\View::e($option) ?>" <?= in_array($option, $selected, true) ? 'checked' : '' ?>><?= \FangdaysCMS\Cms\View::e($option) ?></label><?php endforeach; ?></div></fieldset>
        <?php else: ?><label><?= \FangdaysCMS\Cms\View::e($field['label']) ?><input name="extra[<?= $fieldId ?>]" maxlength="200" value="<?= \FangdaysCMS\Cms\View::e($fieldValue) ?>" <?= $field['is_required'] ? 'required' : '' ?>></label><?php endif; ?>
      <?php endforeach; ?>
    </div></fieldset><?php endif; ?>
    <fieldset class="stack"><legend>展示图片</legend>
      <label>封面图片<select name="cover_media_id"><option value="">无封面</option><?php foreach ($media as $image): ?><option value="<?= (int) $image['id'] ?>" <?= (string) ($category['cover_media_id'] ?? '') === (string) $image['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($image['original_name']) ?> (#<?= (int) $image['id'] ?>)</option><?php endforeach; ?></select></label>
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
    <label>状态<select name="status"><option value="draft" <?= $category['single_status'] === 'draft' ? 'selected' : '' ?>>草稿</option><option value="published" <?= $category['single_status'] === 'published' ? 'selected' : '' ?>>发布</option></select></label>
    <div class="admin-form-actions"><button class="layui-btn" type="submit">保存内容</button><?php if ($category['single_status'] === 'published'): ?><a href="/category/<?= \FangdaysCMS\Cms\View::e($category['slug']) ?>" target="_blank" rel="noopener">查看前台单页 ↗</a><?php endif; ?></div>
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

<header class="admin-page-heading"><div><p class="eyebrow">内容管理 / 单页栏目</p><h1><?= \FangdaysCMS\Cms\View::e($category['name']) ?></h1><p class="muted">编辑此栏目的单页正文，发布后可通过栏目地址访问。</p></div><a class="admin-back-link" href="/admin/categories"><i class="fa fa-angle-left" aria-hidden="true"></i> 返回栏目</a></header>
<section class="panel">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/single" class="stack js-single-editor-form">
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
    <label>状态<select name="status"><option value="draft" <?= $category['single_status'] === 'draft' ? 'selected' : '' ?>>草稿</option><option value="published" <?= $category['single_status'] === 'published' ? 'selected' : '' ?>>发布</option></select></label>
    <div class="admin-form-actions"><button class="layui-btn" type="submit">保存内容</button><?php if ($category['single_status'] === 'published'): ?><a href="/category/<?= \FangdaysCMS\Cms\View::e($category['slug']) ?>" target="_blank" rel="noopener">查看前台单页 ↗</a><?php endif; ?></div>
  </form>
</section>

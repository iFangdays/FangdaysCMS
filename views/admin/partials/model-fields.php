<?php foreach ($modelFields as $field): ?>
  <?php
    $fieldId = (int) $field['id'];
    $rawValue = $extra_values[$fieldId] ?? $extra_values[(string) $fieldId] ?? '';
    $fieldValue = is_string($rawValue) ? $rawValue : '';
    if ($field['field_type'] === 'editor') {
      $fieldValue = \FangdaysCMS\Cms\RichText::clean(mb_strcut($fieldValue, 0, 50000, 'UTF-8'), 'html');
    }
    $disabled = $activeModel ? '' : 'disabled';
    $required = $field['is_required'] ? 'required' : '';
    $label = \FangdaysCMS\Cms\View::e($field['label']);
  ?>
  <?php if ($field['field_type'] === 'textarea'): ?>
    <label><?= $label ?><textarea name="extra[<?= $fieldId ?>]" rows="4" maxlength="5000" <?= $required ?> <?= $disabled ?>><?= \FangdaysCMS\Cms\View::e($fieldValue) ?></textarea></label>
  <?php elseif ($field['field_type'] === 'editor'): ?>
    <div class="admin-body-editor"><label for="extra-editor-text-<?= $fieldId ?>"><?= $label ?></label>
      <textarea class="js-extra-editor" id="extra-editor-text-<?= $fieldId ?>" name="extra[<?= $fieldId ?>]" rows="8" <?= $disabled ?>><?= \FangdaysCMS\Cms\View::e($fieldValue) ?></textarea>
      <div class="rich-editor-host" id="extra-editor-host-<?= $fieldId ?>" hidden></div>
      <small>支持富文本排版；编辑器未加载时可直接填写 HTML。</small>
    </div>
  <?php elseif ($field['field_type'] === 'select'): ?>
    <label><?= $label ?><select name="extra[<?= $fieldId ?>]" <?= $required ?> <?= $disabled ?>><option value="">请选择</option><?php foreach ($field['options'] as $option): ?><option value="<?= \FangdaysCMS\Cms\View::e($option) ?>" <?= $fieldValue === $option ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($option) ?></option><?php endforeach; ?></select></label>
  <?php elseif ($field['field_type'] === 'radio'): ?>
    <fieldset><legend><?= $label ?></legend><div class="check-grid"><?php foreach ($field['options'] as $option): ?><label class="check"><input type="radio" name="extra[<?= $fieldId ?>]" value="<?= \FangdaysCMS\Cms\View::e($option) ?>" <?= $fieldValue === $option ? 'checked' : '' ?> <?= $required ?> <?= $disabled ?>><?= \FangdaysCMS\Cms\View::e($option) ?></label><?php endforeach; ?></div></fieldset>
  <?php elseif ($field['field_type'] === 'multi'): ?>
    <?php $selected = is_array($rawValue) ? $rawValue : array_filter(explode(',', $fieldValue)); ?>
    <fieldset><legend><?= $label ?></legend><div class="check-grid"><?php foreach ($field['options'] as $option): ?><label class="check"><input type="checkbox" name="extra[<?= $fieldId ?>][]" value="<?= \FangdaysCMS\Cms\View::e($option) ?>" <?= in_array($option, $selected, true) ? 'checked' : '' ?> <?= $disabled ?>><?= \FangdaysCMS\Cms\View::e($option) ?></label><?php endforeach; ?></div></fieldset>
  <?php elseif ($field['field_type'] === 'date'): ?>
    <label><?= $label ?><input type="date" name="extra[<?= $fieldId ?>]" value="<?= \FangdaysCMS\Cms\View::e($fieldValue) ?>" <?= $required ?> <?= $disabled ?>></label>
  <?php elseif ($field['field_type'] === 'image'): ?>
    <?php $knownPaths = array_column($media, 'path'); ?>
    <?php $previewPath = preg_match('#^/uploads/[0-9]{4}/[0-9]{2}/[a-f0-9]{32}\.(jpg|png|webp|gif)$#D', $fieldValue) ? $fieldValue : ''; ?>
    <div class="stack"><label><?= $label ?><select name="extra[<?= $fieldId ?>]" data-extra-image <?= $required ?> <?= $disabled ?>><option value="">选择图片</option>
      <?php if ($previewPath !== '' && ! in_array($fieldValue, $knownPaths, true)): ?><option value="<?= \FangdaysCMS\Cms\View::e($fieldValue) ?>" selected>当前图片</option><?php endif; ?>
      <?php foreach ($media as $image): ?><option value="<?= \FangdaysCMS\Cms\View::e($image['path']) ?>" <?= $fieldValue === $image['path'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($image['original_name']) ?> (#<?= (int) $image['id'] ?>)</option><?php endforeach; ?>
    </select></label><img class="admin-gallery-preview" <?= $previewPath !== '' ? 'src="' . \FangdaysCMS\Cms\View::e($previewPath) . '"' : '' ?> alt="" <?= $previewPath === '' ? 'hidden' : '' ?>><small>从<a href="/admin/media">媒体图片</a>上传后选择。</small></div>
  <?php else: ?>
    <label><?= $label ?><input name="extra[<?= $fieldId ?>]" maxlength="200" value="<?= \FangdaysCMS\Cms\View::e($fieldValue) ?>" <?= $required ?> <?= $disabled ?>></label>
  <?php endif; ?>
<?php endforeach; ?>

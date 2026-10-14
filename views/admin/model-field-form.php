<header class="admin-page-heading"><div><p class="eyebrow">内容模型 / <?= \FangdaysCMS\Cms\View::e($model['name']) ?></p><h1><?= \FangdaysCMS\Cms\View::e($pageTitle) ?></h1><p class="muted">字段会出现在使用该模型的文章编辑页。</p></div><a class="admin-back-link" href="/admin/models/<?= (int) $model['id'] ?>/edit">返回模型</a></header>
<section class="panel narrow">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form class="stack" method="post" action="<?= $field && $field['id'] ? '/admin/models/' . (int) $model['id'] . '/fields/' . (int) $field['id'] . '/edit' : '/admin/models/' . (int) $model['id'] . '/fields/new' ?>">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>字段名称<input name="label" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($field['label'] ?? '') ?>" placeholder="产品颜色"></label>
    <label>字段标识<input name="name" required maxlength="64" pattern="ext_[a-z][a-z0-9_]*" value="<?= \FangdaysCMS\Cms\View::e($field['name'] ?? '') ?>" placeholder="ext_color"><small>以 ext_ 开头，仅使用小写字母、数字和下划线。</small></label>
    <label>字段类型<select name="field_type" required><?php foreach (array('text' => '单行文本', 'textarea' => '多行文本', 'select' => '单选', 'multi' => '多选') as $type => $label): ?><option value="<?= $type ?>" <?= ($field['field_type'] ?? 'text') === $type ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
    <label>可选值<textarea name="options_text" rows="4" placeholder="红色,蓝色,绿色"><?= \FangdaysCMS\Cms\View::e($field['options_text'] ?? implode("\n", $field['options'] ?? array())) ?></textarea><small>单选、多选类型必填；逗号或换行分隔。</small></label>
    <label class="check"><input type="checkbox" name="is_required" value="1" <?= ($field['is_required'] ?? false) ? 'checked' : '' ?>> 必填</label>
    <label>排序<input type="number" name="sort_order" min="0" max="1000000" value="<?= \FangdaysCMS\Cms\View::e($field['sort_order'] ?? '0') ?>"></label>
    <div><button class="layui-btn" type="submit">保存字段</button></div>
  </form>
</section>

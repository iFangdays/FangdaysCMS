<header class="admin-page-heading"><div><p class="eyebrow">内容管理 / 内容模型</p><h1><?= \FangdaysCMS\Cms\View::e($pageTitle) ?></h1><p class="muted">设置模型类型、默认模板及内容扩展字段。</p></div><a class="admin-back-link" href="/admin/models">返回模型列表</a></header>
<section class="panel narrow">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form class="stack" method="post" action="<?= $model && $model['id'] ? '/admin/models/' . (int) $model['id'] . '/edit' : '/admin/models' ?>">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>模型名称<input name="name" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($model['name'] ?? '') ?>" placeholder="产品模型"></label>
    <label>模型标识<input name="slug" required maxlength="64" <?= ($model['slug'] ?? '') === '__system_single' ? 'readonly' : 'pattern="[a-z][a-z0-9-]+"' ?> value="<?= \FangdaysCMS\Cms\View::e($model['slug'] ?? '') ?>" placeholder="product"><small>小写字母开头，可用数字和连字符；系统模型标识不可修改。</small></label>
    <label>模型类型<select name="type" id="model-type" <?= in_array($model['slug'] ?? '', array('article', '__system_single'), true) ? 'disabled' : '' ?>><option value="list" <?= ($model['type'] ?? 'list') === 'list' ? 'selected' : '' ?>>列表模型</option><option value="single" <?= ($model['type'] ?? '') === 'single' ? 'selected' : '' ?>>单页模型</option><?php if (($model['type'] ?? '') === 'mixed'): ?><option value="mixed" selected>兼容旧版混合模型</option><?php endif; ?></select><small>单页模型仅使用内容页模板；兼容旧版混合模型保留现有绑定。</small></label>
    <label>默认列表页模板<select name="list_template" id="model-list-template"><option value="">使用内置列表页</option><?php foreach ($templates as $template): ?><option value="<?= \FangdaysCMS\Cms\View::e($template) ?>" <?= ($model['list_template'] ?? '') === $template ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($template) ?></option><?php endforeach; ?></select></label>
    <label>默认内容页模板<select name="content_template"><option value="">使用内置内容页</option><?php foreach ($templates as $template): ?><option value="<?= \FangdaysCMS\Cms\View::e($template) ?>" <?= ($model['content_template'] ?? '') === $template ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($template) ?></option><?php endforeach; ?></select><small>模板来自 templates/site 目录。栏目可以单独覆盖；仅解析已实现的 Pboot 标签。</small></label>
    <div><button class="layui-btn" type="submit">保存模型</button></div>
  </form>
</section>
<?php if ($model && $model['id']): ?><section class="panel narrow" style="margin-top:16px">
  <div class="admin-section-heading"><div><p class="eyebrow">模型字段</p><h2>扩展字段</h2></div><a class="layui-btn layui-btn-sm" href="/admin/models/<?= (int) $model['id'] ?>/fields/new">新增字段</a></div>
  <?php if (! $fields): ?><p class="muted">暂无扩展字段。新增后，绑定此模型的栏目可在文章编辑页填写。</p><?php else: ?>
  <div class="table-wrap"><table class="layui-table admin-card-table"><thead><tr><th>名称</th><th>标识</th><th>类型</th><th>必填</th><th>排序</th><th>操作</th></tr></thead><tbody><?php foreach ($fields as $field): ?><tr>
    <td data-label="名称"><?= \FangdaysCMS\Cms\View::e($field['label']) ?></td>
    <td data-label="标识"><code><?= \FangdaysCMS\Cms\View::e($field['name']) ?></code></td>
    <td data-label="类型"><?= \FangdaysCMS\Cms\View::e($field['field_type']) ?></td>
    <td data-label="必填"><?= $field['is_required'] ? '是' : '否' ?></td>
    <td data-label="排序"><?= (int) $field['sort_order'] ?></td>
    <td data-label="操作" class="actions"><a class="layui-btn layui-btn-xs" href="/admin/models/<?= (int) $model['id'] ?>/fields/<?= (int) $field['id'] ?>/edit">修改</a><form method="post" action="/admin/models/<?= (int) $model['id'] ?>/fields/<?= (int) $field['id'] ?>/delete" class="inline-form" onsubmit="return confirm('删除字段及文章中已填写的值？')"><?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="link-button danger">删除</button></form></td>
  </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section><?php endif; ?>
<script>
  (() => {
    const type = document.getElementById('model-type');
    const list = document.getElementById('model-list-template');
    const sync = () => { if (type?.value === 'single') list.value = ''; list.disabled = type?.value === 'single'; };
    type?.addEventListener('change', sync);
    sync();
  })();
</script>

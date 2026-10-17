<header class="admin-page-heading"><div><p class="eyebrow">内容管理</p><h1>内容模型</h1><p class="muted">为栏目定义文章字段，再在栏目中选择模型。</p></div><a class="layui-btn" href="/admin/models/new"><i class="fa fa-plus" aria-hidden="true"></i> 新建模型</a></header>
<section class="panel">
  <div class="table-wrap"><table class="layui-table admin-card-table"><thead><tr><th>ID</th><th>模型名称</th><th>类型</th><th>列表模板</th><th>内容模板</th><th>标识</th><th>栏目数</th><th>字段数</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($models as $model): ?><tr>
      <td data-label="ID"><?= (int) $model['id'] ?></td>
      <td data-label="模型名称"><strong><?= \FangdaysCMS\Cms\View::e($model['name']) ?></strong></td>
      <td data-label="类型"><?= \FangdaysCMS\Cms\View::e(array('list' => '列表', 'single' => '单页', 'mixed' => '兼容混合')[$model['type']] ?? '列表') ?></td>
      <td data-label="列表模板"><?= \FangdaysCMS\Cms\View::e($model['list_template'] ?: '内置') ?></td>
      <td data-label="内容模板"><?= \FangdaysCMS\Cms\View::e($model['content_template'] ?: '内置') ?></td>
      <td data-label="标识"><code><?= \FangdaysCMS\Cms\View::e($model['slug']) ?></code></td>
      <td data-label="栏目数"><?= (int) $model['category_count'] ?></td>
      <td data-label="字段数"><?= (int) $model['field_count'] ?></td>
      <td data-label="操作" class="actions"><a class="layui-btn layui-btn-xs" href="/admin/models/<?= (int) $model['id'] ?>/edit">编辑</a><?php if (! in_array($model['slug'], array('article', '__system_single'), true)): ?><form method="post" action="/admin/models/<?= (int) $model['id'] ?>/delete" class="inline-form" onsubmit="return confirm('删除此模型及其扩展字段？')"><?= \FangdaysCMS\Cms\View::csrf() ?><button class="link-button danger" type="submit">删除</button></form><?php endif; ?></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>

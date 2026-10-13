<div class="page-heading"><div><p class="eyebrow">内容结构</p><h1>标签管理</h1></div><a class="button" href="/admin/tags/new">新建标签</a></div>
<section class="panel">
  <?php if (! $tags): ?><p class="muted">暂无标签。</p><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>名称</th><th>别名</th><th>操作</th></tr></thead><tbody>
  <?php foreach ($tags as $tag): ?><tr><td><?= \FangdaysCMS\Cms\View::e($tag['name']) ?></td><td><code><?= \FangdaysCMS\Cms\View::e($tag['slug']) ?></code></td><td class="actions">
    <a href="/admin/tags/<?= (int) $tag['id'] ?>/edit">编辑</a>
    <form method="post" action="/admin/tags/<?= (int) $tag['id'] ?>/delete" class="inline-form" onsubmit="return confirm('删除此标签？')"><?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="link-button danger">删除</button></form>
  </td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>

<div class="page-heading"><div><p class="eyebrow">内容结构</p><h1>栏目管理</h1></div><a class="button" href="/admin/categories/new">新建栏目</a></div>
<section class="panel">
  <?php if (! $categories): ?><p class="muted">暂无栏目。创建一个栏目开始组织内容。</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>名称</th><th>别名</th><th>排序</th><th>操作</th></tr></thead><tbody>
      <?php foreach ($categories as $category): ?><tr>
        <td><?= \FangdaysCMS\Cms\View::e($category['name']) ?></td>
        <td><code><?= \FangdaysCMS\Cms\View::e($category['slug']) ?></code></td>
        <td><?= (int) $category['sort_order'] ?></td>
        <td class="actions"><a href="/admin/categories/<?= (int) $category['id'] ?>/edit">编辑</a>
          <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/delete" class="inline-form" onsubmit="return confirm('删除此栏目？栏目内文章会保留。')">
            <?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="link-button danger">删除</button>
          </form>
        </td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

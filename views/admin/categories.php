<div class="page-heading admin-page-heading"><div><p class="eyebrow">内容结构</p><h1>栏目管理</h1><p class="muted">配置文章分类和前台栏目入口。</p></div><a class="button" href="/admin/categories/new">新建栏目</a></div>
<section class="panel">
  <?php if (! $categories): ?><p class="muted">暂无栏目。创建一个栏目开始组织内容。</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>名称</th><th>别名</th><th>文章数</th><th>排序</th><th>操作</th></tr></thead><tbody>
      <?php foreach ($categories as $category): ?><tr>
        <td><strong><?= \FangdaysCMS\Cms\View::e($category['name']) ?></strong></td>
        <td><code><?= \FangdaysCMS\Cms\View::e($category['slug']) ?></code></td>
        <td><?php if ($can['content.view']): ?><a href="/admin/posts?category_id=<?= (int) $category['id'] ?>"><?= (int) $category['post_count'] ?> 篇</a><?php else: ?><?= (int) $category['post_count'] ?> 篇<?php endif; ?></td>
        <td><?= (int) $category['sort_order'] ?></td>
        <td class="actions"><a href="/category/<?= \FangdaysCMS\Cms\View::e($category['slug']) ?>" target="_blank" rel="noopener">查看</a><a href="/admin/categories/<?= (int) $category['id'] ?>/edit">编辑</a>
          <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/delete" class="inline-form" onsubmit="return confirm('删除此栏目？栏目内文章会保留。')">
            <?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="link-button danger">删除</button>
          </form>
        </td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

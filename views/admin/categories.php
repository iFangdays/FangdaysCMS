<div class="layui-tab layui-tab-brief">
  <ul class="layui-tab-title"><li class="layui-this"><a href="/admin/categories">栏目列表</a></li><li><a href="/admin/categories/new">栏目新增</a></li></ul>
  <div class="layui-tab-content">
<section>
  <?php if (! $categories): ?><p class="muted">暂无栏目。创建一个栏目开始组织内容。</p><?php else: ?>
    <div class="table-wrap"><table class="layui-table"><thead><tr><th>ID</th><th>栏目名称</th><th>URL 名称</th><th>文章数</th><th>排序</th><th>操作</th></tr></thead><tbody>
      <?php foreach ($categories as $category): ?><tr>
        <td><?= (int) $category['id'] ?></td>
        <td><i class="fa fa-folder-o" aria-hidden="true"></i> <strong><?= \FangdaysCMS\Cms\View::e($category['name']) ?></strong></td>
        <td><code><?= \FangdaysCMS\Cms\View::e($category['slug']) ?></code></td>
        <td><?php if ($can['content.view']): ?><a href="/admin/posts?category_id=<?= (int) $category['id'] ?>"><?= (int) $category['post_count'] ?> 篇</a><?php else: ?><?= (int) $category['post_count'] ?> 篇<?php endif; ?></td>
        <td><?= (int) $category['sort_order'] ?></td>
        <td class="actions"><a class="layui-btn layui-btn-xs layui-btn-primary" href="/category/<?= \FangdaysCMS\Cms\View::e($category['slug']) ?>" target="_blank" rel="noopener">查看</a><a class="layui-btn layui-btn-xs" href="/admin/categories/<?= (int) $category['id'] ?>/edit">修改</a>
          <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/delete" class="inline-form" onsubmit="return confirm('删除此栏目？栏目内文章会保留。')">
            <?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="link-button danger">删除</button>
          </form>
        </td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
  </div>
</div>

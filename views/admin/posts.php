<div class="page-heading"><div><p class="eyebrow">内容发布</p><h1>文章管理</h1></div><?php if ($can['content.manage']): ?><a class="button" href="/admin/posts/new">新建文章</a><?php endif; ?></div>
<section class="panel">
  <?php if (! $posts): ?><p class="muted">暂无文章。创建文章后可以保存草稿或直接发布。</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>标题</th><th>栏目</th><th>状态</th><th>可见范围</th><th>更新时间</th><?php if ($can['content.manage']): ?><th>操作</th><?php endif; ?></tr></thead><tbody>
      <?php foreach ($posts as $post): ?><tr>
        <td><?= \FangdaysCMS\Cms\View::e($post['title']) ?></td>
        <td><?= \FangdaysCMS\Cms\View::e($post['category_name'] ?? '未分类') ?></td>
        <td><span class="badge"><?= $post['status'] === 'published' ? '已发布' : '草稿' ?></span></td>
        <td><?= $post['visibility'] === 'members' ? '会员' : '公开' ?></td>
        <td><?= date('Y-m-d H:i', (int) $post['updated_at']) ?></td>
        <?php if ($can['content.manage']): ?>
        <td class="actions"><a href="/admin/posts/<?= (int) $post['id'] ?>/edit">编辑</a>
          <form method="post" action="/admin/posts/<?= (int) $post['id'] ?>/delete" class="inline-form" onsubmit="return confirm('确定删除此文章？')">
            <?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="link-button danger">删除</button>
          </form>
        </td>
        <?php endif; ?>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
<?= \FangdaysCMS\Cms\View::pagination($page, $total_pages, '/admin/posts') ?>

<div class="page-heading admin-page-heading">
  <div><p class="eyebrow">内容管理</p><h1>文章管理</h1><p class="muted">检索、编辑与发布站点文章。</p></div>
  <?php if ($can['content.manage']): ?><a class="button" href="/admin/posts/new">新建文章</a><?php endif; ?>
</div>
<section class="panel admin-filter-panel" aria-label="筛选文章">
  <form class="admin-filter-form" method="get" action="/admin/posts">
    <label>关键词<input type="search" name="q" maxlength="100" placeholder="搜索标题或正文" value="<?= \FangdaysCMS\Cms\View::e($filters['q']) ?>"></label>
    <label>栏目<select name="category_id"><option value="">全部栏目</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (string) $filters['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($category['name']) ?></option><?php endforeach; ?></select></label>
    <label>状态<select name="status"><option value="">全部状态</option><option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>已发布</option><option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>草稿</option></select></label>
    <div class="admin-filter-actions"><button type="submit">筛选</button><a href="/admin/posts">重置</a></div>
  </form>
</section>
<section class="panel admin-table-panel">
  <div class="admin-section-heading"><div><h2>文章列表</h2><p class="muted">共 <?= (int) $total ?> 篇文章</p></div></div>
  <?php if (! $posts): ?><div class="admin-empty"><strong><?= $total ? '当前页没有文章' : '没有符合条件的文章' ?></strong><p class="muted">可调整筛选条件，或新建文章。</p></div>
  <?php else: ?>
    <form method="post" action="/admin/posts/bulk">
      <?= \FangdaysCMS\Cms\View::csrf() ?>
      <p class="admin-table-hint">左右滑动表格可查看全部字段和操作。</p>
      <div class="table-wrap"><table><thead><tr>
        <?php if ($can['content.manage']): ?><th><input type="checkbox" id="select-all-posts" aria-label="选择本页全部文章"></th><?php endif; ?>
        <th>标题</th><th>栏目</th><th>状态</th><th>可见范围</th><th>更新时间</th><th>操作</th>
      </tr></thead><tbody>
        <?php foreach ($posts as $post): ?><tr>
          <?php if ($can['content.manage']): ?><td><input type="checkbox" name="post_ids[]" value="<?= (int) $post['id'] ?>" aria-label="选择文章 <?= \FangdaysCMS\Cms\View::e($post['title']) ?>"></td><?php endif; ?>
          <td class="admin-title-cell"><strong><?= \FangdaysCMS\Cms\View::e($post['title']) ?></strong><small>/post/<?= \FangdaysCMS\Cms\View::e($post['slug']) ?></small></td>
          <td><?= \FangdaysCMS\Cms\View::e($post['category_name'] ?? '未分类') ?></td>
          <td><span class="badge <?= $post['status'] === 'published' ? 'badge-success' : 'badge-muted' ?>"><?= $post['status'] === 'published' ? '已发布' : '草稿' ?></span></td>
          <td><?= $post['visibility'] === 'members' ? '仅会员' : '公开' ?></td>
          <td><?= date('Y-m-d H:i', (int) $post['updated_at']) ?></td>
          <td class="actions">
            <?php if ($post['status'] === 'published' && $post['visibility'] === 'public'): ?><a href="/post/<?= \FangdaysCMS\Cms\View::e($post['slug']) ?>" target="_blank" rel="noopener">查看</a><?php endif; ?>
            <?php if ($can['content.manage']): ?><a href="/admin/posts/<?= (int) $post['id'] ?>/edit">编辑</a><?php endif; ?>
          </td>
        </tr><?php endforeach; ?>
      </tbody></table></div>
      <?php if ($can['content.manage']): ?><div class="admin-bulk-bar"><span class="muted">对所选文章</span><button type="submit" name="action" value="publish" class="button-secondary">发布</button><button type="submit" name="action" value="draft" class="button-secondary">转为草稿</button><button type="submit" name="action" value="delete" class="button-danger" onclick="return confirm('确定删除所选文章？此操作无法撤销。')">删除</button></div><?php endif; ?>
    </form>
  <?php endif; ?>
</section>
<?= \FangdaysCMS\Cms\View::pagination($page, $total_pages, '/admin/posts', array_filter($filters, static fn ($value): bool => $value !== '')) ?>
<?php if ($can['content.manage']): ?><script>document.getElementById('select-all-posts')?.addEventListener('change', (event) => { document.querySelectorAll('input[name="post_ids[]"]').forEach((box) => { box.checked = event.target.checked; }); });</script><?php endif; ?>

<header class="admin-page-heading"><div><p class="eyebrow">内容管理</p><h1>文章管理</h1><p class="muted">检索、编辑和发布站点文章。</p></div></header>
<div class="layui-tab layui-tab-brief">
  <ul class="layui-tab-title"><li class="layui-this"><a href="/admin/posts">文章列表</a></li><?php if ($can['content.manage']): ?><li><a href="/admin/posts/new">文章新增</a></li><?php endif; ?></ul>
  <div class="layui-tab-content">
<section class="admin-filter-panel" aria-label="筛选文章">
  <form class="admin-filter-form" method="get" action="/admin/posts">
    <label>关键词<input class="layui-input" type="search" name="q" maxlength="100" placeholder="搜索标题或正文" value="<?= \FangdaysCMS\Cms\View::e($filters['q']) ?>"></label>
    <label>栏目<select name="category_id"><option value="">全部栏目</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (string) $filters['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($category['name']) ?></option><?php endforeach; ?></select></label>
    <label>状态<select name="status"><option value="">全部状态</option><option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>已发布</option><option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>草稿</option></select></label>
    <div class="admin-filter-actions"><button class="layui-btn" type="submit"><i class="fa fa-search" aria-hidden="true"></i> 搜索</button><a class="layui-btn layui-btn-primary" href="/admin/posts">清除搜索</a></div>
  </form>
</section>
<section class="admin-table-panel">
  <p class="muted">共 <?= (int) $total ?> 篇文章</p>
  <?php if (! $posts): ?><div class="admin-empty"><i class="fa fa-file-text-o" aria-hidden="true"></i><strong><?= $total ? '当前页没有文章' : '没有符合条件的文章' ?></strong><p class="muted">可调整筛选条件，或新建文章。</p><a class="layui-btn layui-btn-primary" href="/admin/posts">清除筛选</a><?php if ($can['content.manage']): ?><a class="layui-btn" href="/admin/posts/new">新建文章</a><?php endif; ?></div>
  <?php else: ?>
    <form method="post" action="/admin/posts/bulk">
      <?= \FangdaysCMS\Cms\View::csrf() ?>
      <?php if ($can['content.manage']): ?><label class="admin-mobile-select-all"><input type="checkbox" id="select-all-posts-mobile"> 全选本页</label><?php endif; ?>
      <div class="table-wrap"><table class="layui-table admin-card-table"><thead><tr>
        <?php if ($can['content.manage']): ?><th><input type="checkbox" id="select-all-posts" aria-label="选择本页全部文章"></th><?php endif; ?>
        <th>ID</th><th>栏目</th><th>标题</th><th>更新时间</th><th>状态</th><th>可见范围</th><th>操作</th>
      </tr></thead><tbody>
        <?php foreach ($posts as $post): ?><tr>
          <?php if ($can['content.manage']): ?><td data-label="选择"><input type="checkbox" name="post_ids[]" value="<?= (int) $post['id'] ?>" aria-label="选择文章 <?= \FangdaysCMS\Cms\View::e($post['title']) ?>"></td><?php endif; ?>
          <td data-label="ID"><?= (int) $post['id'] ?></td>
          <td data-label="栏目"><?= \FangdaysCMS\Cms\View::e($post['category_name'] ?? '未分类') ?></td>
          <td data-label="标题" class="admin-title-cell"><span class="admin-cell-value"><strong><?= \FangdaysCMS\Cms\View::e($post['title']) ?></strong><small>/post/<?= \FangdaysCMS\Cms\View::e($post['slug']) ?></small></span></td>
          <td data-label="更新时间"><?= date('Y-m-d H:i', (int) $post['updated_at']) ?></td>
          <td data-label="状态"><span class="badge <?= $post['status'] === 'published' ? 'badge-success' : 'badge-muted' ?>"><?= $post['status'] === 'published' ? '已发布' : '草稿' ?></span></td>
          <td data-label="可见范围"><?= $post['visibility'] === 'members' ? '仅会员' : '公开' ?></td>
          <td data-label="操作" class="actions">
            <?php if ($post['status'] === 'published' && $post['visibility'] === 'public'): ?><a class="layui-btn layui-btn-xs layui-btn-primary" href="/post/<?= \FangdaysCMS\Cms\View::e($post['slug']) ?>" target="_blank" rel="noopener">查看</a><?php endif; ?>
            <?php if ($can['content.manage']): ?><a class="layui-btn layui-btn-xs" href="/admin/posts/<?= (int) $post['id'] ?>/edit">修改</a><?php endif; ?>
          </td>
        </tr><?php endforeach; ?>
      </tbody></table></div>
      <?php if ($can['content.manage']): ?><div class="admin-bulk-bar"><span class="muted">对所选文章</span><button type="submit" name="action" value="publish" class="button-secondary">发布</button><button type="submit" name="action" value="draft" class="button-secondary">转为草稿</button><button type="submit" name="action" value="delete" class="button-danger" onclick="return confirm('确定删除所选文章？此操作无法撤销。')">批量删除</button></div><?php endif; ?>
    </form>
  <?php endif; ?>
</section>
<?= \FangdaysCMS\Cms\View::pagination($page, $total_pages, '/admin/posts', array_filter($filters, static fn ($value): bool => $value !== '')) ?>
  </div>
</div>
<?php if ($can['content.manage']): ?><script>
  (() => {
    const all = [...document.querySelectorAll('input[name="post_ids[]"]')];
    const toggles = [...document.querySelectorAll('#select-all-posts, #select-all-posts-mobile')];
    toggles.forEach((toggle) => toggle.addEventListener('change', () => {
      all.forEach((box) => { box.checked = toggle.checked; });
      toggles.forEach((other) => { other.checked = toggle.checked; });
    }));
    all.forEach((box) => box.addEventListener('change', () => {
      toggles.forEach((toggle) => { toggle.checked = all.every((item) => item.checked); });
    }));
  })();
</script><?php endif; ?>

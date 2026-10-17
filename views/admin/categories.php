<header class="admin-page-heading"><div><p class="eyebrow">内容管理</p><h1>栏目管理</h1><p class="muted">组织文章分类，设置前台栏目入口。</p></div></header>
<div class="layui-tab layui-tab-brief">
  <ul class="layui-tab-title"><li class="layui-this"><a href="/admin/categories">栏目列表</a></li><li><a href="/admin/categories/new">栏目新增</a></li></ul>
  <div class="layui-tab-content">
<section>
  <?php if (! $categories): ?><div class="admin-empty"><strong>暂无栏目</strong><p class="muted">创建一个栏目开始组织内容。</p><a class="layui-btn" href="/admin/categories/new">新建栏目</a></div><?php else: ?>
    <div class="table-wrap"><table class="layui-table admin-card-table"><thead><tr><th>ID</th><th>栏目名称</th><th>类型</th><th>显示</th><th>URL 名称</th><th>内容模型</th><th>文章数（含子栏目）</th><th>排序</th><th>操作</th></tr></thead><tbody>
      <?php foreach ($categories as $category): ?><tr>
        <td data-label="ID"><?= (int) $category['id'] ?></td>
        <td data-label="栏目名称"><span class="admin-cell-value" style="padding-left:<?= min(12, (int) $category['depth']) * 16 ?>px"><i class="fa fa-folder-o" aria-hidden="true"></i> <strong><?= \FangdaysCMS\Cms\View::e($category['name']) ?></strong></span></td>
        <td data-label="类型"><?= \FangdaysCMS\Cms\View::e(array('list' => '列表', 'single' => '单页', 'link' => '跳转')[$category['type']] ?? '列表') ?><?php if ($category['type'] === 'single' && $category['single_status'] !== 'published'): ?> <span class="muted">· 草稿</span><?php endif; ?></td>
        <td data-label="显示"><?= $category['is_visible'] ? '显示' : '<span class="muted">隐藏</span>' ?></td>
        <td data-label="URL 名称"><code><?= \FangdaysCMS\Cms\View::e($category['slug']) ?></code></td>
        <td data-label="内容模型"><?= \FangdaysCMS\Cms\View::e($category['model_name'] ?: '文章模型') ?></td>
        <td data-label="文章数"><?php if ($can['content.view']): ?><a href="/admin/posts?category_id=<?= (int) $category['id'] ?>"><?= (int) $category['post_count'] ?> 篇</a><?php else: ?><?= (int) $category['post_count'] ?> 篇<?php endif; ?></td>
        <td data-label="排序"><?= (int) $category['sort_order'] ?></td>
        <td data-label="操作" class="actions"><?php if ($category['type'] !== 'single' || $category['single_status'] === 'published'): ?><a class="layui-btn layui-btn-xs layui-btn-primary" href="<?= \FangdaysCMS\Cms\View::e(\FangdaysCMS\Cms\View::categoryLink($category)) ?>" target="_blank" rel="noopener">查看</a><?php endif; ?><a class="layui-btn layui-btn-xs" href="/admin/categories/<?= (int) $category['id'] ?>/edit">修改</a><?php if ($category['type'] === 'single'): ?><a class="layui-btn layui-btn-xs" href="/admin/categories/<?= (int) $category['id'] ?>/single">编辑内容</a><?php endif; ?>
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

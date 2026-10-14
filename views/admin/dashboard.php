<blockquote class="layui-elem-quote">当前登录用户：<?= \FangdaysCMS\Cms\View::e($user['username']) ?>（<?= \FangdaysCMS\Cms\View::e($user['role_name']) ?>）　<span class="muted">欢迎进入 <?= \FangdaysCMS\Cms\View::e($siteName) ?> 管理中心</span></blockquote>
<fieldset class="layui-elem-field">
  <legend>内容概览</legend>
  <div class="layui-field-box">
<div class="admin-stats">
  <a class="deskbox admin-stat" href="<?= $can['content.view'] ? '/admin/posts' : '/admin' ?>"><span>全部文章</span><strong><?= (int) $post_count ?></strong><small>站点内容</small></a>
  <a class="deskbox admin-stat" href="<?= $can['content.view'] ? '/admin/posts?status=published' : '/admin' ?>"><span>已发布</span><strong><?= (int) $published_count ?></strong><small>含会员内容</small></a>
  <a class="deskbox admin-stat" href="<?= $can['content.view'] ? '/admin/posts?status=draft' : '/admin' ?>"><span>草稿</span><strong><?= (int) $draft_count ?></strong><small>待继续编辑</small></a>
  <a class="deskbox admin-stat" href="<?= $can['taxonomy.manage'] ? '/admin/categories' : '/admin' ?>"><span>栏目</span><strong><?= (int) $category_count ?></strong><small>内容分类</small></a>
</div>
  </div>
</fieldset>
<div class="admin-dashboard-grid">
  <section class="panel">
    <div class="admin-section-heading"><div><p class="eyebrow">近期文章</p><h2>文章动态</h2></div><?php if ($can['content.view']): ?><a href="/admin/posts">查看全部</a><?php endif; ?></div>
    <?php if (! $can['content.view']): ?><p class="muted">当前角色没有文章查看权限。</p>
    <?php elseif (! $recent_posts): ?><p class="muted">还没有文章。新建一篇文章开始发布内容。</p>
    <?php else: ?><div class="table-wrap"><table class="layui-table admin-recent-table"><thead><tr><th>文章</th><th>状态</th><th>更新时间</th></tr></thead><tbody>
      <?php foreach (array_slice($recent_posts, 0, 5) as $post): ?><tr><td><?php if ($can['content.manage']): ?><a href="/admin/posts/<?= (int) $post['id'] ?>/edit"><?= \FangdaysCMS\Cms\View::e($post['title']) ?></a><?php else: ?><?= \FangdaysCMS\Cms\View::e($post['title']) ?><?php endif; ?></td><td><span class="badge <?= $post['status'] === 'published' ? 'badge-success' : 'badge-muted' ?>"><?= $post['status'] === 'published' ? '已发布' : '草稿' ?></span></td><td><?= date('Y-m-d H:i', (int) $post['updated_at']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
  <aside class="panel admin-quick-panel">
    <p class="eyebrow">常用操作</p><h2>快捷入口</h2>
    <div class="admin-quick-links">
      <?php if ($can['content.manage']): ?><a href="/admin/posts/new">新建文章 <span aria-hidden="true">↗</span></a><a href="/admin/pages/new">新建单页 <span aria-hidden="true">↗</span></a><?php endif; ?>
      <?php if ($can['taxonomy.manage']): ?><a href="/admin/categories/new">新建栏目 <span aria-hidden="true">↗</span></a><?php endif; ?>
      <?php if ($can['media.manage']): ?><a href="/admin/media">上传图片 <span aria-hidden="true">↗</span></a><?php endif; ?>
      <?php if ($can['settings.manage']): ?><a href="/admin/settings">站点设置 <span aria-hidden="true">↗</span></a><?php endif; ?>
    </div>
    <div class="admin-site-state"><span>离线授权</span><strong class="<?= $license['valid'] ? 'state-ok' : 'state-warn' ?>"><?= $license['valid'] ? '已激活' : '待激活' ?></strong></div>
  </aside>
</div>

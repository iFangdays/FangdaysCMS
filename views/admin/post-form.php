<header class="admin-page-heading"><div><p class="eyebrow">内容管理 / 文章</p><h1><?= \FangdaysCMS\Cms\View::e($pageTitle) ?></h1><p class="muted">填写正文、设置栏目与可见范围，然后保存。</p></div><a class="admin-back-link" href="/admin/posts"><i class="fa fa-angle-left" aria-hidden="true"></i> 返回列表</a></header>
<div class="layui-tab layui-tab-brief">
  <ul class="layui-tab-title"><li><a href="/admin/posts">文章列表</a></li><li class="layui-this"><a href="<?= $post && $post['id'] ? '/admin/posts/' . (int) $post['id'] . '/edit' : '/admin/posts/new' ?>"><?= $post && $post['id'] ? '文章修改' : '文章新增' ?></a></li></ul>
  <div class="layui-tab-content">
<?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $post && $post['id'] ? '/admin/posts/' . (int) $post['id'] . '/edit' : '/admin/posts' ?>" class="admin-editor-grid">
  <?= \FangdaysCMS\Cms\View::csrf() ?>
  <section class="panel stack admin-editor-main" aria-label="文章内容">
    <div class="admin-section-heading"><div><p class="eyebrow">基本内容</p><h2>文章信息</h2></div></div>
    <label>文章标题<input name="title" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($post['title'] ?? '') ?>" placeholder="请输入文章标题"></label>
    <div class="field-row">
      <label>URL 别名<input name="slug" required maxlength="160" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="hello-world" value="<?= \FangdaysCMS\Cms\View::e($post['slug'] ?? '') ?>"><small>小写字母、数字与连字符。</small></label>
      <label>栏目<select name="category_id"><option value="">未分类</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (string) ($post['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($category['name']) ?></option><?php endforeach; ?></select><?php if (! $categories): ?><small>可先到栏目管理创建分类。</small><?php endif; ?></label>
    </div>
    <label>正文<textarea name="body" rows="20" placeholder="输入文章正文"><?= \FangdaysCMS\Cms\View::e($post['body'] ?? '') ?></textarea><small>当前正文以纯文本安全展示，换行会保留。</small></label>
    <fieldset><legend>标签</legend><div class="check-grid">
      <?php foreach ($tags as $tag): ?><label class="check"><input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>" <?= in_array((string) $tag['id'], array_map('strval', $selected_tags), true) ? 'checked' : '' ?>><?= \FangdaysCMS\Cms\View::e($tag['name']) ?></label><?php endforeach; ?>
      <?php if (! $tags): ?><p class="muted">暂无标签，可先到标签管理创建。</p><?php endif; ?>
    </div></fieldset>
  </section>
  <aside class="admin-editor-side">
    <section class="panel stack"><div><p class="eyebrow">发布设置</p><h2>状态与范围</h2></div>
      <label>状态<select name="status"><option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>草稿</option><option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>已发布</option></select></label>
      <label>可见范围<select name="visibility"><option value="public" <?= ($post['visibility'] ?? 'public') === 'public' ? 'selected' : '' ?>>公开</option><option value="members" <?= ($post['visibility'] ?? '') === 'members' ? 'selected' : '' ?>>仅会员</option></select></label>
      <button class="layui-btn" type="submit">保存文章</button>
      <?php if ($post && ($post['status'] ?? '') === 'published' && ($post['visibility'] ?? 'public') === 'public'): ?><a href="/post/<?= \FangdaysCMS\Cms\View::e($post['slug']) ?>" target="_blank" rel="noopener">查看已发布页面 ↗</a><?php endif; ?>
    </section>
    <section class="panel stack"><div><p class="eyebrow">展示图片</p><h2>封面</h2></div>
      <label>封面图片<select name="cover_media_id"><option value="">无封面</option><?php foreach ($media as $image): ?><option value="<?= (int) $image['id'] ?>" <?= (string) ($post['cover_media_id'] ?? '') === (string) $image['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($image['original_name']) ?> (#<?= (int) $image['id'] ?>)</option><?php endforeach; ?></select></label>
      <small>先到 <a href="/admin/media">媒体图片</a> 上传，再选择封面。</small>
    </section>
  </aside>
</form>
  </div>
</div>

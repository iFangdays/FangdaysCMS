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
      <label>栏目<select name="category_id"><option value="">未分类</option><?php foreach ($categories as $category): ?><?php if ($category['type'] !== 'list') { continue; } ?><option value="<?= (int) $category['id'] ?>" <?= (string) ($post['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e(str_repeat('— ', min(12, (int) $category['depth'])) . $category['name']) ?></option><?php endforeach; ?></select><?php if (! $categories): ?><small>可先到栏目管理创建分类。</small><?php endif; ?></label>
    </div>
    <label>正文<textarea name="body" rows="20" placeholder="输入文章正文"><?= \FangdaysCMS\Cms\View::e($post['body'] ?? '') ?></textarea><small>当前正文以纯文本安全展示，换行会保留。</small></label>
    <fieldset class="stack"><legend>文章属性与 SEO</legend>
      <label>副标题<input name="subtitle" maxlength="255" value="<?= \FangdaysCMS\Cms\View::e($post['subtitle'] ?? '') ?>"></label>
      <div class="field-row"><label>作者<input name="author" maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($post['author'] ?? '') ?>"></label><label>来源<input name="source" maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($post['source'] ?? '') ?>"></label></div>
      <div class="field-row"><label>标题颜色<input name="title_color" maxlength="7" pattern="#[0-9a-fA-F]{6}" placeholder="#176c73" value="<?= \FangdaysCMS\Cms\View::e($post['title_color'] ?? '') ?>"><small>可选，填写六位十六进制色值。</small></label><label>跳转链接<input name="outlink" maxlength="2048" placeholder="https://example.com/ 或 /contact" value="<?= \FangdaysCMS\Cms\View::e($post['outlink'] ?? '') ?>"><small>填写后列表入口直接跳转，正文页同样跳转。</small></label></div>
      <label>SEO 关键词<input name="seo_keywords" maxlength="255" value="<?= \FangdaysCMS\Cms\View::e($post['seo_keywords'] ?? '') ?>"></label>
      <label>SEO 描述<textarea name="seo_description" rows="3" maxlength="500"><?= \FangdaysCMS\Cms\View::e($post['seo_description'] ?? '') ?></textarea></label>
    </fieldset>
    <?php foreach ($model_form['fields'] as $modelId => $modelFields): ?><?php if ($modelFields): ?>
      <?php $activeModel = (int) $modelId === (int) $model_form['active_id']; ?>
      <fieldset class="admin-model-fields" data-model-id="<?= (int) $modelId ?>" <?= $activeModel ? '' : 'hidden' ?>><legend>模型扩展字段</legend><div class="stack">
        <?php foreach ($modelFields as $field): ?>
          <?php $fieldId = (int) $field['id']; $fieldValue = $extra_values[$fieldId] ?? $extra_values[(string) $fieldId] ?? ''; $disabled = $activeModel ? '' : 'disabled'; ?>
          <?php if ($field['field_type'] === 'textarea'): ?><label><?= \FangdaysCMS\Cms\View::e($field['label']) ?><textarea name="extra[<?= $fieldId ?>]" rows="4" <?= $field['is_required'] ? 'required' : '' ?> <?= $disabled ?>><?= \FangdaysCMS\Cms\View::e($fieldValue) ?></textarea></label>
          <?php elseif ($field['field_type'] === 'select'): ?><label><?= \FangdaysCMS\Cms\View::e($field['label']) ?><select name="extra[<?= $fieldId ?>]" <?= $field['is_required'] ? 'required' : '' ?> <?= $disabled ?>><option value="">请选择</option><?php foreach ($field['options'] as $option): ?><option value="<?= \FangdaysCMS\Cms\View::e($option) ?>" <?= $fieldValue === $option ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($option) ?></option><?php endforeach; ?></select></label>
          <?php elseif ($field['field_type'] === 'multi'): ?><?php $selected = is_array($fieldValue) ? $fieldValue : array_filter(explode(',', (string) $fieldValue)); ?><fieldset><legend><?= \FangdaysCMS\Cms\View::e($field['label']) ?></legend><div class="check-grid"><?php foreach ($field['options'] as $option): ?><label class="check"><input type="checkbox" name="extra[<?= $fieldId ?>][]" value="<?= \FangdaysCMS\Cms\View::e($option) ?>" <?= in_array($option, $selected, true) ? 'checked' : '' ?> <?= $disabled ?>><?= \FangdaysCMS\Cms\View::e($option) ?></label><?php endforeach; ?></div></fieldset>
          <?php else: ?><label><?= \FangdaysCMS\Cms\View::e($field['label']) ?><input name="extra[<?= $fieldId ?>]" maxlength="200" value="<?= \FangdaysCMS\Cms\View::e($fieldValue) ?>" <?= $field['is_required'] ? 'required' : '' ?> <?= $disabled ?>></label><?php endif; ?>
        <?php endforeach; ?>
      </div></fieldset>
    <?php endif; ?><?php endforeach; ?>
    <fieldset><legend>标签</legend><div class="check-grid">
      <?php foreach ($tags as $tag): ?><label class="check"><input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>" <?= in_array((string) $tag['id'], array_map('strval', $selected_tags), true) ? 'checked' : '' ?>><?= \FangdaysCMS\Cms\View::e($tag['name']) ?></label><?php endforeach; ?>
      <?php if (! $tags): ?><p class="muted">暂无标签，可先到标签管理创建。</p><?php endif; ?>
    </div></fieldset>
  </section>
  <aside class="admin-editor-side">
    <section class="panel stack"><div><p class="eyebrow">发布设置</p><h2>状态与范围</h2></div>
      <label>状态<select name="status"><option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>草稿</option><option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>已发布</option></select></label>
      <label>可见范围<select name="visibility"><option value="public" <?= ($post['visibility'] ?? 'public') === 'public' ? 'selected' : '' ?>>公开</option><option value="members" <?= ($post['visibility'] ?? '') === 'members' ? 'selected' : '' ?>>仅会员</option></select></label>
      <fieldset><legend>展示属性</legend><div class="check-grid">
        <label class="check"><input type="hidden" name="is_top" value="0"><input type="checkbox" name="is_top" value="1" <?= (int) ($post['is_top'] ?? 0) === 1 ? 'checked' : '' ?>> 置顶</label>
        <label class="check"><input type="hidden" name="is_recommended" value="0"><input type="checkbox" name="is_recommended" value="1" <?= (int) ($post['is_recommended'] ?? 0) === 1 ? 'checked' : '' ?>> 推荐</label>
        <label class="check"><input type="hidden" name="is_headline" value="0"><input type="checkbox" name="is_headline" value="1" <?= (int) ($post['is_headline'] ?? 0) === 1 ? 'checked' : '' ?>> 头条</label>
      </div></fieldset>
      <button class="layui-btn" type="submit">保存文章</button>
      <?php if ($post && ($post['status'] ?? '') === 'published' && ($post['visibility'] ?? 'public') === 'public'): ?><a href="<?= \FangdaysCMS\Cms\View::e(\FangdaysCMS\Cms\View::postLink($post)) ?>" target="_blank" rel="noopener">查看已发布页面 ↗</a><?php endif; ?>
    </section>
    <section class="panel stack"><div><p class="eyebrow">展示图片</p><h2>封面</h2></div>
      <label>封面图片<select name="cover_media_id"><option value="">无封面</option><?php foreach ($media as $image): ?><option value="<?= (int) $image['id'] ?>" <?= (string) ($post['cover_media_id'] ?? '') === (string) $image['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($image['original_name']) ?> (#<?= (int) $image['id'] ?>)</option><?php endforeach; ?></select></label>
      <small>先到 <a href="/admin/media">媒体图片</a> 上传，再选择封面。</small>
    </section>
  </aside>
</form>
  </div>
</div>
<script>
  (() => {
    const category = document.querySelector('select[name="category_id"]');
    const map = <?= json_encode($model_form['category_models'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const fallback = <?= (int) $model_form['default_id'] ?>;
    const sync = () => {
      const selected = String(map[category.value] || fallback);
      document.querySelectorAll('.admin-model-fields').forEach((group) => {
        const active = group.dataset.modelId === selected;
        group.hidden = !active;
        group.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !active; });
      });
    };
    category.addEventListener('change', sync);
    sync();
  })();
</script>

<header class="admin-page-heading"><div><p class="eyebrow">内容管理 / 栏目</p><h1><?= \FangdaysCMS\Cms\View::e($pageTitle) ?></h1><p class="muted">设置栏目类型、访问地址、SEO 信息与展示顺序。</p></div><a class="admin-back-link" href="/admin/categories"><i class="fa fa-angle-left" aria-hidden="true"></i> 返回列表</a></header>
<div class="layui-tab layui-tab-brief">
  <ul class="layui-tab-title"><li><a href="/admin/categories">栏目列表</a></li><li class="layui-this"><a href="<?= $category && $category['id'] ? '/admin/categories/' . (int) $category['id'] . '/edit' : '/admin/categories/new' ?>"><?= $category && $category['id'] ? '栏目修改' : '栏目新增' ?></a></li></ul>
  <div class="layui-tab-content">
<section class="panel narrow">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= $category && $category['id'] ? '/admin/categories/' . (int) $category['id'] . '/edit' : '/admin/categories' ?>" class="stack">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>栏目名称<input name="name" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($category['name'] ?? '') ?>"></label>
    <label>上级栏目<select name="parent_id"><option value="">顶级栏目</option><?php foreach ($parents as $parent): ?><option value="<?= (int) $parent['id'] ?>" <?= (string) ($category['parent_id'] ?? '') === (string) $parent['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e(str_repeat('— ', min(12, (int) $parent['depth'])) . $parent['name']) ?></option><?php endforeach; ?></select><small>可建立多级栏目；现有 URL 别名保持不变。</small></label>
    <label>URL 别名<input name="slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="company-news" value="<?= \FangdaysCMS\Cms\View::e($category['slug'] ?? '') ?>"><small>使用小写字母、数字和连字符。</small></label>
    <label>栏目类型<select name="type" id="category-type"><option value="list" <?= ($category['type'] ?? 'list') === 'list' ? 'selected' : '' ?>>列表栏目</option><option value="single" <?= ($category['type'] ?? '') === 'single' ? 'selected' : '' ?>>单页栏目</option><option value="link" <?= ($category['type'] ?? '') === 'link' ? 'selected' : '' ?>>跳转链接</option></select><small>列表栏目聚合文章；单页栏目保存后可编辑正文；跳转栏目使用下方链接。</small></label>
    <label>前台显示<select name="is_visible"><option value="1" <?= (string) ($category['is_visible'] ?? '1') === '1' ? 'selected' : '' ?>>显示</option><option value="0" <?= (string) ($category['is_visible'] ?? '1') === '0' ? 'selected' : '' ?>>隐藏</option></select><small>隐藏后不出现在导航和子栏目入口；已发布内容仍可通过原地址访问。</small></label>
    <label>跳转链接<input name="outlink" maxlength="2048" placeholder="https://example.com/ 或 /contact" value="<?= \FangdaysCMS\Cms\View::e($category['outlink'] ?? '') ?>"><small>仅跳转链接类型生效，支持 http(s) 地址或站内绝对路径。</small></label>
    <label>内容模型<select name="model_id" id="category-model" required><?php foreach ($models as $model): ?><option value="<?= (int) $model['id'] ?>" data-type="<?= \FangdaysCMS\Cms\View::e($model['type']) ?>" <?= (string) ($category['model_id'] ?? $models[0]['id']) === (string) $model['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($model['name']) ?>（<?= \FangdaysCMS\Cms\View::e(array('list' => '列表', 'single' => '单页', 'mixed' => '兼容混合')[$model['type']] ?? '列表') ?>）</option><?php endforeach; ?></select><small>模型决定扩展字段；切换模型时自动匹配列表或单页类型。</small></label>
    <fieldset class="stack"><legend>前台模板</legend>
      <label>列表页模板<select name="list_template"><option value="">继承模型默认模板</option><?php foreach ($templates as $template): ?><option value="<?= \FangdaysCMS\Cms\View::e($template) ?>" <?= ($category['list_template'] ?? '') === $template ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($template) ?></option><?php endforeach; ?></select></label>
      <label>内容页模板<select name="content_template"><option value="">继承模型默认模板</option><?php foreach ($templates as $template): ?><option value="<?= \FangdaysCMS\Cms\View::e($template) ?>" <?= ($category['content_template'] ?? '') === $template ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($template) ?></option><?php endforeach; ?></select><small>单页栏目使用内容页模板；未选时继承模型设置，模型也未选时使用内置页面。</small></label>
    </fieldset>
    <fieldset class="stack"><legend>栏目图片</legend>
      <label>栏目缩略图<select name="thumbnail_media_id"><option value="">无缩略图</option><?php foreach ($media as $image): ?><option value="<?= (int) $image['id'] ?>" <?= (string) ($category['thumbnail_media_id'] ?? '') === (string) $image['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($image['original_name']) ?> (#<?= (int) $image['id'] ?>)</option><?php endforeach; ?></select><small>用于子栏目卡片。</small></label>
      <label>栏目大图<select name="banner_media_id"><option value="">无大图</option><?php foreach ($media as $image): ?><option value="<?= (int) $image['id'] ?>" <?= (string) ($category['banner_media_id'] ?? '') === (string) $image['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($image['original_name']) ?> (#<?= (int) $image['id'] ?>)</option><?php endforeach; ?></select><small>用于栏目页顶部。</small></label>
      <small>先到 <a href="/admin/media">媒体图片</a> 上传图片，再在这里选择。</small>
    </fieldset>
    <label>排序<input type="number" name="sort_order" min="0" max="1000000" value="<?= \FangdaysCMS\Cms\View::e($category['sort_order'] ?? '0') ?>"><small>数字越小越靠前；前台导航展示前六个栏目。</small></label>
    <label>SEO 标题<input name="seo_title" maxlength="255" value="<?= \FangdaysCMS\Cms\View::e($category['seo_title'] ?? '') ?>"><small>留空时使用栏目名称和站点名称。</small></label>
    <label>SEO 关键词<input name="seo_keywords" maxlength="255" value="<?= \FangdaysCMS\Cms\View::e($category['seo_keywords'] ?? '') ?>"></label>
    <label>SEO 描述<textarea name="seo_description" rows="3" maxlength="500"><?= \FangdaysCMS\Cms\View::e($category['seo_description'] ?? '') ?></textarea></label>
    <div class="admin-form-actions"><button class="layui-btn" type="submit">保存栏目</button><?php if ($category && $category['id'] && ($category['type'] ?? '') === 'single'): ?><a href="/admin/categories/<?= (int) $category['id'] ?>/single">编辑单页内容 →</a><?php endif; ?><?php if ($category && $category['id'] && (($category['type'] ?? '') !== 'single' || ($category['single_status'] ?? '') === 'published')): ?><a href="<?= \FangdaysCMS\Cms\View::e(\FangdaysCMS\Cms\View::categoryLink($category)) ?>" target="_blank" rel="noopener">查看前台栏目 ↗</a><?php endif; ?></div>
  </form>
</section>
  </div>
</div>
<script>
  (() => {
    const model = document.getElementById('category-model');
    const type = document.getElementById('category-type');
    model?.addEventListener('change', () => {
      const modelType = model.selectedOptions[0]?.dataset.type;
      if (type.value !== 'link' && (modelType === 'list' || modelType === 'single')) type.value = modelType;
    });
    type?.addEventListener('change', () => {
      if (type.value === 'link' || model.selectedOptions[0]?.dataset.type === type.value) return;
      const match = [...model.options].find((option) => option.dataset.type === type.value);
      if (match) model.value = match.value;
    });
  })();
</script>

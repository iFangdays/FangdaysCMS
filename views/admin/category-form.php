<header class="admin-page-heading"><div><p class="eyebrow">内容管理 / 栏目</p><h1><?= \FangdaysCMS\Cms\View::e($pageTitle) ?></h1><p class="muted">设置栏目名称、访问地址与展示顺序。</p></div><a class="admin-back-link" href="/admin/categories"><i class="fa fa-angle-left" aria-hidden="true"></i> 返回列表</a></header>
<div class="layui-tab layui-tab-brief">
  <ul class="layui-tab-title"><li><a href="/admin/categories">栏目列表</a></li><li class="layui-this"><a href="<?= $category && $category['id'] ? '/admin/categories/' . (int) $category['id'] . '/edit' : '/admin/categories/new' ?>"><?= $category && $category['id'] ? '栏目修改' : '栏目新增' ?></a></li></ul>
  <div class="layui-tab-content">
<section class="panel narrow">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= $category && $category['id'] ? '/admin/categories/' . (int) $category['id'] . '/edit' : '/admin/categories' ?>" class="stack">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>栏目名称<input name="name" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($category['name'] ?? '') ?>"></label>
    <label>URL 别名<input name="slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="company-news" value="<?= \FangdaysCMS\Cms\View::e($category['slug'] ?? '') ?>"><small>使用小写字母、数字和连字符。</small></label>
    <label>内容模型<select name="model_id" required><?php foreach ($models as $model): ?><option value="<?= (int) $model['id'] ?>" <?= (string) ($category['model_id'] ?? $models[0]['id']) === (string) $model['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($model['name']) ?></option><?php endforeach; ?></select><small>栏目下文章将使用所选模型的扩展字段。</small></label>
    <label>排序<input type="number" name="sort_order" min="0" max="1000000" value="<?= \FangdaysCMS\Cms\View::e($category['sort_order'] ?? '0') ?>"><small>数字越小越靠前；前台导航展示前六个栏目。</small></label>
    <div class="admin-form-actions"><button class="layui-btn" type="submit">保存栏目</button><?php if ($category && $category['id']): ?><a href="/category/<?= \FangdaysCMS\Cms\View::e($category['slug']) ?>" target="_blank" rel="noopener">查看前台栏目 ↗</a><?php endif; ?></div>
  </form>
</section>
  </div>
</div>

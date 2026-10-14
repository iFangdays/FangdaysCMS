<div class="page-heading admin-page-heading"><div><p class="eyebrow">内容结构</p><h1><?= \FangdaysCMS\Cms\View::e($pageTitle) ?></h1><p class="muted">设置栏目名称、访问地址与展示顺序。</p></div><a href="/admin/categories">返回栏目列表</a></div>
<section class="panel narrow">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= $category && $category['id'] ? '/admin/categories/' . (int) $category['id'] . '/edit' : '/admin/categories' ?>" class="stack">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>栏目名称<input name="name" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($category['name'] ?? '') ?>"></label>
    <label>URL 别名<input name="slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="company-news" value="<?= \FangdaysCMS\Cms\View::e($category['slug'] ?? '') ?>"><small>使用小写字母、数字和连字符。</small></label>
    <label>排序<input type="number" name="sort_order" min="0" max="1000000" value="<?= \FangdaysCMS\Cms\View::e($category['sort_order'] ?? '0') ?>"><small>数字越小越靠前；前台导航展示前六个栏目。</small></label>
    <div class="admin-form-actions"><button type="submit">保存栏目</button><?php if ($category && $category['id']): ?><a href="/category/<?= \FangdaysCMS\Cms\View::e($category['slug']) ?>" target="_blank" rel="noopener">查看前台栏目 ↗</a><?php endif; ?></div>
  </form>
</section>

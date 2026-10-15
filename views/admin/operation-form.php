<?php $slides = $kind === 'slide'; $prefix = $slides ? '/admin/slides' : '/admin/links'; ?>
<header class="admin-page-heading"><div><p class="eyebrow">内容运营 / <?= $slides ? '轮播图' : '友情链接' ?></p><h1><?= \FangdaysCMS\Cms\View::e($pageTitle) ?></h1><p class="muted">分组 1 在当前前台展示，排序数字越小越靠前。</p></div><a class="admin-back-link" href="<?= $prefix ?>"><i class="fa fa-angle-left" aria-hidden="true"></i> 返回列表</a></header>
<div class="layui-tab layui-tab-brief"><ul class="layui-tab-title"><li><a href="<?= $prefix ?>">列表</a></li><li class="layui-this"><a href="<?= $item && $item['id'] ? $prefix . '/' . (int) $item['id'] . '/edit' : $prefix . '/new' ?>"><?= $item && $item['id'] ? '修改' : '新增' ?></a></li></ul><div class="layui-tab-content">
<section class="panel narrow">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= $item && $item['id'] ? $prefix . '/' . (int) $item['id'] . '/edit' : $prefix ?>" class="stack">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <?php if ($slides): ?><label>标题<input name="title" maxlength="150" value="<?= \FangdaysCMS\Cms\View::e($item['title'] ?? '') ?>"></label><label>副标题<input name="subtitle" maxlength="255" value="<?= \FangdaysCMS\Cms\View::e($item['subtitle'] ?? '') ?>"></label>
    <?php else: ?><label>网站名称<input name="name" required maxlength="150" value="<?= \FangdaysCMS\Cms\View::e($item['name'] ?? '') ?>"></label><?php endif; ?>
    <label>图片<?= $slides ? '（必选）' : '（可选）' ?><select name="media_id" <?= $slides ? 'required' : '' ?>><option value=""><?= $slides ? '请选择已上传的图片' : '不使用 Logo' ?></option><?php foreach ($media as $image): ?><option value="<?= (int) $image['id'] ?>" <?= (string) ($item['media_id'] ?? '') === (string) $image['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($image['original_name']) ?> · #<?= (int) $image['id'] ?></option><?php endforeach; ?></select><small>需先到<a href="/admin/media">媒体图片</a>上传；被引用时图片不可删除。</small></label>
    <?php if (! empty($item[$slides ? 'image_path' : 'logo_path'])): ?><img class="operation-preview" src="<?= \FangdaysCMS\Cms\View::e($item[$slides ? 'image_path' : 'logo_path']) ?>" alt="当前图片"><?php endif; ?>
    <label>跳转链接<input name="link" <?= $slides ? '' : 'required' ?> maxlength="2048" placeholder="https://example.com/ 或 /search" value="<?= \FangdaysCMS\Cms\View::e($item['link'] ?? '') ?>"><small>只允许 http(s) 地址或站内绝对路径；轮播图留空时不跳转。</small></label>
    <label>分组 ID<input type="number" name="group_id" required min="1" max="1000000" value="<?= \FangdaysCMS\Cms\View::e($item['group_id'] ?? '1') ?>"></label>
    <label>排序<input type="number" name="sort_order" required min="0" max="1000000" value="<?= \FangdaysCMS\Cms\View::e($item['sort_order'] ?? '255') ?>"></label>
    <div class="admin-form-actions"><button class="layui-btn" type="submit">保存<?= $slides ? '轮播图' : '友情链接' ?></button></div>
  </form>
</section></div></div>

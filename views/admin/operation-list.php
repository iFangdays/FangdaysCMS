<?php $prefix = $kind === 'slide' ? '/admin/slides' : '/admin/links'; ?>
<header class="admin-page-heading"><div><p class="eyebrow">内容运营</p><h1><?= \FangdaysCMS\Cms\View::e($pageTitle) ?></h1><p class="muted">按分组和排序维护<?= $kind === 'slide' ? '首页横幅图片' : '合作站点链接' ?>。分组 1 在当前前台展示。</p></div><a class="layui-btn" href="<?= $prefix ?>/new"><i class="fa fa-plus" aria-hidden="true"></i> 新建<?= \FangdaysCMS\Cms\View::e($pageTitle) ?></a></header>
<div class="layui-tab layui-tab-brief"><ul class="layui-tab-title"><li class="layui-this"><a href="<?= $prefix ?>">列表</a></li><li><a href="<?= $prefix ?>/new">新增</a></li></ul></div>
<section class="panel">
  <?php if (! $items): ?><p class="muted">暂无<?= \FangdaysCMS\Cms\View::e($pageTitle) ?>，可先到<a href="/admin/media">媒体图片</a>上传素材，再创建记录。</p><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>图片</th><th><?= $kind === 'slide' ? '标题' : '名称' ?></th><th>分组</th><th>排序</th><th>链接</th><th>操作</th></tr></thead><tbody>
  <?php foreach ($items as $item): ?><tr>
    <td><?php $image = $kind === 'slide' ? $item['image_path'] : $item['logo_path']; if ($image): ?><img class="operation-thumb" src="<?= \FangdaysCMS\Cms\View::e($image) ?>" alt="" loading="lazy"><?php else: ?><span class="muted">无</span><?php endif; ?></td>
    <td><strong><?= \FangdaysCMS\Cms\View::e($kind === 'slide' ? ($item['title'] ?: $item['image_name']) : $item['name']) ?></strong><?php if ($kind === 'slide' && $item['subtitle'] !== ''): ?><div class="muted"><?= \FangdaysCMS\Cms\View::e($item['subtitle']) ?></div><?php endif; ?></td>
    <td><?= (int) $item['group_id'] ?></td><td><?= (int) $item['sort_order'] ?></td><td><?php if ($item['link'] !== ''): ?><a href="<?= \FangdaysCMS\Cms\View::e($item['link']) ?>" target="_blank" rel="noopener noreferrer"><?= \FangdaysCMS\Cms\View::e(mb_strimwidth($item['link'], 0, 55, '…')) ?></a><?php else: ?><span class="muted">无</span><?php endif; ?></td>
    <td class="actions"><a href="<?= $prefix ?>/<?= (int) $item['id'] ?>/edit">编辑</a><form method="post" action="<?= $prefix ?>/<?= (int) $item['id'] ?>/delete" class="inline-form" onsubmit="return confirm('确定删除此记录？')"><?= \FangdaysCMS\Cms\View::csrf() ?><button class="link-button danger" type="submit">删除</button></form></td>
  </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>

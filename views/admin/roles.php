<div class="page-heading"><div><p class="eyebrow">访问控制</p><h1>角色权限</h1></div><a class="button" href="/admin/roles/new">新建角色</a></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>角色</th><th>标识</th><th>账号数</th><th>操作</th></tr></thead><tbody>
  <?php foreach ($roles as $role): ?><tr><td><?= \FangdaysCMS\Cms\View::e($role['name']) ?></td><td><code><?= \FangdaysCMS\Cms\View::e($role['slug']) ?></code></td><td><?= (int) $role['user_count'] ?></td><td class="actions">
    <?php if ($role['slug'] === 'owner'): ?>固定权限<?php else: ?>
    <a href="/admin/roles/<?= (int) $role['id'] ?>/edit">编辑</a>
    <form method="post" action="/admin/roles/<?= (int) $role['id'] ?>/delete" class="inline-form" onsubmit="return confirm('确定删除该角色？')"><?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="link-button danger">删除</button></form>
    <?php endif; ?>
  </td></tr><?php endforeach; ?>
</tbody></table></div></section>

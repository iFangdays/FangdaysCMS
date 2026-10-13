<div class="page-heading"><div><p class="eyebrow">用户</p><h1>会员管理</h1></div></div>
<section class="panel">
  <?php if (! $members): ?><p class="muted">暂无注册会员。</p><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>昵称</th><th>邮箱</th><th>状态</th><th>注册时间</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($members as $entry): ?><tr><td><?= \FangdaysCMS\Cms\View::e($entry['display_name']) ?></td><td><?= \FangdaysCMS\Cms\View::e($entry['email']) ?></td><td><?= $entry['status'] === 'active' ? '正常' : '停用' ?></td><td><?= date('Y-m-d', (int) $entry['created_at']) ?></td><td>
      <form method="post" action="/admin/members/<?= (int) $entry['id'] ?>/status" class="inline-form"><?= \FangdaysCMS\Cms\View::csrf() ?><input type="hidden" name="status" value="<?= $entry['status'] === 'active' ? 'disabled' : 'active' ?>"><button type="submit" class="link-button"><?= $entry['status'] === 'active' ? '停用' : '启用' ?></button></form>
    </td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>

<div class="page-heading"><div><p class="eyebrow">访问控制</p><h1>后台账号</h1></div></div>
<section class="panel"><h2>现有账号</h2><div class="table-wrap"><table><thead><tr><th>账号</th><th>角色</th><th>创建时间</th><th>操作</th></tr></thead><tbody>
  <?php foreach ($admins as $entry): ?><tr><td><?= \FangdaysCMS\Cms\View::e($entry['username']) ?></td><td><?= \FangdaysCMS\Cms\View::e($entry['role_name']) ?></td><td><?= date('Y-m-d', (int) $entry['created_at']) ?></td><td>
    <?php if ($entry['role_slug'] !== 'owner' || $user['role_slug'] === 'owner'): ?><a href="/admin/admins/<?= (int) $entry['id'] ?>/edit">编辑</a><?php endif; ?>
    <?php if ($entry['role_slug'] !== 'owner' && (int) $entry['id'] !== (int) $user['id']): ?><form method="post" action="/admin/admins/<?= (int) $entry['id'] ?>/delete" class="inline-form" onsubmit="return confirm('确定删除该后台账号？')"><?= \FangdaysCMS\Cms\View::csrf() ?><button type="submit" class="link-button danger">删除</button></form><?php endif; ?>
  </td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="panel section-gap"><h2>新增账号</h2>
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="/admin/admins" class="stack">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <div class="field-row"><label>账号<input name="username" required minlength="3" maxlength="32" autocomplete="off" value="<?= \FangdaysCMS\Cms\View::e($_POST['username'] ?? '') ?>"></label><label>初始密码<input name="password" type="password" required minlength="12" autocomplete="new-password"></label></div>
    <label>角色<select name="role_id" required><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>"><?= \FangdaysCMS\Cms\View::e($role['name']) ?></option><?php endforeach; ?></select></label>
    <button type="submit">创建账号</button>
  </form>
</section>

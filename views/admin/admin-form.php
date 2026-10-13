<div class="page-heading"><div><p class="eyebrow">访问控制</p><h1>编辑后台账号</h1></div><a href="/admin/admins">返回账号</a></div>
<section class="panel narrow"><h2><?= \FangdaysCMS\Cms\View::e($account['username']) ?></h2>
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="/admin/admins/<?= (int) $account['id'] ?>/edit" class="stack"><?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>角色<select name="role_id" required><?php foreach ($roles as $role): ?>
      <?php if ($user['role_slug'] === 'owner' || $role['slug'] !== 'owner'): ?><option value="<?= (int) $role['id'] ?>" <?= (int) $account['role_id'] === (int) $role['id'] ? 'selected' : '' ?>><?= \FangdaysCMS\Cms\View::e($role['name']) ?></option><?php endif; ?>
    <?php endforeach; ?></select></label>
    <label>新密码（留空则不修改）<input name="new_password" type="password" minlength="12" autocomplete="new-password"></label>
    <button type="submit">保存账号</button>
  </form>
</section>

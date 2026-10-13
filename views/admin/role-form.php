<div class="page-heading"><div><p class="eyebrow">访问控制</p><h1><?= \FangdaysCMS\Cms\View::e($pageTitle) ?></h1></div><a href="/admin/roles">返回角色</a></div>
<section class="panel narrow">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= $role && $role['id'] ? '/admin/roles/' . (int) $role['id'] . '/edit' : '/admin/roles' ?>" class="stack">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>角色名称<input name="name" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($role['name'] ?? '') ?>"></label>
    <label>角色标识<input name="slug" required pattern="[a-z][a-z0-9_-]+" value="<?= \FangdaysCMS\Cms\View::e($role['slug'] ?? '') ?>"></label>
    <fieldset><legend>权限</legend><p class="muted">所有后台角色始终可以查看概览。</p><div class="check-grid">
      <?php foreach (\FangdaysCMS\Cms\Permissions::CATALOG as $key => $label): ?><label class="check"><input type="checkbox" name="permissions[]" value="<?= \FangdaysCMS\Cms\View::e($key) ?>" <?= in_array($key, $grants, true) ? 'checked' : '' ?>><?= \FangdaysCMS\Cms\View::e($label) ?></label><?php endforeach; ?>
    </div></fieldset>
    <button type="submit">保存角色</button>
  </form>
</section>

<section class="panel narrow">
  <p class="eyebrow">首次安装</p>
  <h1>创建 FangdaysCMS 站点</h1>
  <p class="muted">默认使用 SQLite。MySQL 可在下方切换。授权密钥 ID 与公钥由站点所有者在独立授权中心提供；私钥不得放在客户站点。</p>
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="/install" class="stack">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>站点名称<input name="site_name" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($input['site_name'] ?? 'FangdaysCMS') ?>"></label>
    <label>站点域名<input name="site_domain" required placeholder="example.com 或 localhost" value="<?= \FangdaysCMS\Cms\View::e($input['site_domain'] ?? 'localhost') ?>"></label>
    <div class="field-row">
      <label>管理员账号<input name="admin_username" required minlength="3" maxlength="32" autocomplete="username" value="<?= \FangdaysCMS\Cms\View::e($input['admin_username'] ?? '') ?>"></label>
      <label>管理员密码<input type="password" name="admin_password" required minlength="12" autocomplete="new-password"></label>
    </div>
    <div class="field-row">
      <label>授权密钥 ID<input name="license_key_id" required value="<?= \FangdaysCMS\Cms\View::e($input['license_key_id'] ?? 'main') ?>"></label>
      <label>授权公钥（Base64）<input name="license_public_key" required value="<?= \FangdaysCMS\Cms\View::e($input['license_public_key'] ?? '') ?>"></label>
    </div>
    <fieldset>
      <legend>数据库</legend>
      <label class="radio"><input type="radio" name="database_driver" value="sqlite" <?= ($input['database_driver'] ?? 'sqlite') === 'sqlite' ? 'checked' : '' ?>> SQLite（默认）</label>
      <label class="radio"><input type="radio" name="database_driver" value="mysql" <?= ($input['database_driver'] ?? '') === 'mysql' ? 'checked' : '' ?>> MySQL</label>
      <div class="field-row">
        <label>MySQL 主机<input name="mysql_host" value="<?= \FangdaysCMS\Cms\View::e($input['mysql_host'] ?? '127.0.0.1') ?>"></label>
        <label>端口<input name="mysql_port" value="<?= \FangdaysCMS\Cms\View::e($input['mysql_port'] ?? '3306') ?>"></label>
      </div>
      <div class="field-row">
        <label>数据库名<input name="mysql_name" value="<?= \FangdaysCMS\Cms\View::e($input['mysql_name'] ?? '') ?>"></label>
        <label>用户名<input name="mysql_user" value="<?= \FangdaysCMS\Cms\View::e($input['mysql_user'] ?? '') ?>"></label>
      </div>
      <label>密码<input type="password" name="mysql_password" autocomplete="new-password"></label>
    </fieldset>
    <button type="submit">安装站点</button>
  </form>
</section>

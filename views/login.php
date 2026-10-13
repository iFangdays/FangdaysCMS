<section class="panel narrow">
  <p class="eyebrow">管理后台</p><h1>登录</h1>
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="/login" class="stack">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>账号<input name="username" required autocomplete="username"></label>
    <label>密码<input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit">登录后台</button>
  </form>
</section>

<section class="panel narrow"><p class="eyebrow">会员</p><h1>注册账号</h1>
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="/member/register" class="stack"><?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>邮箱<input name="email" type="email" required maxlength="191" autocomplete="email" value="<?= \FangdaysCMS\Cms\View::e($email) ?>"></label>
    <label>昵称<input name="display_name" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($display_name) ?>"></label>
    <label>密码（至少 12 位）<input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
    <button type="submit">注册</button>
  </form><p>已有账号？<a href="/member/login">去登录</a></p>
</section>

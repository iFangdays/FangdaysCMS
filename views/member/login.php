<section class="panel narrow"><p class="eyebrow">会员</p><h1>登录</h1>
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="/member/login" class="stack"><?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>邮箱<input name="email" type="email" required autocomplete="email"></label>
    <label>密码<input name="password" type="password" required autocomplete="current-password"></label>
    <button type="submit">登录</button>
  </form><p>还没有账号？<a href="/member/register">注册会员</a></p>
</section>

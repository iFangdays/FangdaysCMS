<div class="user-login">
  <div class="user-login-main">
    <div class="user-login-header">
      <h2>FangdaysCMS</h2>
      <p>后台管理系统</p>
    </div>
    <div class="user-login-box">
      <?php if ($flash): ?><div class="notice" role="status"><?= \FangdaysCMS\Cms\View::e($flash) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
      <form method="post" action="/login" class="layui-form">
        <?= \FangdaysCMS\Cms\View::csrf() ?>
        <div class="layui-form-item">
          <label class="user-login-icon" for="admin-username"><i class="fa fa-user" aria-hidden="true"></i></label>
          <input class="layui-input" id="admin-username" name="username" required autocomplete="username" aria-label="账号" placeholder="管理员账号">
        </div>
        <div class="layui-form-item">
          <label class="user-login-icon" for="admin-password"><i class="fa fa-lock" aria-hidden="true"></i></label>
          <input class="layui-input" id="admin-password" type="password" name="password" required autocomplete="current-password" aria-label="密码" placeholder="登录密码">
        </div>
        <button class="layui-btn layui-btn-fluid" type="submit">登录后台</button>
      </form>
      <p class="admin-login-home"><a href="/">返回网站主页</a></p>
    </div>
  </div>
  <div class="user-login-footer">FangdaysCMS · 内容管理中心</div>
</div>

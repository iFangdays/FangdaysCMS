<section class="panel narrow"><p class="eyebrow">会员中心</p><h1><?= \FangdaysCMS\Cms\View::e($member['display_name']) ?></h1>
  <p class="muted"><?= \FangdaysCMS\Cms\View::e($member['email']) ?> · 注册于 <?= date('Y-m-d', (int) $member['created_at']) ?></p>
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form method="post" action="/member/profile" class="stack"><?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>昵称<input name="display_name" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($member['display_name']) ?>"></label>
    <button type="submit">保存资料</button>
  </form>
</section>
<section class="panel narrow section-gap"><h2>修改密码</h2>
  <form method="post" action="/member/password" class="stack"><?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>当前密码<input name="current_password" type="password" required autocomplete="current-password"></label>
    <label>新密码（至少 12 位）<input name="new_password" type="password" required minlength="12" autocomplete="new-password"></label>
    <button type="submit">更新密码</button>
  </form>
</section>

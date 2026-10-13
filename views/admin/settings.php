<section class="panel narrow"><p class="eyebrow">网站</p><h1>站点设置</h1>
<?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
<form class="stack" method="post" action="/admin/settings">
  <?= \FangdaysCMS\Cms\View::csrf() ?>
  <label>站点名称<input name="site_name" required maxlength="100" value="<?= \FangdaysCMS\Cms\View::e($settings['site_name']) ?>"></label>
  <label>站点简介<textarea name="description" rows="4" maxlength="300"><?= \FangdaysCMS\Cms\View::e($settings['description']) ?></textarea></label>
  <label>页脚文字<input name="footer_text" maxlength="150" value="<?= \FangdaysCMS\Cms\View::e($settings['footer_text']) ?>"></label>
  <p class="muted">简介用于前台页面描述。授权绑定的域名仍在安装配置中，修改站点名称不会改变授权。</p>
  <button type="submit">保存设置</button>
</form></section>

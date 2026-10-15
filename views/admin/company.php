<?php
$fields = array('name' => '公司名称', 'address' => '地址', 'postcode' => '邮编', 'contact' => '联系人', 'mobile' => '手机', 'phone' => '电话', 'fax' => '传真', 'email' => '邮箱', 'qq' => 'QQ', 'weixin' => '微信', 'blicense' => '营业执照号');
$lengths = array('name' => 150, 'address' => 255, 'postcode' => 30, 'contact' => 100, 'mobile' => 50, 'phone' => 50, 'fax' => 50, 'email' => 191, 'qq' => 50, 'weixin' => 255, 'blicense' => 255);
?>
<header class="admin-page-heading"><div><p class="eyebrow">内容运营</p><h1>公司信息</h1><p class="muted">集中维护企业资料，前台页脚会展示公司名称与联系方式。</p></div></header>
<section class="panel company-panel">
  <?php if ($error): ?><div class="error" role="alert"><?= \FangdaysCMS\Cms\View::e($error) ?></div><?php endif; ?>
  <form class="stack company-grid" method="post" action="/admin/company">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <?php foreach ($fields as $key => $label): ?>
      <label class="<?= in_array($key, array('name', 'address'), true) ? 'company-wide' : '' ?>"><?= \FangdaysCMS\Cms\View::e($label) ?><input name="<?= $key ?>" <?= $key === 'email' ? 'type="email"' : '' ?> maxlength="<?= $lengths[$key] ?>" value="<?= \FangdaysCMS\Cms\View::e($company[$key] ?? '') ?>"></label>
    <?php endforeach; ?>
    <label class="company-wide">其他说明<textarea name="other" rows="5" maxlength="2000"><?= \FangdaysCMS\Cms\View::e($company['other'] ?? '') ?></textarea></label>
    <div class="admin-form-actions company-wide"><button class="layui-btn" type="submit">保存公司信息</button></div>
  </form>
</section>

<div class="page-heading"><div><p class="eyebrow">独立授权</p><h1>离线授权码</h1></div></div>
<section class="panel narrow">
  <p class="muted">请向站点所有者获取本域名的授权码，并在下方粘贴激活。授权码可在独立的 FangdaysCMS 授权中心签发；此客户站点不保存签发私钥。</p>
  <p>当前状态：<strong><?= $license['valid'] ? '有效' : \FangdaysCMS\Cms\View::e($license['reason']) ?></strong></p>
  <?php if ($license['valid']): ?>
    <p class="muted">授权 ID：<?= \FangdaysCMS\Cms\View::e($license['claims']['license_id']) ?></p>
    <p class="muted">有效域名：<?= \FangdaysCMS\Cms\View::e(implode('、', $license['claims']['domains'])) ?></p>
    <p class="muted">到期时间：<?= $license['claims']['expires_at'] === null ? '永久' : gmdate('Y-m-d H:i:s', $license['claims']['expires_at']) . ' UTC' ?></p>
  <?php endif; ?>
  <form method="post" action="/admin/license" class="stack">
    <?= \FangdaysCMS\Cms\View::csrf() ?>
    <label>粘贴离线授权码<textarea name="code" rows="7" required spellcheck="false"></textarea></label>
    <button type="submit">验证并激活</button>
  </form>
</section>

<div class="page-heading"><div><p class="eyebrow">管理后台</p><h1>概览</h1></div></div>
<div class="stats">
  <div class="panel stat"><strong><?= (int) $category_count ?></strong><span>栏目</span></div>
  <div class="panel stat"><strong><?= (int) $post_count ?></strong><span>内容</span></div>
  <div class="panel stat"><strong><?= $license['valid'] ? '有效' : '待激活' ?></strong><span>离线授权</span></div>
</div>
<section class="panel"><h2>当前账号</h2><p><?= \FangdaysCMS\Cms\View::e($user['username']) ?> · <?= \FangdaysCMS\Cms\View::e($user['role_name']) ?></p><p>前台仅展示已发布内容；会员专属文章需要会员登录。</p></section>

<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use FangdaysCMS\Licensing\IssuerRegistry;

function issuerEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function issuerRedirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (! in_array($remote, array('127.0.0.1', '::1'), true)
    || ! preg_match('/^(?:127\.0\.0\.1|localhost)(?::[0-9]{1,5})?$/D', $host)) {
    http_response_code(403);
    exit('Only the local issuer console is available.');
}

try {
    $issuerHome = IssuerRegistry::defaultDirectory();
    $registry = new IssuerRegistry($issuerHome);
    $sessionDirectory = $issuerHome . '/sessions';
    if (! is_dir($sessionDirectory) && ! mkdir($sessionDirectory, 0700, true) && ! is_dir($sessionDirectory)) {
        throw new RuntimeException('Cannot create private session directory.');
    }
    if ((fileperms($sessionDirectory) & 0077) !== 0) {
        throw new RuntimeException('Session directory must be private.');
    }
} catch (Throwable $error) {
    http_response_code(503);
    echo '<!doctype html><html lang="zh-CN"><meta charset="utf-8"><title>授权中心未初始化</title><body><h1>授权中心未初始化</h1><p>先在签发机运行 <code>php bin/issuer.php setup /path/to/private.key KEY_ID</code>。</p></body></html>';
    exit;
}

ini_set('session.use_strict_mode', '1');
session_save_path($sessionDirectory);
session_name('fangdays_issuer');
session_set_cookie_params(array('httponly' => true, 'samesite' => 'Strict', 'path' => '/'));
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(24));

$error = null;
$action = (string) ($_POST['action'] ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (! hash_equals((string) $_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        exit('Invalid request token.');
    }
    try {
        if ($action === 'login') {
            if (! $registry->authenticate((string) ($_POST['password'] ?? ''))) {
                throw new RuntimeException('密码不正确。');
            }
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(24));
            issuerRedirect('/');
        }
        if (empty($_SESSION['authenticated'])) {
            issuerRedirect('/');
        }
        if ($action === 'logout') {
            $_SESSION = array();
            session_destroy();
            issuerRedirect('/');
        }
        if ($action === 'issue') {
            $result = $registry->issue((string) ($_POST['domains'] ?? ''), (string) ($_POST['customer'] ?? ''), (string) ($_POST['expires'] ?? ''), (string) ($_POST['notes'] ?? ''));
            issuerRedirect('/?id=' . $result['claims']['license_id'] . '&created=1');
        }
        if ($action === 'import') {
            $result = $registry->import((string) ($_POST['code'] ?? ''), (string) ($_POST['customer'] ?? ''), (string) ($_POST['notes'] ?? ''));
            issuerRedirect('/?id=' . $result['claims']['license_id'] . '&imported=1');
        }
        if ($action === 'update') {
            $id = (string) ($_POST['license_id'] ?? '');
            $registry->updateMeta($id, (string) ($_POST['customer'] ?? ''), (string) ($_POST['notes'] ?? ''));
            issuerRedirect('/?id=' . rawurlencode($id) . '&saved=1');
        }
        throw new RuntimeException('未知操作。');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        http_response_code(422);
    }
}

$authenticated = ! empty($_SESSION['authenticated']);
$search = trim((string) ($_GET['q'] ?? ''));
$selected = null;
$rows = array();
if ($authenticated) {
    try {
        $rows = $registry->list($search);
        $id = (string) ($_GET['id'] ?? '');
        if ($id !== '') {
            $selected = $registry->get($id);
            if ($selected === null) {
                http_response_code(404);
                $error = '这条授权记录不存在。';
            }
        }
    } catch (Throwable $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    }
}
?><!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FangdaysCMS 授权中心</title>
  <link rel="stylesheet" href="/app.css">
  <script src="/app.js" defer></script>
</head>
<body>
  <header class="topbar"><div class="wrap topbar-inner"><a class="brand" href="/">FangdaysCMS <span>授权中心</span></a>
  <?php if ($authenticated): ?><form method="post"><input type="hidden" name="csrf" value="<?= issuerEscape($_SESSION['csrf']) ?>"><button class="quiet" name="action" value="logout">退出</button></form><?php endif; ?></div></header>
  <main class="wrap">
    <?php if ($error !== null): ?><p class="alert error" role="alert"><?= issuerEscape($error) ?></p><?php endif; ?>
    <?php if (! $authenticated): ?>
      <section class="card login"><p class="eyebrow">签发机专用</p><h1>登录授权中心</h1><p class="muted">仅在本机开放。登录密码由首次初始化命令生成。</p>
        <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= issuerEscape($_SESSION['csrf']) ?>">
          <label>管理密码<input type="password" name="password" autocomplete="current-password" required autofocus></label>
          <button name="action" value="login">登录</button>
        </form>
      </section>
    <?php else: ?>
      <div class="heading"><div><p class="eyebrow">离线授权 · 集中记录</p><h1>签发与管理</h1><p class="muted">已登记 <?= $registry->count() ?> 条授权。记录显示签发情况，客户服务器不会自动回报激活状态。</p></div><div class="counter"><?= $registry->count() ?><small>条签发记录</small></div></div>
      <?php if (isset($_GET['created'])): ?><p class="alert">授权码已生成并保存到签发记录。复制下方授权码，交给客户在其站点后台“授权”页激活。</p><?php endif; ?>
      <?php if (isset($_GET['imported'])): ?><p class="alert">已有授权码已导入。</p><?php endif; ?>
      <?php if (isset($_GET['saved'])): ?><p class="alert">客户信息已保存。</p><?php endif; ?>

      <?php if ($selected !== null): ?>
        <section class="card detail"><div class="section-heading"><div><p class="eyebrow">授权详情</p><h2><?= issuerEscape($selected['domains']) ?></h2></div><a href="/">返回列表</a></div>
          <dl class="facts"><div><dt>客户</dt><dd><?= issuerEscape($selected['customer'] !== '' ? $selected['customer'] : '未填写') ?></dd></div><div><dt>签发时间</dt><dd><?= issuerEscape(gmdate('Y-m-d H:i', (int) $selected['issued_at'])) ?> UTC</dd></div><div><dt>到期时间</dt><dd><?= $selected['expires_at'] === null ? '永久' : issuerEscape(gmdate('Y-m-d H:i', (int) $selected['expires_at'])) . ' UTC' ?></dd></div><div><dt>密钥 ID</dt><dd><?= issuerEscape($selected['key_id']) ?></dd></div></dl>
          <label class="stack"><span>授权码</span><textarea id="license-code" rows="6" readonly spellcheck="false"><?= issuerEscape($selected['code']) ?></textarea></label><button type="button" class="copy" data-copy="license-code">复制授权码</button>
          <form method="post" class="stack meta-form"><input type="hidden" name="csrf" value="<?= issuerEscape($_SESSION['csrf']) ?>"><input type="hidden" name="license_id" value="<?= issuerEscape($selected['license_id']) ?>">
            <label>客户或项目名称<input name="customer" maxlength="100" value="<?= issuerEscape($selected['customer']) ?>"></label>
            <label>备注<textarea name="notes" rows="2" maxlength="2000"><?= issuerEscape($selected['notes']) ?></textarea></label>
            <button name="action" value="update">保存备注</button>
          </form>
        </section>
      <?php endif; ?>

      <div class="columns">
        <section class="card"><p class="eyebrow">一步签发</p><h2>生成授权码</h2><p class="muted">签发不依赖客户站点是否已经安装。域名不含协议和端口；多个域名用英文逗号分隔。</p>
          <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= issuerEscape($_SESSION['csrf']) ?>">
            <label>客户或项目名称<input name="customer" maxlength="100" value="<?= issuerEscape($action === 'issue' ? ($_POST['customer'] ?? '') : '') ?>" placeholder="例如：甲方官网"></label>
            <label>授权域名<input name="domains" required value="<?= issuerEscape($action === 'issue' ? ($_POST['domains'] ?? '') : '') ?>" placeholder="example.com,www.example.com"></label>
            <label>到期日期（UTC，可留空为永久）<input type="date" name="expires" value="<?= issuerEscape($action === 'issue' ? ($_POST['expires'] ?? '') : '') ?>"></label>
            <label>备注<textarea name="notes" rows="2" maxlength="2000" placeholder="合同、项目、联系人等内部备注"><?= issuerEscape($action === 'issue' ? ($_POST['notes'] ?? '') : '') ?></textarea></label>
            <button name="action" value="issue">生成并登记授权码</button>
          </form>
        </section>
        <section class="card"><p class="eyebrow">新站点安装时使用</p><h2>公钥配置</h2><p class="muted">把这两项填在客户站点安装页。私钥只在当前签发机上保存。</p>
          <label class="stack"><span>授权密钥 ID</span><input id="key-id" value="<?= issuerEscape($registry->keyId()) ?>" readonly></label>
          <label class="stack"><span>授权公钥（Base64）</span><textarea id="public-key" rows="3" readonly><?= issuerEscape($registry->publicKey()) ?></textarea></label>
          <div class="button-row"><button type="button" class="copy" data-copy="key-id">复制密钥 ID</button><button type="button" class="copy" data-copy="public-key">复制公钥</button></div>
          <details><summary>导入以前签发的授权码</summary><form method="post" class="stack import-form"><input type="hidden" name="csrf" value="<?= issuerEscape($_SESSION['csrf']) ?>"><label>客户或项目名称<input name="customer" maxlength="100"></label><label>授权码<textarea name="code" rows="5" required spellcheck="false"></textarea></label><label>备注<input name="notes" maxlength="2000"></label><button name="action" value="import">验证并导入</button></form></details>
        </section>
      </div>

      <section class="card records"><div class="section-heading"><div><p class="eyebrow">已签发站点</p><h2>授权记录</h2></div><form method="get" class="search"><input name="q" value="<?= issuerEscape($search) ?>" placeholder="搜索域名、客户或备注" aria-label="搜索授权记录"><button>搜索</button></form></div>
        <div class="table-wrap"><table><thead><tr><th>客户</th><th>域名</th><th>签发时间</th><th>到期时间</th><th>签发状态</th><th></th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr><td><?= issuerEscape($row['customer'] !== '' ? $row['customer'] : '未填写') ?></td><td><?= issuerEscape($row['domains']) ?></td><td><?= issuerEscape(gmdate('Y-m-d', (int) $row['issued_at'])) ?></td><td><?= $row['expires_at'] === null ? '永久' : issuerEscape(gmdate('Y-m-d', (int) $row['expires_at'])) ?></td><td><span class="badge <?= $row['expires_at'] !== null && (int) $row['expires_at'] < time() ? 'expired' : '' ?>"><?= $row['expires_at'] !== null && (int) $row['expires_at'] < time() ? '已到期' : '有效期内' ?></span></td><td><a href="/?id=<?= issuerEscape($row['license_id']) ?>">查看</a></td></tr><?php endforeach; ?>
        <?php if ($rows === array()): ?><tr><td colspan="6" class="empty">暂无匹配的签发记录。</td></tr><?php endif; ?></tbody></table></div>
        <p class="muted footnote">最多显示最近 500 条匹配记录。离线授权无法实时显示客户服务器是否已激活，也无法远程即时撤销。</p>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>

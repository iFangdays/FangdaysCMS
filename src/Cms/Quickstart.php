<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use FangdaysCMS\Licensing\LicenseCode;
use FangdaysCMS\Licensing\LicenseStore;
use RuntimeException;

/** Local provisioning helper. The signing key always stays outside this project. */
final class Quickstart
{
    public static function install(string $root, string $keyFile, string $domain = '127.0.0.1', string $siteName = 'FangdaysCMS'): array
    {
        if (is_file($root . '/config/app.php')) {
            throw new RuntimeException('站点已经安装，快速安装不会覆盖现有数据。');
        }
        $domain = LicenseCode::normalizeDomain($domain) ?? throw new RuntimeException('站点域名无效。');
        $directory = dirname($keyFile);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('无法创建签发密钥目录。');
        }
        $project = realpath($root);
        $keyDirectory = realpath($directory);
        if ($project === false || $keyDirectory === false || $keyDirectory === $project || str_starts_with($keyDirectory, $project . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('签发私钥必须位于 FangdaysCMS 项目目录之外。');
        }
        $keyFile = $keyDirectory . DIRECTORY_SEPARATOR . basename($keyFile);
        if (is_file($keyFile)) {
            $encoded = file_get_contents($keyFile);
            $secret = $encoded === false ? false : base64_decode(trim($encoded), true);
            if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
                throw new RuntimeException('现有签发私钥无效，请检查密钥文件。');
            }
        } else {
            $pair = sodium_crypto_sign_keypair();
            $secret = sodium_crypto_sign_secretkey($pair);
            $oldUmask = umask(0077);
            $handle = @fopen($keyFile, 'x');
            umask($oldUmask);
            if ($handle === false) {
                throw new RuntimeException('无法创建签发私钥文件。');
            }
            $bytes = base64_encode($secret) . "\n";
            try {
                if (fwrite($handle, $bytes) !== strlen($bytes)) {
                    throw new RuntimeException('签发私钥写入不完整。');
                }
            } catch (\Throwable $error) {
                fclose($handle);
                unlink($keyFile);
                throw $error;
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
            chmod($keyFile, 0600);
        }
        $public = base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret));
        $password = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        Installer::install(array(
            'site_name' => $siteName,
            'site_domain' => $domain,
            'admin_username' => 'admin',
            'admin_password' => $password,
            'license_key_id' => 'main',
            'license_public_key' => $public,
            'database_driver' => 'sqlite'
        ), $root);
        $config = require $root . '/config/app.php';
        $pdo = Database::connect($config['database']);
        $content = new ContentRepository($pdo);
        $newsId = $content->saveCategory(null, '网站动态', 'news', 10);
        $content->savePost(null, $newsId, '欢迎使用 FangdaysCMS', 'welcome', '站点已经安装完成。登录后台后，可以修改栏目、发布文章、管理标签与会员，并维护角色权限。', 'published');
        (new PageRepository($pdo))->save(null, '关于我们', 'about', '请在后台「单页」中编辑这里的介绍、联系方式与服务内容。', 'published', 10);
        (new SettingsRepository($pdo))->save($siteName, $siteName . ' 的最新动态与专题内容。', 'Powered by FangdaysCMS');
        $now = time();
        $code = LicenseCode::issue(array(
            'schema' => 1,
            'product' => 'FangdaysCMS',
            'key_id' => 'main',
            'license_id' => bin2hex(random_bytes(16)),
            'domains' => array($domain),
            'features' => array('publish'),
            'issued_at' => $now,
            'not_before' => $now,
            'expires_at' => null
        ), $secret);
        $store = new LicenseStore($config['license_file'], $config['license_public_keys'], $domain);
        if (! $store->activate($code)['valid']) {
            throw new RuntimeException('站点已安装，但本地授权激活失败。');
        }
        return array('domain' => $domain, 'username' => 'admin', 'password' => $password, 'key_file' => $keyFile);
    }
}

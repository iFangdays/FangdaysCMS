<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/autoload.php';

use FangdaysCMS\Cms\ContentRepository;
use FangdaysCMS\Cms\ContentModelRepository;
use FangdaysCMS\Cms\Database;
use FangdaysCMS\Cms\Installer;
use FangdaysCMS\Cms\Migrator;
use FangdaysCMS\Cms\TagRepository;
use FangdaysCMS\Cms\MemberRepository;
use FangdaysCMS\Cms\AccessRepository;
use FangdaysCMS\Cms\Permissions;
use FangdaysCMS\Cms\Quickstart;
use FangdaysCMS\Cms\PageRepository;
use FangdaysCMS\Cms\SettingsRepository;
use FangdaysCMS\Cms\MediaRepository;
use FangdaysCMS\Licensing\LicenseCode;
use FangdaysCMS\Licensing\LicenseStore;

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function removeTestDirectory(string $directory): void
{
    foreach (new DirectoryIterator($directory) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        if ($entry->isDir()) {
            removeTestDirectory($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
}

$root = sys_get_temp_dir() . '/fangdays-test-' . bin2hex(random_bytes(8));
mkdir($root . '/config', 0700, true);
mkdir($root . '/var', 0700, true);

try {
    $pair = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($pair);
    $public = base64_encode(sodium_crypto_sign_publickey($pair));
    Installer::install(array(
        'site_name' => '测试站点',
        'site_domain' => 'example.com',
        'admin_username' => 'owner',
        'admin_password' => 'strong-test-password-123',
        'license_key_id' => 'main',
        'license_public_key' => $public,
        'database_driver' => 'sqlite'
    ), $root);
    $config = require $root . '/config/app.php';
    check($config['site_domain'] === 'example.com', 'Installer did not save canonical domain.');
    $pdo = Database::connect($config['database']);
    $user = $pdo->query('SELECT username, password_hash FROM users')->fetch();
    check($user['username'] === 'owner' && password_verify('strong-test-password-123', $user['password_hash']), 'Admin account was not installed.');

    $repo = new ContentRepository($pdo);
    $categoryId = $repo->saveCategory(null, '新闻', 'news', 1);
    $postId = $repo->savePost(null, $categoryId, '第一篇文章', 'first-post', '<script>alert(1)</script>', 'draft');
    check(count($repo->posts('published')) === 0, 'Draft appeared in published listing.');
    $repo->savePost($postId, $categoryId, '第一篇文章', 'first-post', '正文', 'published');
    check($repo->publishedPostBySlug('first-post')['title'] === '第一篇文章', 'Published post lookup failed.');
    check(count($repo->posts('published', $categoryId)) === 1, 'Category listing failed.');
    check((int) $repo->categoriesWithPostCounts()[0]['post_count'] === 1, 'Category post count failed.');
    $repo->deleteCategory($categoryId);
    check($repo->post($postId)['category_id'] === null, 'Deleting category should keep its articles.');
    $models = new ContentModelRepository($pdo);
    check($models->defaultModel()['slug'] === 'article', 'Default content model is missing.');
    $productModel = $models->saveModel(null, '产品模型', 'product');
    $colorField = $models->saveField(null, $productModel, 'ext_color', '颜色', 'select', "红色\n蓝色", true, 1);
    $sizeField = $models->saveField(null, $productModel, 'ext_sizes', '尺码', 'multi', 'S,M,L', false, 2);
    $productCategory = $repo->saveCategory(null, '产品', 'products', 2);
    $models->bindCategory($productCategory, $productModel);
    check((int) $models->modelForCategory($productCategory)['id'] === $productModel, 'Category model binding failed.');
    $productPost = $repo->savePost(null, $productCategory, '测试产品', 'test-product', '产品正文', 'draft');
    $models->setValuesForPost($productPost, $productCategory, array($colorField => '红色', $sizeField => array('S', 'M')));
    check($models->valuesForPost($productPost)[$sizeField] === 'S,M', 'Multiple choice values were not saved.');
    check($models->publicFieldsForPost($productPost, $productCategory)[0]['value'] === '红色', 'Model field was not exposed to the public post.');
    check($repo->countPosts('draft', null, true, null, '红色') === 1, 'Current model field was not searchable.');
    try {
        $models->setValuesForPost($productPost, $productCategory, array($colorField => '绿色'));
        throw new RuntimeException('Undefined model option was accepted.');
    } catch (InvalidArgumentException $error) {
        check(str_contains($error->getMessage(), '未定义'), 'Undefined model option failed for the wrong reason.');
    }
    check($models->valuesForPost($productPost)[$colorField] === '红色', 'Invalid input changed saved fields.');
    $repo->savePost($productPost, null, '测试产品', 'test-product', '产品正文', 'draft');
    $models->setValuesForPost($productPost, null, array());
    check($models->valuesForPost($productPost)[$colorField] === '红色', 'Switching model erased the previous field value.');
    check($repo->countPosts('draft', null, true, null, '红色') === 0, 'Inactive model field appeared in search.');
    try {
        $models->deleteModel($productModel);
        throw new RuntimeException('Bound model was deleted.');
    } catch (InvalidArgumentException $error) {
        check(str_contains($error->getMessage(), '栏目'), 'Bound model deletion failed for the wrong reason.');
    }
    $models->saveField($colorField, $productModel, 'ext_color', '商品颜色', 'select', '红色,蓝色', true, 1);
    check($models->field($colorField)['label'] === '商品颜色', 'Model field update failed.');
    $models->bindCategory($productCategory, (int) $models->defaultModel()['id']);
    $models->deleteModel($productModel);
    check($models->model($productModel) === null && $models->valuesForPost($productPost) === array(), 'Model deletion did not clear field definitions and values.');
    $tags = new TagRepository($pdo);
    $tagId = $tags->save(null, '专题', 'topic');
    $tags->setForPost($postId, array($tagId));
    check($tags->forPost($postId)[0]['slug'] === 'topic', 'Post tags were not saved.');
    $repo->setVisibility($postId, 'members');
    check(count($repo->posts('published')) === 0 && count($repo->posts('published', null, true, $tagId)) === 1, 'Member visibility leaked into public listing.');
    for ($i = 1; $i <= 21; $i++) {
        $repo->savePost(null, null, '分页文章 ' . $i, 'page-' . $i, '正文', 'published');
    }
    check($repo->countPosts('published') === 21 && count($repo->posts('published')) === 20 && count($repo->posts('published', page: 2)) === 1, 'Pagination or count failed.');
    $batchA = $repo->savePost(null, null, '批量文章甲', 'batch-a', '正文', 'draft');
    $batchB = $repo->savePost(null, null, '批量文章乙', 'batch-b', '正文', 'draft');
    check($repo->countPosts('draft', null, true, null, '批量文章') === 2, 'Filtered draft count failed.');
    check($repo->bulkPosts(array((string) $batchA, (string) $batchB), 'publish') === 2, 'Bulk publish failed.');
    check(count($repo->posts('published', null, true, null, '批量文章')) === 2, 'Filtered published listing failed.');
    check($repo->bulkPosts(array((string) $batchA, (string) $batchB), 'draft') === 2, 'Bulk draft failed.');
    check($repo->bulkPosts(array((string) $batchA, (string) $batchB), 'delete') === 2 && $repo->post($batchA) === null, 'Bulk delete failed.');
    try {
        $repo->bulkPosts(array('not-an-id'), 'delete');
        throw new RuntimeException('Invalid bulk ID was accepted.');
    } catch (InvalidArgumentException $error) {
        check(str_contains($error->getMessage(), '无效'), 'Invalid bulk ID failed for the wrong reason.');
    }
    $members = new MemberRepository($pdo);
    $memberId = $members->register('Reader@Example.com', '读者', 'member-test-password-123');
    check($members->authenticate('reader@example.com', 'member-test-password-123')['id'] == $memberId, 'Member authentication failed.');
    $members->setStatus($memberId, 'disabled');
    check($members->authenticate('reader@example.com', 'member-test-password-123') === null, 'Disabled member could log in.');
    $access = new AccessRepository($pdo);
    check($access->admins()[0]['role_slug'] === 'owner', 'Installer did not assign the owner role.');
    $roleId = $access->saveRole(null, '审阅员', 'reviewer', array('content.view'));
    $adminId = $access->createAdmin('reviewer', 'reviewer-password-123', $roleId, true);
    check(Permissions::can($pdo, array('role_id' => $roleId, 'role_slug' => 'reviewer'), 'content.view'), 'Granted permission failed.');
    check(! Permissions::can($pdo, array('role_id' => $roleId, 'role_slug' => 'reviewer'), 'taxonomy.manage'), 'Unexpected permission granted.');
    $editorRoleId = $access->saveRole(null, '内容专员', 'content-only-editor', array('content.manage'));
    check(Permissions::can($pdo, array('role_id' => $editorRoleId, 'role_slug' => 'content-only-editor'), 'content.view'), 'Content management did not include listing access.');
    $access->deleteRole($editorRoleId);
    $access->deleteAdmin($adminId, 1);
    $access->deleteRole($roleId);
    Migrator::ensure($pdo);
    check($access->admins()[0]['role_slug'] === 'owner', 'Idempotent migration altered the owner.');

    $now = 1000000;
    $claims = array(
        'schema' => 1,
        'product' => 'FangdaysCMS',
        'key_id' => 'main',
        'license_id' => bin2hex(random_bytes(16)),
        'domains' => array('example.com', 'www.example.com'),
        'features' => array('publish'),
        'issued_at' => $now,
        'not_before' => $now,
        'expires_at' => $now + 100
    );
    $code = LicenseCode::issue($claims, $secret);
    $keys = array('main' => $public);
    check(LicenseCode::verify($code, $keys, 'EXAMPLE.COM.', $now)['valid'], 'Valid code was rejected.');
    check(LicenseCode::verify($code, $keys, 'other.example.com', $now)['reason'] === 'domain_mismatch', 'Domain binding failed.');
    check(LicenseCode::verify($code, $keys, 'example.com', $now - 1)['reason'] === 'not_yet_valid', 'Start time failed.');
    check(LicenseCode::verify($code, $keys, 'example.com', $now + 101)['reason'] === 'expired', 'Expiry failed.');
    $parts = explode('.', $code);
    $parts[1][0] = $parts[1][0] === 'A' ? 'B' : 'A';
    check(! LicenseCode::verify(implode('.', $parts), $keys, 'example.com', $now)['valid'], 'Tampered payload was accepted.');
    $parts = explode('.', $code);
    $parts[2][0] = $parts[2][0] === 'A' ? 'B' : 'A';
    check(LicenseCode::verify(implode('.', $parts), $keys, 'example.com', $now)['reason'] === 'signature', 'Tampered signature was accepted.');
    $store = new LicenseStore($root . '/var/license.code', $keys, 'example.com');
    check($store->activate($code, $now)['valid'], 'Activation failed.');
    check($store->allows('publish', $now) && ! $store->allows('export', $now), 'Feature gate failed.');
    check(! $store->activate('bad-token', $now)['valid'] && $store->status($now)['valid'], 'Invalid activation replaced a valid code.');

    $legacy = new PDO('sqlite::memory:', null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
    $legacy->exec('PRAGMA foreign_keys = ON');
    $legacy->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, password_hash TEXT, created_at INTEGER)');
    $legacy->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, sort_order INTEGER)');
    $legacy->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, category_id INTEGER, title TEXT, slug TEXT, body TEXT, status TEXT, created_at INTEGER, updated_at INTEGER)');
    $legacy->exec("INSERT INTO users VALUES (7, 'old-admin', 'hash', 1)");
    $legacy->exec("INSERT INTO posts VALUES (9, NULL, '旧文章', 'legacy', '正文', 'published', 1, 1)");
    Migrator::ensure($legacy);
    check((int) $legacy->query('SELECT version FROM cms_schema')->fetchColumn() === 6, 'Legacy schema version was not upgraded.');
    check((int) $legacy->query('SELECT COUNT(*) FROM posts')->fetchColumn() === 1, 'Legacy content was lost.');
    check((new AccessRepository($legacy))->admins()[0]['role_slug'] === 'owner', 'Legacy administrator was not migrated.');
    $legacy->exec('DROP TABLE pages');
    $legacy->exec('DROP TABLE site_settings');
    $legacy->exec('UPDATE cms_schema SET version = 2 WHERE id = 1');
    Migrator::ensure($legacy);
    check((int) $legacy->query('SELECT version FROM cms_schema')->fetchColumn() === 6 && (int) $legacy->query('SELECT COUNT(*) FROM posts')->fetchColumn() === 1, 'Upgrade from schema version 2 failed.');
    $legacy->exec('DROP TABLE post_field_values');
    $legacy->exec('DROP TABLE category_models');
    $legacy->exec('DROP TABLE model_fields');
    $legacy->exec('DROP TABLE content_models');
    $legacy->exec('UPDATE cms_schema SET version = 5 WHERE id = 1');
    Migrator::ensure($legacy);
    check((int) $legacy->query('SELECT version FROM cms_schema')->fetchColumn() === 6 && (int) $legacy->query('SELECT COUNT(*) FROM posts')->fetchColumn() === 1 && (new ContentModelRepository($legacy))->defaultModel()['slug'] === 'article', 'Upgrade from schema version 5 failed.');

    $pages = new PageRepository($pdo);
    $pageId = $pages->save(null, '关于', 'about', '简介', 'draft', 5);
    check($pages->publishedBySlug('about') === null, 'Draft page became public.');
    $pages->save($pageId, '关于', 'about', '简介', 'published', 5);
    check($pages->publishedBySlug('about')['body'] === '简介' && count($pages->published()) === 1, 'Published page lookup failed.');
    $pages->delete($pageId);
    check($pages->byId($pageId) === null, 'Page deletion failed.');
    $settings = new SettingsRepository($pdo);
    $settings->save('新站名', '站点简介', '页脚内容');
    check($settings->all('旧站名')['site_name'] === '新站名', 'Site settings were not saved.');
    $pdo->exec("INSERT INTO media (path, original_name, mime_type, bytes, created_at) VALUES ('/uploads/2026/10/0123456789abcdef0123456789abcdef.png', 'test.png', 'image/png', 100, 1)");
    $mediaId = (int) $pdo->lastInsertId();
    $media = new MediaRepository($pdo, $root);
    $media->setCover($postId, $mediaId);
    check($repo->publishedPostBySlug('first-post')['cover_path'] !== null, 'Post cover relation failed.');
    try {
        $media->delete($mediaId);
        throw new RuntimeException('Referenced media was deleted.');
    } catch (InvalidArgumentException $error) {
        check(str_contains($error->getMessage(), '使用'), 'Referenced media deletion failed for the wrong reason.');
    }

    $quickRoot = $root . '/quicksite';
    mkdir($quickRoot, 0700);
    $quick = Quickstart::install($quickRoot, $root . '/issuer.key', '127.0.0.1', '快速安装站');
    $quickConfig = require $quickRoot . '/config/app.php';
    $quickPdo = Database::connect($quickConfig['database']);
    check(password_verify($quick['password'], $quickPdo->query('SELECT password_hash FROM users')->fetchColumn()), 'Quickstart administrator password failed.');
    check((new ContentRepository($quickPdo))->publishedPostBySlug('welcome') !== null, 'Quickstart sample content missing.');
    check((new PageRepository($quickPdo))->publishedBySlug('about') !== null, 'Quickstart sample page missing.');
    check((new LicenseStore($quickConfig['license_file'], $quickConfig['license_public_keys'], '127.0.0.1'))->status()['valid'], 'Quickstart license failed.');
    try {
        Quickstart::install($quickRoot, $root . '/issuer.key');
        throw new RuntimeException('Quickstart overwrote an existing site.');
    } catch (RuntimeException $error) {
        check(str_contains($error->getMessage(), '已经安装'), 'Quickstart failed for the wrong reason.');
    }

    echo "FangdaysCMS quickstart, installer, migration, content models, membership, RBAC, and licensing tests passed\n";
} finally {
    removeTestDirectory($root);
}

<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Run only against an empty, disposable MySQL database whose name ends in _test. */
require dirname(__DIR__) . '/autoload.php';

use FangdaysCMS\Cms\ContentRepository;
use FangdaysCMS\Cms\ContentModelRepository;
use FangdaysCMS\Cms\ContentSearch;
use FangdaysCMS\Cms\Database;
use FangdaysCMS\Cms\Installer;
use FangdaysCMS\Cms\TagRepository;
use FangdaysCMS\Cms\MemberRepository;
use FangdaysCMS\Cms\AccessRepository;
use FangdaysCMS\Cms\PageRepository;
use FangdaysCMS\Cms\SettingsRepository;
use FangdaysCMS\Cms\MediaRepository;
use FangdaysCMS\Cms\OperationsRepository;
use FangdaysCMS\Cms\OperationTags;

$name = getenv('FANGDAYS_MYSQL_NAME') ?: '';
if (! preg_match('/^[a-zA-Z0-9_]+_test$/D', $name)) {
    fwrite(STDERR, "FANGDAYS_MYSQL_NAME must end with _test\n");
    exit(1);
}
$database = array(
    'driver' => 'mysql',
    'host' => getenv('FANGDAYS_MYSQL_HOST') ?: '127.0.0.1',
    'port' => getenv('FANGDAYS_MYSQL_PORT') ?: '3306',
    'name' => $name,
    'user' => getenv('FANGDAYS_MYSQL_USER') ?: '',
    'password' => getenv('FANGDAYS_MYSQL_PASSWORD') ?: ''
);
$pdo = Database::connect($database);
if ((int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn() !== 0) {
    throw new RuntimeException('Disposable MySQL test database is not empty.');
}
$root = sys_get_temp_dir() . '/fangdays-mysql-test-' . bin2hex(random_bytes(8));
mkdir($root . '/config', 0700, true);
mkdir($root . '/var', 0700, true);

try {
    $pair = sodium_crypto_sign_keypair();
    Installer::install(array(
        'site_name' => 'MySQL 测试',
        'site_domain' => 'example.com',
        'admin_username' => 'owner',
        'admin_password' => 'strong-test-password-123',
        'license_key_id' => 'main',
        'license_public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
        'database_driver' => 'mysql',
        'mysql_host' => $database['host'],
        'mysql_port' => $database['port'],
        'mysql_name' => $database['name'],
        'mysql_user' => $database['user'],
        'mysql_password' => $database['password']
    ), $root);
    $config = require $root . '/config/app.php';
    $repo = new ContentRepository(Database::connect($config['database']));
    $category = $repo->saveCategory(null, '新闻', 'news', 1);
    $post = $repo->savePost(null, $category, 'MySQL 文章', 'mysql-post', '正文', 'published');
    if ($repo->publishedPostBySlug('mysql-post')['id'] != $post) {
        throw new RuntimeException('MySQL published lookup failed.');
    }
    $repo->savePostMeta($post, array('subtitle' => 'MySQL 副标题', 'author' => '编辑', 'source' => '本站', 'title_color' => '#176c73', 'outlink' => '', 'seo_keywords' => '内容', 'seo_description' => 'MySQL 摘要', 'is_top' => '1', 'is_recommended' => '0', 'is_headline' => '0'));
    if ($repo->publishedPostBySlug('mysql-post')['subtitle'] !== 'MySQL 副标题' || $repo->countPosts('published', null, true, null, null, 'top') !== 1) {
        throw new RuntimeException('MySQL post metadata failed.');
    }
    $models = new ContentModelRepository($pdo);
    $model = $models->saveModel(null, '产品模型', 'product');
    $field = $models->saveField(null, $model, 'ext_color', '颜色', 'select', '红色,蓝色', true, 1);
    $sizeField = $models->saveField(null, $model, 'ext_size', '尺码', 'multi', 'S,XS', false, 2);
    $models->bindCategory($category, $model);
    $models->setValuesForPost($post, $category, array($field => '红色', $sizeField => array('S')));
    if ($models->publicFieldsForPost($post, $category)[0]['value'] !== '红色' || $repo->countPosts('published', null, true, null, '红色') !== 1) {
        throw new RuntimeException('MySQL content model field failed.');
    }
    $filters = $models->categoryFilters($category, array('ext_color' => '红色', 'ext_size' => 'S'));
    if ($repo->countPosts('published', $category, true, fieldFilters: $filters['filters']) !== 1 || (int) $repo->posts('published', $category, true, fieldFilters: $filters['filters'])[0]['id'] !== $post) {
        throw new RuntimeException('MySQL combined model field filters failed.');
    }
    $search = ContentSearch::parse($pdo, $repo, array('keyword' => '红色', 'field' => 'ext_color', 'ext_size' => 'S', 'scode' => (string) $category));
    if ($repo->countPosts('published', null, true, search: $search['criteria']) !== 1 || (int) $repo->posts('published', null, true, search: $search['criteria'])[0]['id'] !== $post) {
        throw new RuntimeException('MySQL compound search failed.');
    }
    $wrongSize = ContentSearch::parse($pdo, $repo, array('ext_size' => 'XS', 'title' => 'MySQL'));
    if ($repo->countPosts('published', null, true, search: $wrongSize['criteria']) !== 0) {
        throw new RuntimeException('MySQL multi-choice search matched the wrong option.');
    }
    $childCategory = $repo->saveCategory(null, '子栏目', 'mysql-child', 2, $category);
    $childPost = $repo->savePost(null, $childCategory, '层级文章', 'mysql-nested-post', '正文', 'published');
    if ($repo->countPosts('published', $category) !== 2 || (int) $repo->categories()[1]['depth'] !== 1) {
        throw new RuntimeException('MySQL category hierarchy failed.');
    }
    $repo->deletePost($childPost);
    $repo->deleteCategory($childCategory);
    $repo->deleteCategory($category);
    if ($repo->post($post)['category_id'] !== null) {
        throw new RuntimeException('MySQL category deletion did not preserve article.');
    }
    $singleCategory = $repo->saveCategory(null, '关于', 'mysql-company', 1);
    $repo->saveCategorySettings($singleCategory, 'single', '', '关于 SEO', '企业', '描述');
    $repo->saveSingle($singleCategory, '关于我们', '单页正文', 'published');
    $models->bindCategory($singleCategory, $model);
    $models->setValuesForSingleCategory($singleCategory, array($field => '蓝色'));
    if ($models->publicFieldsForSingleCategory($singleCategory)[0]['value'] !== '蓝色') {
        throw new RuntimeException('MySQL single category model field failed.');
    }
    if ($repo->categoryBySlug('mysql-company')['single_body'] !== '单页正文') {
        throw new RuntimeException('MySQL single category failed.');
    }
    $repo->deleteCategory($singleCategory);
    $tags = new TagRepository(Database::connect($config['database']));
    $tag = $tags->save(null, 'MySQL 标签', 'mysql-tag');
    $tags->setForPost($post, array($tag));
    $repo->setVisibility($post, 'members');
    if (count($repo->posts('published')) !== 0 || count($repo->posts('published', null, true, $tag)) !== 1) {
        throw new RuntimeException('MySQL tag or visibility check failed.');
    }
    $repo->savePostSortOrders(array($post => '2'));
    if ((int) $repo->post($post)['sort_order'] !== 2 || $repo->post($post)['subtitle'] !== 'MySQL 副标题') {
        throw new RuntimeException('MySQL sorting lost article metadata.');
    }
    $futurePost = $repo->savePost(null, null, 'MySQL 定时文章', 'mysql-scheduled', '正文', 'published');
    $repo->savePostMeta($futurePost, array('published_at' => (string) (time() + 3600), 'sort_order' => '0'));
    if ($repo->publishedPostBySlug('mysql-scheduled') !== null || $repo->countPosts('published', null, true, null, 'MySQL 定时文章') !== 0 || $repo->countPosts('published', null, true, null, 'MySQL 定时文章', includeFuture: true) !== 1) {
        throw new RuntimeException('MySQL scheduled publication failed.');
    }
    $repo->deletePost($futurePost);
    for ($i = 1; $i <= 21; $i++) {
        $repo->savePost(null, null, 'MySQL 分页 ' . $i, 'mysql-page-' . $i, '正文', 'published');
    }
    if ($repo->countPosts('published') !== 21 || count($repo->posts('published')) !== 20 || count($repo->posts('published', page: 2)) !== 1) {
        throw new RuntimeException('MySQL pagination failed.');
    }
    $members = new MemberRepository(Database::connect($config['database']));
    $memberId = $members->register('mysql@example.com', 'MySQL 会员', 'mysql-member-password-123');
    if ($members->authenticate('mysql@example.com', 'mysql-member-password-123')['id'] != $memberId) {
        throw new RuntimeException('MySQL member authentication failed.');
    }
    if ((new AccessRepository(Database::connect($config['database'])))->admins()[0]['role_slug'] !== 'owner') {
        throw new RuntimeException('MySQL owner role was not created.');
    }
    $pages = new PageRepository(Database::connect($config['database']));
    $pageId = $pages->save(null, 'MySQL 单页', 'mysql-about', '正文', 'published', 1);
    if ($pages->publishedBySlug('mysql-about')['id'] != $pageId) {
        throw new RuntimeException('MySQL page lookup failed.');
    }
    $settings = new SettingsRepository(Database::connect($config['database']));
    $settings->save('MySQL 站点', '简介', '页脚');
    if ($settings->all('原站点')['site_name'] !== 'MySQL 站点') {
        throw new RuntimeException('MySQL settings failed.');
    }
    $pdo->exec("INSERT INTO media (path, original_name, mime_type, bytes, created_at) VALUES ('/uploads/2026/10/0123456789abcdef0123456789abcdef.png', 'test.png', 'image/png', 100, 1)");
    $mediaId = (int) $pdo->lastInsertId();
    (new MediaRepository($pdo, $root . '/public'))->setCover($post, $mediaId);
    $operations = new OperationsRepository($pdo);
    $operations->saveCompany(array('name' => 'MySQL 企业', 'phone' => '010-12345678'));
    $labelId = $operations->saveLabel(null, array('name' => 'home_slogan', 'description' => '标语', 'value' => '欢迎'));
    $slideId = $operations->saveSlide(null, array('group_id' => '1', 'media_id' => (string) $mediaId, 'title' => 'MySQL 横幅', 'subtitle' => '', 'link' => '/search', 'sort_order' => '1'));
    $linkId = $operations->saveLink(null, array('group_id' => '1', 'media_id' => (string) $mediaId, 'name' => 'MySQL 友链', 'link' => 'https://example.com/', 'sort_order' => '1'));
    $slideAfter = $operations->saveSlide(null, array('group_id' => '1', 'media_id' => (string) $mediaId, 'title' => '第二张', 'subtitle' => '', 'link' => '', 'sort_order' => '2'));
    if ($operations->company()['name'] !== 'MySQL 企业' || $operations->labelValues()['home_slogan'] !== '欢迎' || $operations->slides(1)[0]['title'] !== 'MySQL 横幅' || $operations->links(1)[0]['name'] !== 'MySQL 友链') {
        throw new RuntimeException('MySQL content operations failed.');
    }
    if ($operations->slides(1, 1, 2)[0]['title'] !== '第二张' || OperationTags::html('{pboot:slide gid=1 num=1 start=2}[slide:title]{/pboot:slide}', $operations) !== '第二张') {
        throw new RuntimeException('MySQL operation tag pagination failed.');
    }
    $operations->deleteSlide($slideId);
    $operations->deleteSlide($slideAfter);
    $operations->deleteLink($linkId);
    $operations->deleteLabel($labelId);
    if ($repo->publishedPostBySlug('mysql-post')['cover_path'] === null) {
        throw new RuntimeException('MySQL post cover failed.');
    }
    $transferCategory = $repo->saveCategory(null, '转移目标', 'mysql-transfer', 3);
    $models->bindCategory($transferCategory, $model);
    if ($repo->transferPosts(array((string) $post), 'copy', $transferCategory) !== 1) {
        throw new RuntimeException('MySQL article copy failed.');
    }
    $copy = $repo->publishedPostBySlug('mysql-post-copy');
    if ($copy === null || (int) $copy['category_id'] !== $transferCategory || $copy['subtitle'] !== 'MySQL 副标题' || $copy['visibility'] !== 'members' || $copy['cover_path'] === null || $models->valuesForPost((int) $copy['id'])[$field] !== '红色' || $tags->forPost((int) $copy['id'])[0]['slug'] !== 'mysql-tag') {
        throw new RuntimeException('MySQL article copy lost related data.');
    }
    if ($repo->transferPosts(array((string) $post), 'move', $transferCategory) !== 1 || (int) $repo->post($post)['category_id'] !== $transferCategory) {
        throw new RuntimeException('MySQL article move failed.');
    }
    $repo->deletePost((int) $copy['id']);
    $repo->deleteCategory($transferCategory);
    $pdo->exec('DROP TABLE post_meta');
    $pdo->exec('DROP TABLE category_settings');
    $pdo->exec('ALTER TABLE categories DROP FOREIGN KEY fk_categories_parent, DROP COLUMN parent_id');
    $pdo->exec('UPDATE cms_schema SET version = 6 WHERE id = 1');
    \FangdaysCMS\Cms\Migrator::ensure($pdo);
    if ((int) $pdo->query('SELECT version FROM cms_schema WHERE id = 1')->fetchColumn() !== \FangdaysCMS\Cms\Migrator::VERSION || (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'parent_id'")->fetchColumn() !== 1 || $repo->post($post) === null || (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'category_settings'")->fetchColumn() !== 1 || (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'post_meta'")->fetchColumn() !== 1) {
        throw new RuntimeException('MySQL upgrade from schema version 6 failed.');
    }
    $repo->savePostMeta($post, array('subtitle' => '升级保留', 'is_top' => '1'));
    $pdo->exec('UPDATE cms_schema SET version = 8 WHERE id = 1');
    \FangdaysCMS\Cms\Migrator::ensure($pdo);
    if ($repo->post($post)['subtitle'] !== '升级保留' || (int) $pdo->query('SELECT version FROM cms_schema WHERE id = 1')->fetchColumn() !== \FangdaysCMS\Cms\Migrator::VERSION) {
        throw new RuntimeException('MySQL upgrade from schema version 8 lost post metadata.');
    }
    $pdo->exec('ALTER TABLE post_meta DROP COLUMN body_format');
    $pdo->exec('UPDATE cms_schema SET version = 9 WHERE id = 1');
    \FangdaysCMS\Cms\Migrator::ensure($pdo);
    if ($repo->post($post)['body_format'] !== 'plain' || $repo->post($post)['subtitle'] !== '升级保留' || (int) $pdo->query('SELECT version FROM cms_schema WHERE id = 1')->fetchColumn() !== \FangdaysCMS\Cms\Migrator::VERSION) {
        throw new RuntimeException('MySQL upgrade from schema version 9 lost article content or metadata.');
    }
    $pdo->exec('ALTER TABLE post_meta DROP COLUMN published_at, DROP COLUMN sort_order');
    $pdo->exec('UPDATE cms_schema SET version = 10 WHERE id = 1');
    \FangdaysCMS\Cms\Migrator::ensure($pdo);
    if ((int) $pdo->query('SELECT version FROM cms_schema WHERE id = 1')->fetchColumn() !== \FangdaysCMS\Cms\Migrator::VERSION || (int) $repo->post($post)['sort_order'] !== 255 || (int) $repo->post($post)['published_at'] !== (int) $repo->post($post)['created_at']) {
        throw new RuntimeException('MySQL upgrade from schema version 10 lost publication defaults.');
    }
    $pdo->exec('DROP TABLE post_gallery');
    $pdo->exec('DROP TABLE post_attachment');
    $pdo->exec('UPDATE cms_schema SET version = 11 WHERE id = 1');
    \FangdaysCMS\Cms\Migrator::ensure($pdo);
    if ((int) $pdo->query('SELECT version FROM cms_schema WHERE id = 1')->fetchColumn() !== \FangdaysCMS\Cms\Migrator::VERSION || (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('post_gallery', 'post_attachment')")->fetchColumn() !== 2 || $repo->post($post) === null) {
        throw new RuntimeException('MySQL upgrade from schema version 11 lost article content or media tables.');
    }
    foreach (array('slides', 'friend_links', 'custom_labels', 'company_profile') as $table) {
        $pdo->exec('DROP TABLE ' . $table);
    }
    $pdo->exec('UPDATE cms_schema SET version = 12 WHERE id = 1');
    \FangdaysCMS\Cms\Migrator::ensure($pdo);
    if ((int) $pdo->query('SELECT version FROM cms_schema WHERE id = 1')->fetchColumn() !== \FangdaysCMS\Cms\Migrator::VERSION || (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('slides', 'friend_links', 'custom_labels', 'company_profile')")->fetchColumn() !== 4 || $repo->post($post) === null) {
        throw new RuntimeException('MySQL upgrade from schema version 12 lost article content or operation tables.');
    }
    $singleCategoryId = $repo->saveCategory(null, '旧栏目单页', 'mysql-single', 1);
    $repo->saveCategorySettings($singleCategoryId, 'single', '', '', '', '');
    $repo->saveSingle($singleCategoryId, '旧栏目单页', '<旧正文>', 'published');
    $pdo->exec('ALTER TABLE pages DROP COLUMN body_format');
    $pdo->exec('ALTER TABLE category_settings DROP COLUMN single_body_format');
    $pdo->exec('UPDATE cms_schema SET version = 13 WHERE id = 1');
    \FangdaysCMS\Cms\Migrator::ensure($pdo);
    if ($pages->byId($pageId)['body_format'] !== 'plain' || $repo->category($singleCategoryId)['single_body_format'] !== 'plain' || $repo->category($singleCategoryId)['single_body'] !== '<旧正文>') {
        throw new RuntimeException('MySQL upgrade from schema version 13 lost single-page text.');
    }
    $pages->save($pageId, 'MySQL 单页', 'mysql-about', '<p><strong>富文本</strong><script>bad()</script></p>', 'published', 1, 'html');
    $repo->saveSingle($singleCategoryId, '旧栏目单页', '<p><strong>栏目富文本</strong><script>bad()</script></p>', 'published', 'html');
    if ($pages->byId($pageId)['body_format'] !== 'html' || str_contains($pages->byId($pageId)['body'], '<script') || $repo->category($singleCategoryId)['single_body_format'] !== 'html' || str_contains($repo->category($singleCategoryId)['single_body'], '<script')) {
        throw new RuntimeException('MySQL rich single-page content was not sanitized.');
    }
    $pdo->exec('DROP TABLE category_field_values');
    $pdo->exec('UPDATE cms_schema SET version = 14 WHERE id = 1');
    \FangdaysCMS\Cms\Migrator::ensure($pdo);
    if ((int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'category_field_values'")->fetchColumn() !== 1 || $repo->category($singleCategoryId)['single_body_format'] !== 'html') {
        throw new RuntimeException('MySQL upgrade from schema version 14 lost single-page content or field table.');
    }
    echo "FangdaysCMS MySQL schema, content operations, membership, and roles passed\n";
} finally {
    $pdo->exec('DROP TABLE IF EXISTS slides');
    $pdo->exec('DROP TABLE IF EXISTS friend_links');
    $pdo->exec('DROP TABLE IF EXISTS custom_labels');
    $pdo->exec('DROP TABLE IF EXISTS company_profile');
    $pdo->exec('DROP TABLE IF EXISTS post_field_values');
    $pdo->exec('DROP TABLE IF EXISTS category_field_values');
    $pdo->exec('DROP TABLE IF EXISTS post_meta');
    $pdo->exec('DROP TABLE IF EXISTS category_models');
    $pdo->exec('DROP TABLE IF EXISTS category_settings');
    $pdo->exec('DROP TABLE IF EXISTS model_fields');
    $pdo->exec('DROP TABLE IF EXISTS content_models');
    $pdo->exec('DROP TABLE IF EXISTS post_cover');
    $pdo->exec('DROP TABLE IF EXISTS post_gallery');
    $pdo->exec('DROP TABLE IF EXISTS post_attachment');
    $pdo->exec('DROP TABLE IF EXISTS media');
    $pdo->exec('DROP TABLE IF EXISTS site_settings');
    $pdo->exec('DROP TABLE IF EXISTS pages');
    $pdo->exec('DROP TABLE IF EXISTS post_tags');
    $pdo->exec('DROP TABLE IF EXISTS post_visibility');
    $pdo->exec('DROP TABLE IF EXISTS posts');
    $pdo->exec('DROP TABLE IF EXISTS tags');
    $pdo->exec('DROP TABLE IF EXISTS members');
    $pdo->exec('DROP TABLE IF EXISTS user_roles');
    $pdo->exec('DROP TABLE IF EXISTS role_permissions');
    $pdo->exec('DROP TABLE IF EXISTS roles');
    $pdo->exec('DROP TABLE IF EXISTS categories');
    $pdo->exec('DROP TABLE IF EXISTS users');
    $pdo->exec('DROP TABLE IF EXISTS cms_schema');
    if (is_file($root . '/config/app.php')) {
        unlink($root . '/config/app.php');
    }
    rmdir($root . '/config');
    rmdir($root . '/var');
    rmdir($root);
}

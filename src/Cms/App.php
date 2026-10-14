<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use FangdaysCMS\Licensing\LicenseStore;
use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;

final class App
{
    public function __construct(private readonly string $root)
    {
    }

    public function run(): void
    {
        Auth::start();
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $configFile = $this->root . '/config/app.php';
        if (! is_file($configFile)) {
            $this->installRoute($path, $method);
            return;
        }
        $config = require $configFile;
        $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
        $requestHost = is_string($hostHeader) ? preg_replace('/:[0-9]+$/D', '', $hostHeader) : '';
        if (\FangdaysCMS\Licensing\LicenseCode::normalizeDomain($requestHost) !== $config['site_domain']) {
            View::render('host-mismatch', array('site_name' => $config['site_name'], 'page_title' => '站点域名不匹配'), 421);
            return;
        }
        $pdo = Database::connect($config['database']);
        Migrator::ensure($pdo);
        $repo = new ContentRepository($pdo);
        $store = new LicenseStore($config['license_file'], $config['license_public_keys'], $config['site_domain']);
        $settings = (new SettingsRepository($pdo))->all($config['site_name']);
        $site = array('site_name' => $settings['site_name'], 'site_description' => $settings['description'], 'footer_text' => $settings['footer_text']);

        if ($path === '/login') {
            $this->loginRoute($method, $pdo, $site);
            return;
        }
        if ($path === '/logout' && $method === 'POST') {
            $this->requireCsrf();
            Auth::logout();
            $this->redirect('/login');
        }
        if (str_starts_with($path, '/admin')) {
            $user = Auth::user($pdo);
            if ($user === null) {
                $this->redirect('/login');
            }
            $can = array();
            foreach (Permissions::CATALOG as $permission => $label) {
                $can[$permission] = Permissions::can($pdo, $user, $permission);
            }
            if ($path === '/admin' && ! $can['dashboard.view']) {
                $landing = self::firstAdminPath($can);
                if ($landing !== '/admin') {
                    $this->redirect($landing);
                }
            }
            $this->adminRoute($path, $method, $pdo, $repo, $store, $site + array('admin' => true, 'user' => $user, 'can' => $can));
            return;
        }

        $license = $store->status();
        if (! $license['valid']) {
            View::render('license-required', $site + array('page_title' => '站点授权', 'license' => $license), 503);
            return;
        }
        $member = Auth::member($pdo);
        $site += array('member' => $member);
        if (str_starts_with($path, '/member/')) {
            $this->memberRoute($path, $method, $pdo, $site);
            return;
        }
        $this->siteRoute($path, $method, $pdo, $repo, new TagRepository($pdo), new PageRepository($pdo), $site);
    }

    private function installRoute(string $path, string $method): void
    {
        if ($path !== '/install') {
            $this->redirect('/install');
        }
        $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
        $suggestedHost = is_string($hostHeader) ? preg_replace('/:[0-9]+$/D', '', $hostHeader) : '';
        $suggestedHost = \FangdaysCMS\Licensing\LicenseCode::normalizeDomain($suggestedHost) ?? 'localhost';
        $data = array('page_title' => '安装 FangdaysCMS', 'error' => null, 'input' => array('database_driver' => 'sqlite', 'site_domain' => $suggestedHost));
        if ($method === 'POST') {
            $this->requireCsrf();
            $data['input'] = $_POST;
            try {
                Installer::install($_POST, $this->root);
                $_SESSION['flash'] = '安装完成，请登录后台并激活授权码。';
                $this->redirect('/login');
            } catch (InvalidArgumentException $error) {
                $data['error'] = $error->getMessage();
            } catch (Throwable $error) {
                error_log('FangdaysCMS install: ' . $error->getMessage());
                $data['error'] = '安装失败，请核对数据库连接和目录权限。';
            }
        } elseif ($method !== 'GET') {
            $this->notFound();
        }
        View::render('install', $data);
    }

    private function loginRoute(string $method, PDO $pdo, array $site): void
    {
        if (Auth::user($pdo) !== null) {
            $this->redirect('/admin');
        }
        $error = null;
        if ($method === 'POST') {
            $this->requireCsrf();
            if (Auth::login($pdo, self::postString('username'), self::postString('password'))) {
                $user = Auth::user($pdo);
                if ($user === null) {
                    $this->redirect('/login');
                }
                $can = array();
                foreach (Permissions::CATALOG as $permission => $label) {
                    $can[$permission] = Permissions::can($pdo, $user, $permission);
                }
                $this->redirect(self::firstAdminPath($can));
            }
            $error = '用户名或密码错误，或尝试次数过多。';
        } elseif ($method !== 'GET') {
            $this->notFound();
        }
        View::render('login', $site + array('page_title' => '后台登录', 'error' => $error));
    }

    private function adminRoute(string $path, string $method, PDO $pdo, ContentRepository $repo, LicenseStore $store, array $base): void
    {
        $permission = match (true) {
            $path === '/admin' => 'dashboard.view',
            str_starts_with($path, '/admin/license') => 'license.manage',
            str_starts_with($path, '/admin/categories'), str_starts_with($path, '/admin/tags'), str_starts_with($path, '/admin/models') => 'taxonomy.manage',
            ($path === '/admin/posts' || $path === '/admin/pages') && $method === 'GET' => 'content.view',
            str_starts_with($path, '/admin/posts') => 'content.manage',
            str_starts_with($path, '/admin/pages') => 'content.manage',
            str_starts_with($path, '/admin/media') => 'media.manage',
            str_starts_with($path, '/admin/members') => 'members.manage',
            $path === '/admin/settings' => 'settings.manage',
            str_starts_with($path, '/admin/roles'), str_starts_with($path, '/admin/admins') => 'access.manage',
            default => null
        };
        if ($permission === null) {
            $this->notFound();
        }
        if (! $base['can'][$permission]) {
            View::render('forbidden', $base + array('page_title' => '无权访问'), 403);
            return;
        }
        if (str_starts_with($path, '/admin/tags')) {
            $this->tagAdminRoute($path, $method, new TagRepository($pdo), $base);
            return;
        }
        if (str_starts_with($path, '/admin/models')) {
            $this->modelAdminRoute($path, $method, new ContentModelRepository($pdo), $base);
            return;
        }
        if (str_starts_with($path, '/admin/members')) {
            $this->memberAdminRoute($path, $method, new MemberRepository($pdo), $base);
            return;
        }
        if (str_starts_with($path, '/admin/roles') || str_starts_with($path, '/admin/admins')) {
            $this->accessAdminRoute($path, $method, new AccessRepository($pdo), $base);
            return;
        }
        if (str_starts_with($path, '/admin/pages')) {
            $this->pageAdminRoute($path, $method, new PageRepository($pdo), $base);
            return;
        }
        if (str_starts_with($path, '/admin/media')) {
            $this->mediaAdminRoute($path, $method, new MediaRepository($pdo, $this->root . '/public'), $base);
            return;
        }
        if ($path === '/admin/settings') {
            $settings = new SettingsRepository($pdo);
            $values = $settings->all($base['site_name']);
            $error = null;
            if ($method === 'POST') {
                $this->requireCsrf();
                $values = array('site_name' => self::postString('site_name'), 'description' => self::postString('description'), 'footer_text' => self::postString('footer_text'));
                try {
                    $settings->save($values['site_name'], $values['description'], $values['footer_text']);
                    $_SESSION['flash'] = '站点设置已保存。';
                    $this->redirect('/admin/settings');
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                }
            } elseif ($method !== 'GET') {
                $this->notFound();
            }
            View::render('admin/settings', $base + array('page_title' => '站点设置', 'settings' => $values, 'error' => $error), $error ? 422 : 200);
            return;
        }
        if ($path === '/admin' && $method === 'GET') {
            View::render('admin/dashboard', $base + array(
                'page_title' => '管理概览',
                'category_count' => count($repo->categories()),
                'post_count' => $repo->countPosts(),
                'published_count' => $repo->countPosts('published', null, true),
                'draft_count' => $repo->countPosts('draft'),
                'recent_posts' => $base['can']['content.view'] ? $repo->posts() : array(),
                'license' => $store->status()
            ));
            return;
        }
        if ($path === '/admin/license') {
            if ($method === 'POST') {
                $this->requireCsrf();
                $result = $store->activate(self::postString('code'));
                $_SESSION['flash'] = $result['valid'] ? '授权码已激活。' : '授权码无效：' . $result['reason'];
                $this->redirect('/admin/license');
            }
            if ($method === 'GET') {
                View::render('admin/license', $base + array('page_title' => '离线授权', 'license' => $store->status()));
                return;
            }
        }
        if ($path === '/admin/categories' && $method === 'GET') {
            View::render('admin/categories', $base + array('page_title' => '栏目管理', 'categories' => $repo->categoriesWithPostCounts()));
            return;
        }
        if ($path === '/admin/categories/new' && $method === 'GET') {
            View::render('admin/category-form', $base + array('page_title' => '新建栏目', 'category' => null, 'models' => (new ContentModelRepository($pdo))->all(), 'error' => null));
            return;
        }
        if ($path === '/admin/categories' && $method === 'POST') {
            $this->saveCategory($pdo, $repo, null, $base);
            return;
        }
        if (preg_match('#^/admin/categories/([1-9][0-9]*)/edit$#D', $path, $match)) {
            $id = (int) $match[1];
            $category = $repo->category($id);
            if ($category === null) {
                $this->notFound();
            }
            if ($method === 'POST') {
                $this->saveCategory($pdo, $repo, $id, $base);
            } elseif ($method === 'GET') {
                View::render('admin/category-form', $base + array('page_title' => '编辑栏目', 'category' => $category, 'models' => (new ContentModelRepository($pdo))->all(), 'error' => null));
            } else {
                $this->notFound();
            }
            return;
        }
        if (preg_match('#^/admin/categories/([1-9][0-9]*)/delete$#D', $path, $match) && $method === 'POST') {
            $this->requireCsrf();
            $repo->deleteCategory((int) $match[1]);
            $_SESSION['flash'] = '栏目已删除，原文章保留。';
            $this->redirect('/admin/categories');
        }
        if ($path === '/admin/posts' && $method === 'GET') {
            $keyword = $_GET['q'] ?? '';
            $keyword = is_string($keyword) ? mb_substr(trim($keyword), 0, 100) : '';
            $status = $_GET['status'] ?? '';
            $status = is_string($status) && in_array($status, array('draft', 'published'), true) ? $status : '';
            $category = $_GET['category_id'] ?? '';
            $categoryId = is_string($category) && ctype_digit($category) && (int) $category > 0 ? (int) $category : null;
            $filters = array('q' => $keyword, 'status' => $status, 'category_id' => $categoryId ?? '');
            $total = $repo->countPosts($status ?: null, $categoryId, true, null, $keyword ?: null);
            $totalPages = (int) ceil($total / 20);
            $page = self::boundedPage($totalPages);
            View::render('admin/posts', $base + array('page_title' => '文章管理', 'posts' => $repo->posts($status ?: null, $categoryId, true, null, $keyword ?: null, $page), 'categories' => $repo->categories(), 'filters' => $filters, 'total' => $total, 'page' => $page, 'total_pages' => $totalPages));
            return;
        }
        if ($path === '/admin/posts/bulk' && $method === 'POST') {
            $this->requireCsrf();
            try {
                $rawIds = $_POST['post_ids'] ?? array();
                if (! is_array($rawIds)) {
                    throw new InvalidArgumentException('所选文章无效。');
                }
                $affected = $repo->bulkPosts($rawIds, self::postString('action'));
                $_SESSION['flash'] = '已处理 ' . $affected . ' 篇文章。';
            } catch (InvalidArgumentException $error) {
                $_SESSION['flash'] = '操作失败：' . $error->getMessage();
            }
            $this->redirect('/admin/posts');
            return;
        }
        if ($path === '/admin/posts/new' && $method === 'GET') {
            $models = new ContentModelRepository($pdo);
            View::render('admin/post-form', $base + array('page_title' => '新建文章', 'post' => null, 'categories' => $repo->categories(), 'tags' => (new TagRepository($pdo))->all(), 'media' => (new MediaRepository($pdo, $this->root . '/public'))->all(), 'selected_tags' => array(), 'extra_values' => array(), 'model_form' => self::postModelForm($models, $repo, null), 'error' => null));
            return;
        }
        if ($path === '/admin/posts' && $method === 'POST') {
            $this->savePost($pdo, $repo, null, $base);
            return;
        }
        if (preg_match('#^/admin/posts/([1-9][0-9]*)/edit$#D', $path, $match)) {
            $id = (int) $match[1];
            $post = $repo->post($id);
            if ($post === null) {
                $this->notFound();
            }
            if ($method === 'POST') {
                $this->savePost($pdo, $repo, $id, $base);
            } elseif ($method === 'GET') {
                $models = new ContentModelRepository($pdo);
                View::render('admin/post-form', $base + array('page_title' => '编辑文章', 'post' => $post, 'categories' => $repo->categories(), 'tags' => (new TagRepository($pdo))->all(), 'media' => (new MediaRepository($pdo, $this->root . '/public'))->all(), 'selected_tags' => array_column((new TagRepository($pdo))->forPost($id), 'id'), 'extra_values' => $models->valuesForPost($id), 'model_form' => self::postModelForm($models, $repo, $post), 'error' => null));
            } else {
                $this->notFound();
            }
            return;
        }
        if (preg_match('#^/admin/posts/([1-9][0-9]*)/delete$#D', $path, $match) && $method === 'POST') {
            $this->requireCsrf();
            $repo->deletePost((int) $match[1]);
            $_SESSION['flash'] = '文章已删除。';
            $this->redirect('/admin/posts');
        }
        $this->notFound();
    }

    private function pageAdminRoute(string $path, string $method, PageRepository $pages, array $base): void
    {
        if ($path === '/admin/pages' && $method === 'GET') {
            View::render('admin/pages', $base + array('page_title' => '单页管理', 'pages' => $pages->all()));
            return;
        }
        if ($path === '/admin/pages/new' && $method === 'GET') {
            View::render('admin/page-form', $base + array('page_title' => '新建单页', 'page' => null, 'error' => null));
            return;
        }
        if ($path === '/admin/pages' && $method === 'POST') {
            $this->savePage($pages, null, $base);
            return;
        }
        if (preg_match('#^/admin/pages/([1-9][0-9]*)/edit$#D', $path, $match)) {
            $id = (int) $match[1];
            $page = $pages->byId($id);
            if ($page === null) {
                $this->notFound();
            }
            if ($method === 'POST') {
                $this->savePage($pages, $id, $base);
            } elseif ($method === 'GET') {
                View::render('admin/page-form', $base + array('page_title' => '编辑单页', 'page' => $page, 'error' => null));
            } else {
                $this->notFound();
            }
            return;
        }
        if (preg_match('#^/admin/pages/([1-9][0-9]*)/delete$#D', $path, $match) && $method === 'POST') {
            $this->requireCsrf();
            $pages->delete((int) $match[1]);
            $_SESSION['flash'] = '单页已删除。';
            $this->redirect('/admin/pages');
        }
        $this->notFound();
    }

    private function mediaAdminRoute(string $path, string $method, MediaRepository $media, array $base): void
    {
        if ($path === '/admin/media') {
            $error = null;
            if ($method === 'POST') {
                $this->requireCsrf();
                try {
                    $media->upload(is_array($_FILES['image'] ?? null) ? $_FILES['image'] : array());
                    $_SESSION['flash'] = '图片已上传。';
                    $this->redirect('/admin/media');
                } catch (InvalidArgumentException | \RuntimeException $exception) {
                    $error = $exception->getMessage();
                }
            } elseif ($method !== 'GET') {
                $this->notFound();
            }
            View::render('admin/media', $base + array('page_title' => '媒体图片', 'media' => $media->all(), 'error' => $error), $error ? 422 : 200);
            return;
        }
        if (preg_match('#^/admin/media/([1-9][0-9]*)/delete$#D', $path, $match) && $method === 'POST') {
            $this->requireCsrf();
            try {
                $media->delete((int) $match[1]);
                $_SESSION['flash'] = '图片已删除。';
            } catch (InvalidArgumentException $exception) {
                $_SESSION['flash'] = $exception->getMessage();
            }
            $this->redirect('/admin/media');
        }
        $this->notFound();
    }

    private function savePage(PageRepository $pages, ?int $id, array $base): void
    {
        $this->requireCsrf();
        $input = array('id' => $id, 'title' => self::postString('title'), 'slug' => self::postString('slug'), 'body' => self::postString('body'), 'status' => self::postString('status'), 'menu_order' => self::postString('menu_order'));
        try {
            $order = filter_var($input['menu_order'], FILTER_VALIDATE_INT);
            if ($order === false) {
                throw new InvalidArgumentException('单页排序必须是整数。');
            }
            $pages->save($id, $input['title'], $input['slug'], $input['body'], $input['status'], $order);
            $_SESSION['flash'] = '单页已保存。';
            $this->redirect('/admin/pages');
        } catch (InvalidArgumentException $error) {
            $message = $error->getMessage();
        } catch (PDOException $error) {
            $message = '保存失败，请检查单页别名是否重复。';
        }
        View::render('admin/page-form', $base + array('page_title' => $id === null ? '新建单页' : '编辑单页', 'page' => $input, 'error' => $message), 422);
    }

    private function siteRoute(string $path, string $method, PDO $pdo, ContentRepository $repo, TagRepository $tags, PageRepository $pages, array $base): void
    {
        if ($method !== 'GET') {
            $this->notFound();
        }
        $base['categories'] = $repo->categories();
        $base['tags'] = $tags->all();
        $base['pages'] = $pages->published();
        if (preg_match('#^/page/([a-z0-9-]+)$#D', $path, $match)) {
            $page = $pages->publishedBySlug($match[1]);
            if ($page === null) {
                $this->notFound();
            }
            View::render('site/page', $base + array('page_title' => $page['title'], 'page' => $page));
            return;
        }
        if ($path === '/') {
            $members = $base['member'] !== null;
            $totalPages = (int) ceil($repo->countPosts('published', null, $members) / 20);
            $page = self::boundedPage($totalPages);
            View::render('site/home', $base + array('page_title' => '首页', 'categories' => $repo->categories(), 'tags' => $tags->all(), 'posts' => $repo->posts('published', null, $members, null, null, $page), 'page' => $page, 'total_pages' => $totalPages));
            return;
        }
        if (preg_match('#^/category/([a-z0-9-]+)$#D', $path, $match)) {
            $category = $repo->categoryBySlug($match[1]);
            if ($category === null) {
                $this->notFound();
            }
            $members = $base['member'] !== null;
            $totalPages = (int) ceil($repo->countPosts('published', (int) $category['id'], $members) / 20);
            $page = self::boundedPage($totalPages);
            View::render('site/category', $base + array('page_title' => $category['name'], 'category' => $category, 'posts' => $repo->posts('published', (int) $category['id'], $members, null, null, $page), 'page' => $page, 'total_pages' => $totalPages));
            return;
        }
        if (preg_match('#^/tag/([a-z0-9-]+)$#D', $path, $match)) {
            $tag = $tags->bySlug($match[1]);
            if ($tag === null) {
                $this->notFound();
            }
            $members = $base['member'] !== null;
            $totalPages = (int) ceil($repo->countPosts('published', null, $members, (int) $tag['id']) / 20);
            $page = self::boundedPage($totalPages);
            View::render('site/tag', $base + array('page_title' => $tag['name'], 'tag' => $tag, 'posts' => $repo->posts('published', null, $members, (int) $tag['id'], null, $page), 'page' => $page, 'total_pages' => $totalPages));
            return;
        }
        if ($path === '/search') {
            $query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
            if (mb_strlen($query) > 100) {
                $query = mb_substr($query, 0, 100);
            }
            $members = $base['member'] !== null;
            $totalPages = $query === '' ? 0 : (int) ceil($repo->countPosts('published', null, $members, null, $query) / 20);
            $page = self::boundedPage($totalPages);
            View::render('site/search', $base + array('page_title' => '搜索', 'query' => $query, 'posts' => $query === '' ? array() : $repo->posts('published', null, $members, null, $query, $page), 'page' => $page, 'total_pages' => $totalPages));
            return;
        }
        if (preg_match('#^/post/([a-z0-9-]+)$#D', $path, $match)) {
            $post = $repo->publishedPostBySlug($match[1]);
            if ($post === null) {
                $this->notFound();
            }
            if ($post['visibility'] === 'members' && $base['member'] === null) {
                View::render('member-required', $base + array('page_title' => '会员内容'), 403);
                return;
            }
            View::render('site/post', $base + array('page_title' => $post['title'], 'post' => $post, 'tags' => $tags->forPost((int) $post['id']), 'extra_fields' => (new ContentModelRepository($pdo))->publicFieldsForPost((int) $post['id'], $post['category_id'] === null ? null : (int) $post['category_id'])));
            return;
        }
        $this->notFound();
    }

    private function saveCategory(PDO $pdo, ContentRepository $repo, ?int $id, array $base): void
    {
        $this->requireCsrf();
        $input = array('name' => self::postString('name'), 'slug' => self::postString('slug'), 'sort_order' => self::postString('sort_order'), 'model_id' => self::postString('model_id'));
        try {
            $sortOrder = filter_var($input['sort_order'], FILTER_VALIDATE_INT);
            if ($sortOrder === false) {
                throw new InvalidArgumentException('栏目排序必须是整数。');
            }
            $models = new ContentModelRepository($pdo);
            $modelId = $input['model_id'] === '' ? (int) $models->defaultModel()['id'] : filter_var($input['model_id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
            if ($modelId === false) {
                throw new InvalidArgumentException('请选择有效的内容模型。');
            }
            $pdo->beginTransaction();
            try {
                $categoryId = $repo->saveCategory($id, $input['name'], $input['slug'], $sortOrder);
                $models->bindCategory($categoryId, $modelId);
                $pdo->commit();
            } catch (Throwable $error) {
                $pdo->rollBack();
                throw $error;
            }
            $_SESSION['flash'] = '栏目已保存。';
            $this->redirect('/admin/categories');
        } catch (InvalidArgumentException $error) {
            $message = $error->getMessage();
        } catch (PDOException $error) {
            $message = '保存失败，请检查栏目别名是否重复。';
        }
        View::render('admin/category-form', $base + array('page_title' => $id === null ? '新建栏目' : '编辑栏目', 'category' => $input + array('id' => $id), 'models' => (new ContentModelRepository($pdo))->all(), 'error' => $message), 422);
    }

    private function savePost(PDO $pdo, ContentRepository $repo, ?int $id, array $base): void
    {
        $this->requireCsrf();
        $input = array(
            'title' => self::postString('title'),
            'slug' => self::postString('slug'),
            'body' => self::postString('body'),
            'status' => self::postString('status'),
            'category_id' => self::postString('category_id'),
            'visibility' => self::postString('visibility') ?: 'public',
            'cover_media_id' => self::postString('cover_media_id'),
            'extra' => $_POST['extra'] ?? array()
        );
        $rawTags = $_POST['tag_ids'] ?? array();
        $selectedTags = is_array($rawTags) ? array_values(array_filter($rawTags, 'is_string')) : array();
        try {
            if (! is_array($rawTags) || count($rawTags) > 30 || array_filter($rawTags, fn ($tag) => ! is_string($tag) || ! ctype_digit($tag))) {
                throw new InvalidArgumentException('文章标签无效。');
            }
            $categoryId = $input['category_id'] === '' ? null : filter_var($input['category_id'], FILTER_VALIDATE_INT);
            if ($categoryId === false || ($categoryId !== null && $categoryId < 1)) {
                throw new InvalidArgumentException('Category is invalid.');
            }
            $coverId = $input['cover_media_id'] === '' ? null : filter_var($input['cover_media_id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
            if ($coverId === false) {
                throw new InvalidArgumentException('封面图片无效。');
            }
            if (! is_array($input['extra']) || count($input['extra']) > 100) {
                throw new InvalidArgumentException('扩展字段无效。');
            }
            $tags = new TagRepository($pdo);
            $media = new MediaRepository($pdo, $this->root . '/public');
            $models = new ContentModelRepository($pdo);
            $pdo->beginTransaction();
            try {
                $postId = $repo->savePost($id, $categoryId, $input['title'], $input['slug'], $input['body'], $input['status']);
                $repo->setVisibility($postId, $input['visibility']);
                $tags->setForPost($postId, $rawTags);
                $media->setCover($postId, $coverId);
                $models->setValuesForPost($postId, $categoryId, $input['extra']);
                $pdo->commit();
            } catch (Throwable $error) {
                $pdo->rollBack();
                throw $error;
            }
            $_SESSION['flash'] = '文章已保存。';
            $this->redirect('/admin/posts');
        } catch (InvalidArgumentException $error) {
            $message = $error->getMessage();
        } catch (PDOException $error) {
            $message = '保存失败，请检查文章别名是否重复。';
        }
        View::render('admin/post-form', $base + array('page_title' => $id === null ? '新建文章' : '编辑文章', 'post' => $input + array('id' => $id), 'categories' => $repo->categories(), 'tags' => (new TagRepository($pdo))->all(), 'media' => (new MediaRepository($pdo, $this->root . '/public'))->all(), 'selected_tags' => $selectedTags, 'extra_values' => is_array($input['extra']) ? $input['extra'] : array(), 'model_form' => self::postModelForm(new ContentModelRepository($pdo), $repo, $input), 'error' => $message), 422);
    }

    private static function postModelForm(ContentModelRepository $models, ContentRepository $repo, ?array $post): array
    {
        $defaultId = (int) $models->defaultModel()['id'];
        $categoryModels = array();
        foreach ($repo->categories() as $category) {
            $categoryModels[(int) $category['id']] = (int) ($category['model_id'] ?: $defaultId);
        }
        $activeCategory = (int) ($post['category_id'] ?? 0);
        $fields = array();
        foreach ($models->all() as $model) {
            $fields[(int) $model['id']] = $models->fields((int) $model['id']);
        }
        return array('default_id' => $defaultId, 'category_models' => $categoryModels, 'active_id' => $categoryModels[$activeCategory] ?? $defaultId, 'fields' => $fields);
    }

    private function modelAdminRoute(string $path, string $method, ContentModelRepository $models, array $base): void
    {
        if ($path === '/admin/models' && $method === 'GET') {
            View::render('admin/models', $base + array('page_title' => '内容模型', 'models' => $models->all()));
            return;
        }
        if ($path === '/admin/models/new' && $method === 'GET') {
            View::render('admin/model-form', $base + array('page_title' => '新建模型', 'model' => null, 'fields' => array(), 'error' => null));
            return;
        }
        if ($path === '/admin/models' && $method === 'POST') {
            $this->saveContentModel($models, null, $base);
            return;
        }
        if (preg_match('#^/admin/models/([1-9][0-9]*)/edit$#D', $path, $match)) {
            $id = (int) $match[1];
            $model = $models->model($id);
            if ($model === null) {
                $this->notFound();
            }
            if ($method === 'GET') {
                View::render('admin/model-form', $base + array('page_title' => '编辑模型', 'model' => $model, 'fields' => $models->fields($id), 'error' => null));
            } elseif ($method === 'POST') {
                $this->saveContentModel($models, $id, $base);
            } else {
                $this->notFound();
            }
            return;
        }
        if (preg_match('#^/admin/models/([1-9][0-9]*)/delete$#D', $path, $match) && $method === 'POST') {
            $this->requireCsrf();
            try {
                $models->deleteModel((int) $match[1]);
                $_SESSION['flash'] = '内容模型已删除。';
            } catch (InvalidArgumentException $error) {
                $_SESSION['flash'] = '操作失败：' . $error->getMessage();
            }
            $this->redirect('/admin/models');
        }
        if (preg_match('#^/admin/models/([1-9][0-9]*)/fields/new$#D', $path, $match)) {
            $model = $models->model((int) $match[1]);
            if ($model === null) {
                $this->notFound();
            }
            if ($method === 'GET') {
                View::render('admin/model-field-form', $base + array('page_title' => '新建扩展字段', 'model' => $model, 'field' => null, 'error' => null));
            } elseif ($method === 'POST') {
                $this->saveModelField($models, $model, null, $base);
            } else {
                $this->notFound();
            }
            return;
        }
        if (preg_match('#^/admin/models/([1-9][0-9]*)/fields/([1-9][0-9]*)/edit$#D', $path, $match)) {
            $model = $models->model((int) $match[1]);
            $field = $models->field((int) $match[2]);
            if ($model === null || $field === null || (int) $field['model_id'] !== (int) $model['id']) {
                $this->notFound();
            }
            if ($method === 'GET') {
                View::render('admin/model-field-form', $base + array('page_title' => '编辑扩展字段', 'model' => $model, 'field' => $field, 'error' => null));
            } elseif ($method === 'POST') {
                $this->saveModelField($models, $model, (int) $field['id'], $base);
            } else {
                $this->notFound();
            }
            return;
        }
        if (preg_match('#^/admin/models/([1-9][0-9]*)/fields/([1-9][0-9]*)/delete$#D', $path, $match) && $method === 'POST') {
            $this->requireCsrf();
            try {
                $models->deleteField((int) $match[1], (int) $match[2]);
                $_SESSION['flash'] = '扩展字段已删除。';
            } catch (InvalidArgumentException $error) {
                $_SESSION['flash'] = '操作失败：' . $error->getMessage();
            }
            $this->redirect('/admin/models/' . (int) $match[1] . '/edit');
        }
        $this->notFound();
    }

    private function saveContentModel(ContentModelRepository $models, ?int $id, array $base): void
    {
        $this->requireCsrf();
        $input = array('id' => $id, 'name' => self::postString('name'), 'slug' => self::postString('slug'));
        try {
            $models->saveModel($id, $input['name'], $input['slug']);
            $_SESSION['flash'] = '内容模型已保存。';
            $this->redirect('/admin/models');
        } catch (InvalidArgumentException $error) {
            $message = $error->getMessage();
        } catch (PDOException $error) {
            $message = '模型标识已存在。';
        }
        View::render('admin/model-form', $base + array('page_title' => $id === null ? '新建模型' : '编辑模型', 'model' => $input, 'fields' => $id === null ? array() : $models->fields($id), 'error' => $message), 422);
    }

    private function saveModelField(ContentModelRepository $models, array $model, ?int $id, array $base): void
    {
        $this->requireCsrf();
        $input = array('id' => $id, 'model_id' => $model['id'], 'name' => self::postString('name'), 'label' => self::postString('label'), 'field_type' => self::postString('field_type'), 'options_text' => self::postString('options_text'), 'is_required' => isset($_POST['is_required']) ? 1 : 0, 'sort_order' => self::postString('sort_order'));
        try {
            $sortOrder = filter_var($input['sort_order'], FILTER_VALIDATE_INT);
            if ($sortOrder === false) {
                throw new InvalidArgumentException('字段排序必须是整数。');
            }
            $models->saveField($id, (int) $model['id'], $input['name'], $input['label'], $input['field_type'], $input['options_text'], (bool) $input['is_required'], $sortOrder);
            $_SESSION['flash'] = '扩展字段已保存。';
            $this->redirect('/admin/models/' . (int) $model['id'] . '/edit');
        } catch (InvalidArgumentException $error) {
            $message = $error->getMessage();
        } catch (PDOException $error) {
            $message = '字段标识已存在。';
        }
        View::render('admin/model-field-form', $base + array('page_title' => $id === null ? '新建扩展字段' : '编辑扩展字段', 'model' => $model, 'field' => $input, 'error' => $message), 422);
    }

    private function tagAdminRoute(string $path, string $method, TagRepository $tags, array $base): void
    {
        if ($path === '/admin/tags' && $method === 'GET') {
            View::render('admin/tags', $base + array('page_title' => '标签管理', 'tags' => $tags->all()));
            return;
        }
        if ($path === '/admin/tags/new' && $method === 'GET') {
            View::render('admin/tag-form', $base + array('page_title' => '新建标签', 'tag' => null, 'error' => null));
            return;
        }
        if ($path === '/admin/tags' && $method === 'POST') {
            $this->saveTag($tags, null, $base);
            return;
        }
        if (preg_match('#^/admin/tags/([1-9][0-9]*)/edit$#D', $path, $match)) {
            $id = (int) $match[1];
            $tag = $tags->byId($id);
            if ($tag === null) {
                $this->notFound();
            }
            if ($method === 'POST') {
                $this->saveTag($tags, $id, $base);
            } elseif ($method === 'GET') {
                View::render('admin/tag-form', $base + array('page_title' => '编辑标签', 'tag' => $tag, 'error' => null));
            } else {
                $this->notFound();
            }
            return;
        }
        if (preg_match('#^/admin/tags/([1-9][0-9]*)/delete$#D', $path, $match) && $method === 'POST') {
            $this->requireCsrf();
            $tags->delete((int) $match[1]);
            $_SESSION['flash'] = '标签已删除。';
            $this->redirect('/admin/tags');
        }
        $this->notFound();
    }

    private function saveTag(TagRepository $tags, ?int $id, array $base): void
    {
        $this->requireCsrf();
        $input = array('id' => $id, 'name' => self::postString('name'), 'slug' => self::postString('slug'));
        try {
            $tags->save($id, $input['name'], $input['slug']);
            $_SESSION['flash'] = '标签已保存。';
            $this->redirect('/admin/tags');
        } catch (InvalidArgumentException $error) {
            $message = $error->getMessage();
        } catch (PDOException $error) {
            $message = '标签别名已存在。';
        }
        View::render('admin/tag-form', $base + array('page_title' => $id === null ? '新建标签' : '编辑标签', 'tag' => $input, 'error' => $message), 422);
    }

    private function memberRoute(string $path, string $method, PDO $pdo, array $base): void
    {
        $members = new MemberRepository($pdo);
        if ($path === '/member/logout' && $method === 'POST') {
            $this->requireCsrf();
            Auth::memberLogout();
            $this->redirect('/');
        }
        if ($path === '/member/register') {
            if ($base['member'] !== null) {
                $this->redirect('/member/profile');
            }
            $error = null;
            if ($method === 'POST') {
                $this->requireCsrf();
                try {
                    $id = $members->register(self::postString('email'), self::postString('display_name'), self::postString('password'));
                    session_regenerate_id(true);
                    $_SESSION['member_id'] = $id;
                    $this->redirect('/member/profile');
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                } catch (PDOException $exception) {
                    $error = '该邮箱已注册。';
                }
            } elseif ($method !== 'GET') {
                $this->notFound();
            }
            View::render('member/register', $base + array('page_title' => '会员注册', 'error' => $error, 'email' => self::postString('email'), 'display_name' => self::postString('display_name')), $error ? 422 : 200);
            return;
        }
        if ($path === '/member/login') {
            if ($base['member'] !== null) {
                $this->redirect('/member/profile');
            }
            $error = null;
            if ($method === 'POST') {
                $this->requireCsrf();
                if (Auth::memberLogin($pdo, self::postString('email'), self::postString('password'))) {
                    $this->redirect('/member/profile');
                }
                $error = '邮箱或密码错误，账号已停用，或尝试次数过多。';
            } elseif ($method !== 'GET') {
                $this->notFound();
            }
            View::render('member/login', $base + array('page_title' => '会员登录', 'error' => $error), $error ? 422 : 200);
            return;
        }
        if ($path === '/member/profile') {
            if ($base['member'] === null) {
                $this->redirect('/member/login');
            }
            $error = null;
            if ($method === 'POST') {
                $this->requireCsrf();
                try {
                    $members->updateName((int) $base['member']['id'], self::postString('display_name'));
                    $_SESSION['flash'] = '资料已更新。';
                    $this->redirect('/member/profile');
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                }
            } elseif ($method !== 'GET') {
                $this->notFound();
            }
            View::render('member/profile', $base + array('page_title' => '会员中心', 'error' => $error, 'member' => $members->byId((int) $base['member']['id'])), $error ? 422 : 200);
            return;
        }
        if ($path === '/member/password' && $method === 'POST') {
            if ($base['member'] === null) {
                $this->redirect('/member/login');
            }
            $this->requireCsrf();
            try {
                $members->changePassword((int) $base['member']['id'], self::postString('current_password'), self::postString('new_password'));
                $_SESSION['flash'] = '密码已更新。';
                $this->redirect('/member/profile');
            } catch (InvalidArgumentException $error) {
                View::render('member/profile', $base + array('page_title' => '会员中心', 'error' => $error->getMessage()), 422);
                return;
            }
        }
        $this->notFound();
    }

    private function memberAdminRoute(string $path, string $method, MemberRepository $members, array $base): void
    {
        if ($path === '/admin/members' && $method === 'GET') {
            View::render('admin/members', $base + array('page_title' => '会员管理', 'members' => $members->all()));
            return;
        }
        if (preg_match('#^/admin/members/([1-9][0-9]*)/status$#D', $path, $match) && $method === 'POST') {
            $this->requireCsrf();
            $id = (int) $match[1];
            if ($members->byId($id) === null) {
                $this->notFound();
            }
            $members->setStatus($id, self::postString('status'));
            $_SESSION['flash'] = '会员状态已更新。';
            $this->redirect('/admin/members');
        }
        $this->notFound();
    }

    private function accessAdminRoute(string $path, string $method, AccessRepository $access, array $base): void
    {
        if ($path === '/admin/roles' && $method === 'GET') {
            View::render('admin/roles', $base + array('page_title' => '角色权限', 'roles' => $access->roles()));
            return;
        }
        if ($path === '/admin/roles/new' && $method === 'GET') {
            View::render('admin/role-form', $base + array('page_title' => '新建角色', 'role' => null, 'grants' => array(), 'error' => null));
            return;
        }
        if ($path === '/admin/roles' && $method === 'POST') {
            $this->saveRole($access, null, $base);
            return;
        }
        if (preg_match('#^/admin/roles/([1-9][0-9]*)/edit$#D', $path, $match)) {
            $id = (int) $match[1];
            $role = $access->role($id);
            if ($role === null || $role['slug'] === 'owner') {
                $this->notFound();
            }
            if ($method === 'POST') {
                $this->saveRole($access, $id, $base);
            } elseif ($method === 'GET') {
                View::render('admin/role-form', $base + array('page_title' => '编辑角色', 'role' => $role, 'grants' => $access->grants($id), 'error' => null));
            } else {
                $this->notFound();
            }
            return;
        }
        if (preg_match('#^/admin/roles/([1-9][0-9]*)/delete$#D', $path, $match) && $method === 'POST') {
            $this->requireCsrf();
            try {
                $access->deleteRole((int) $match[1]);
                $_SESSION['flash'] = '角色已删除。';
            } catch (InvalidArgumentException $error) {
                $_SESSION['flash'] = $error->getMessage();
            }
            $this->redirect('/admin/roles');
        }
        if ($path === '/admin/admins') {
            if ($method === 'POST') {
                $this->requireCsrf();
                try {
                    $access->createAdmin(self::postString('username'), self::postString('password'), (int) self::postString('role_id'), $base['user']['role_slug'] === 'owner');
                    $_SESSION['flash'] = '后台账号已创建。';
                    $this->redirect('/admin/admins');
                } catch (InvalidArgumentException $error) {
                    $message = $error->getMessage();
                } catch (PDOException $error) {
                    $message = '后台账号已存在。';
                }
                View::render('admin/admins', $base + array('page_title' => '后台账号', 'admins' => $access->admins(), 'roles' => $access->roles(), 'error' => $message), 422);
                return;
            }
            if ($method === 'GET') {
                View::render('admin/admins', $base + array('page_title' => '后台账号', 'admins' => $access->admins(), 'roles' => $access->roles(), 'error' => null));
                return;
            }
        }
        if (preg_match('#^/admin/admins/([1-9][0-9]*)/edit$#D', $path, $match)) {
            $id = (int) $match[1];
            $admin = $access->admin($id);
            if ($admin === null) {
                $this->notFound();
            }
            if ($admin['role_slug'] === 'owner' && $base['user']['role_slug'] !== 'owner') {
                View::render('forbidden', $base + array('page_title' => '无权访问'), 403);
                return;
            }
            $error = null;
            if ($method === 'POST') {
                $this->requireCsrf();
                try {
                    $access->updateAdmin($id, (int) self::postString('role_id'), self::postString('new_password'), $base['user']['role_slug'] === 'owner');
                    $_SESSION['flash'] = '后台账号已更新。';
                    $this->redirect('/admin/admins');
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                }
            } elseif ($method !== 'GET') {
                $this->notFound();
            }
            View::render('admin/admin-form', $base + array('page_title' => '编辑后台账号', 'account' => $admin, 'roles' => $access->roles(), 'error' => $error), $error ? 422 : 200);
            return;
        }
        if (preg_match('#^/admin/admins/([1-9][0-9]*)/delete$#D', $path, $match) && $method === 'POST') {
            $this->requireCsrf();
            try {
                $access->deleteAdmin((int) $match[1], (int) $base['user']['id']);
                $_SESSION['flash'] = '后台账号已删除。';
            } catch (InvalidArgumentException $error) {
                $_SESSION['flash'] = $error->getMessage();
            }
            $this->redirect('/admin/admins');
        }
        $this->notFound();
    }

    private function saveRole(AccessRepository $access, ?int $id, array $base): void
    {
        $this->requireCsrf();
        $raw = $_POST['permissions'] ?? array();
        $grants = is_array($raw) ? $raw : array();
        $role = array('id' => $id, 'name' => self::postString('name'), 'slug' => self::postString('slug'));
        try {
            if (! is_array($raw)) {
                throw new InvalidArgumentException('权限列表无效。');
            }
            $access->saveRole($id, $role['name'], $role['slug'], $grants);
            $_SESSION['flash'] = '角色权限已保存。';
            $this->redirect('/admin/roles');
        } catch (InvalidArgumentException $error) {
            $message = $error->getMessage();
        } catch (PDOException $error) {
            $message = '角色标识已存在。';
        }
        View::render('admin/role-form', $base + array('page_title' => $id === null ? '新建角色' : '编辑角色', 'role' => $role, 'grants' => $grants, 'error' => $message), 422);
    }

    private function requireCsrf(): void
    {
        $token = $_POST['_csrf'] ?? null;
        if (! Auth::validCsrf(is_string($token) ? $token : null)) {
            http_response_code(403);
            exit('CSRF verification failed.');
        }
    }

    private static function postString(string $key): string
    {
        return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }

    private static function pageNumber(): int
    {
        $input = $_GET['page'] ?? '1';
        if (! is_string($input) && ! is_int($input)) {
            return 1;
        }
        $page = filter_var($input, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 100000)));
        return $page === false ? 1 : $page;
    }

    private static function boundedPage(int $totalPages): int
    {
        return min(self::pageNumber(), max(1, $totalPages));
    }

    private static function firstAdminPath(array $can): string
    {
        foreach (array(
            'dashboard.view' => '/admin',
            'content.view' => '/admin/posts',
            'taxonomy.manage' => '/admin/categories',
            'content.manage' => '/admin/posts/new',
            'media.manage' => '/admin/media',
            'members.manage' => '/admin/members',
            'access.manage' => '/admin/roles',
            'settings.manage' => '/admin/settings',
            'license.manage' => '/admin/license'
        ) as $permission => $path) {
            if ($can[$permission] ?? false) {
                return $path;
            }
        }
        return '/admin';
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }

    private function notFound(): never
    {
        View::render('not-found', array('page_title' => '页面不存在'), 404);
        exit;
    }
}

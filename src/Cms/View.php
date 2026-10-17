<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use RuntimeException;

final class View
{
    public static function render(string $template, array $data = array(), int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('X-Frame-Options: DENY');
        $root = dirname(__DIR__, 2);
        $pageTitle = $data['page_title'] ?? 'FangdaysCMS';
        $siteName = $data['site_name'] ?? 'FangdaysCMS';
        $admin = $data['admin'] ?? false;
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        extract($data, EXTR_SKIP);
        ob_start();
        require $root . '/views/' . $template . '.php';
        $content = ob_get_clean();
        require $root . (str_starts_with($template, 'site/') ? '/views/site/layout.php' : '/views/layout.php');
    }

    public static function renderSiteHtml(string $html, array $data = array(), int $status = 200): void
    {
        if (preg_match('/^\s*(?:<!doctype\s+html|<html\b)/i', $html)) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: same-origin');
            header('X-Frame-Options: DENY');
            echo $html;
            return;
        }
        $data['html'] = $html;
        self::render('site/template', $data, $status);
    }

    public static function e($value): string
    {
        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function pbootFragment(string $name, OperationsRepository $operations, array $labels = array(), array $company = array()): string
    {
        return OperationTags::html(self::fragmentSource($name), $operations, $labels, $company);
    }

    public static function pbootFilterFragment(string $name, array $fields, string $path, array $params, string $fieldName): string
    {
        if (! preg_match('/^ext_[a-z][a-z0-9_]{0,58}$/D', $fieldName)) {
            throw new RuntimeException('筛选字段名称无效。');
        }
        $source = str_replace('{{field}}', $fieldName, self::fragmentSource($name));
        return ContentFilterTags::html($source, $fields, $path, $params);
    }

    private static function fragmentSource(string $name): string
    {
        if (! preg_match('/^[a-z][a-z0-9-]*$/D', $name)) {
            throw new RuntimeException('模板片段名称无效。');
        }
        $source = file_get_contents(dirname(__DIR__, 2) . '/templates/default/' . $name . '.html');
        if ($source === false) {
            throw new RuntimeException('模板片段无法读取。');
        }
        return $source;
    }

    public static function csrf(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::e(Auth::csrfToken()) . '">';
    }

    public static function categoryLink(array $category): string
    {
        return ($category['type'] ?? 'list') === 'link' && ($category['outlink'] ?? '') !== ''
            ? (string) $category['outlink']
            : '/category/' . rawurlencode((string) $category['slug']);
    }

    public static function postLink(array $post): string
    {
        return ($post['outlink'] ?? '') !== ''
            ? (string) $post['outlink']
            : '/post/' . rawurlencode((string) $post['slug']);
    }

    public static function pagination(int $page, int $totalPages, string $path, array $params = array()): string
    {
        if ($totalPages <= 1) {
            return '';
        }
        $link = static fn (int $number): string => self::e($path . '?' . http_build_query(array_merge($params, array('page' => $number))));
        $html = '<nav class="pager" aria-label="分页">';
        if ($page > 1) {
            $html .= '<a href="' . $link($page - 1) . '">上一页</a>';
        }
        $html .= '<span>第 ' . min($page, $totalPages) . ' / ' . $totalPages . ' 页</span>';
        if ($page < $totalPages) {
            $html .= '<a href="' . $link($page + 1) . '">下一页</a>';
        }
        return $html . '</nav>';
    }
}

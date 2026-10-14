<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

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

    public static function e($value): string
    {
        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;

/** Filesystem-backed, non-executable HTML templates with a small Pboot tag vocabulary. */
final class SiteTemplate
{
    private const DIRECTORY = 'templates/site';

    public static function names(): array
    {
        $directory = dirname(__DIR__, 2) . '/' . self::DIRECTORY;
        $names = array();
        if (! is_dir($directory)) {
            return $names;
        }
        foreach (scandir($directory) ?: array() as $name) {
            if (self::validName($name) && is_file($directory . '/' . $name) && ! is_link($directory . '/' . $name)) {
                $names[] = $name;
            }
        }
        sort($names, SORT_STRING);
        return $names;
    }

    public static function check(string $name): void
    {
        if ($name !== '' && (! self::validName($name) || ! in_array($name, self::names(), true))) {
            throw new InvalidArgumentException('请选择现有的 HTML 模板文件。');
        }
    }

    public static function render(string $name, array $context): string
    {
        self::check($name);
        if ($name === '') {
            throw new InvalidArgumentException('未选择 HTML 模板。');
        }
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . self::DIRECTORY . '/' . $name);
        if ($source === false || strlen($source) > 500000) {
            throw new InvalidArgumentException('HTML 模板无法读取或文件过大。');
        }
        $posts = $context['posts'] ?? array();
        $source = preg_replace_callback('~\{pboot:list(?:\s+[^{}]*)?\}(.*?)\{/pboot:list\}~si', static function (array $match) use ($posts): string {
            $html = '';
            foreach ($posts as $index => $post) {
                $fields = array(
                    'n' => $index,
                    'i' => $index + 1,
                    'id' => $post['id'],
                    'title' => $post['title'],
                    'link' => View::postLink($post),
                    'date' => date('Y-m-d', (int) $post['published_at']),
                    'ico' => $post['cover_path'] ?? '',
                    'description' => $post['seo_description'] ?: RichText::summary($post['body'], $post['body_format'], 120),
                );
                $html .= self::replaceSquareTags($match[1], 'list', $fields);
            }
            return $html;
        }, $source) ?? $source;

        $category = $context['category'] ?? array();
        $sort = array(
            'scode' => $category['id'] ?? '',
            'name' => $category['name'] ?? '',
            'link' => $category ? View::categoryLink($category) : '',
            'type' => ($category['type'] ?? '') === 'single' ? '1' : '2',
            'ico' => $category['thumbnail_path'] ?? '',
            'pic' => $category['banner_path'] ?? '',
            'title' => $category['seo_title'] ?? '',
            'keywords' => $category['seo_keywords'] ?? '',
            'description' => $category['seo_description'] ?? '',
            'listtpl' => $context['list_template'] ?? '',
            'contenttpl' => $context['content_template'] ?? '',
        );
        $post = $context['post'] ?? array();
        $single = ($category['type'] ?? '') === 'single' && ! $post;
        $content = array(
            'id' => $single ? ($category['id'] ?? '') : ($post['id'] ?? ''),
            'scode' => $single ? ($category['id'] ?? '') : ($post['category_id'] ?? ''),
            'title' => $single ? ($category['single_title'] ?? '') : ($post['title'] ?? ''),
            'subtitle' => $post['subtitle'] ?? '',
            'author' => $post['author'] ?? '',
            'source' => $post['source'] ?? '',
            'link' => $single ? View::categoryLink($category) : ($post ? View::postLink($post) : ''),
            'date' => $post ? date('Y-m-d', (int) $post['published_at']) : '',
            'ico' => $single ? ($context['cover']['path'] ?? '') : ($post['cover_path'] ?? ''),
            'keywords' => $single ? ($category['seo_keywords'] ?? '') : ($post['seo_keywords'] ?? ''),
            'description' => $single ? ($category['seo_description'] ?? '') : ($post['seo_description'] ?? ''),
        );
        foreach ($context['extra_fields'] ?? array() as $field) {
            $content[$field['name']] = $field['value'];
        }
        $body = $single
            ? RichText::render((string) ($category['single_body'] ?? ''), (string) ($category['single_body_format'] ?? 'plain'))
            : ($post ? RichText::render((string) $post['body'], (string) $post['body_format']) : '');
        $source = preg_replace_callback('/\{(?:sort|content):[a-z][a-z0-9_]*\}/i', static function (array $match) use ($sort, $content, $body): string {
            [$kind, $field] = explode(':', substr($match[0], 1, -1), 2);
            $field = strtolower($field);
            if ($kind === 'content' && $field === 'content') {
                return $body;
            }
            return View::e(($kind === 'sort' ? $sort : $content)[$field] ?? '');
        }, $source) ?? $source;
        $globals = array(
            'sitetitle' => $context['site_name'] ?? 'FangdaysCMS',
            'pagetitle' => ($context['seo_title'] ?? '') ?: (($context['page_title'] ?? '') . ' · ' . ($context['site_name'] ?? 'FangdaysCMS')),
            'pagekeywords' => $context['seo_keywords'] ?? '',
            'pagedescription' => $context['seo_description'] ?? ($context['site_description'] ?? ''),
            'pagebar' => View::pagination((int) ($context['page'] ?? 1), (int) ($context['total_pages'] ?? 0), (string) ($context['page_path'] ?? '/'), $context['filter_params'] ?? array()),
        );
        return preg_replace_callback('/\{pboot:([a-z]+)\}/i', static function (array $match) use ($globals): string {
            $key = strtolower($match[1]);
            return $key === 'pagebar' ? ($globals[$key] ?? '') : View::e($globals[$key] ?? '');
        }, $source) ?? $source;
    }

    private static function replaceSquareTags(string $source, string $prefix, array $fields): string
    {
        return preg_replace_callback('/\[' . preg_quote($prefix, '/') . ':([a-z][a-z0-9_]*)\]/i', static fn (array $match): string => View::e($fields[strtolower($match[1])] ?? ''), $source) ?? $source;
    }

    private static function validName(string $name): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9-]{0,63}\.html$/D', $name);
    }
}

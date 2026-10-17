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
        $source = ContentFilterTags::html($source, $context['filter_fields'] ?? array(), (string) ($context['page_path'] ?? '/'), $context['filter_params'] ?? array());
        $posts = $context['posts'] ?? array();
        $listExtras = $context['list_extra_fields'] ?? array();
        $listGalleries = $context['list_galleries'] ?? array();
        $listTags = $context['list_tags'] ?? array();
        $source = preg_replace_callback('~\{pboot:list(?:\s+[^{}]*)?\}(.*?)\{/pboot:list\}~si', static function (array $match) use ($posts, $listExtras, $listGalleries, $listTags): string {
            $html = '';
            foreach ($posts as $index => $post) {
                $postId = (int) $post['id'];
                $extras = $listExtras[$postId] ?? array();
                $gallery = $listGalleries[$postId] ?? array();
                $tags = $listTags[$postId] ?? array();
                $fields = array(
                    'n' => $index,
                    'i' => $index + 1,
                    'id' => $postId,
                    'scode' => $post['category_id'] ?? '',
                    'sortname' => $post['category_name'] ?? '',
                    'sortlink' => isset($post['category_slug']) && $post['category_slug'] !== null ? '/category/' . rawurlencode($post['category_slug']) : '',
                    'title' => $post['title'],
                    'titlecolor' => $post['title_color'] ?? '',
                    'subtitle' => $post['subtitle'] ?? '',
                    'author' => $post['author'] ?? '',
                    'source' => $post['source'] ?? '',
                    'link' => View::postLink($post),
                    'outlink' => $post['outlink'] ?? '',
                    'date' => date('Y-m-d', (int) $post['published_at']),
                    'ico' => $post['cover_path'] ?? '',
                    'isico' => ! empty($post['cover_path']) ? 1 : 0,
                    'pics' => implode(',', array_column($gallery, 'path')),
                    'ispics' => $gallery ? 1 : 0,
                    'tags' => implode(',', array_column($tags, 'name')),
                    'keywords' => $post['seo_keywords'] ?? '',
                    'description' => $post['seo_description'] ?: RichText::summary($post['body'], $post['body_format'], 120),
                    'istop' => $post['is_top'] ?? 0,
                    'isrecommend' => $post['is_recommended'] ?? 0,
                    'isheadline' => $post['is_headline'] ?? 0,
                );
                foreach ($extras as $field) {
                    $fields[$field['name']] = $field['value'];
                }
                $item = self::renderCheckboxes($match[1], $postId, $extras, '[list:id]');
                $item = self::renderPictures($item, $postId, $gallery, '[list:id]');
                $item = self::renderTags($item, $postId, $tags, '[list:id]');
                $html .= self::replaceSquareTags($item, 'list', $fields);
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
        $coverPath = $single ? ($context['cover']['path'] ?? '') : ($post['cover_path'] ?? '');
        $attachment = $context['attachment'] ?? null;
        $content = array(
            'id' => $single ? ($category['id'] ?? '') : ($post['id'] ?? ''),
            'scode' => $single ? ($category['id'] ?? '') : ($post['category_id'] ?? ''),
            'sortname' => $category['name'] ?? '',
            'sortlink' => $category ? View::categoryLink($category) : '',
            'title' => $single ? ($category['single_title'] ?? '') : ($post['title'] ?? ''),
            'titlecolor' => $post['title_color'] ?? '',
            'subtitle' => $post['subtitle'] ?? '',
            'author' => $post['author'] ?? '',
            'source' => $post['source'] ?? '',
            'link' => $single ? View::categoryLink($category) : ($post ? View::postLink($post) : ''),
            'outlink' => $post['outlink'] ?? '',
            'date' => $post ? date('Y-m-d', (int) $post['published_at']) : '',
            'ico' => $coverPath,
            'isico' => $coverPath !== '' ? 1 : 0,
            'pics' => implode(',', array_column($context['gallery'] ?? array(), 'path')),
            'ispics' => ! empty($context['gallery']) ? 1 : 0,
            'tags' => implode(',', array_column($context['tags'] ?? array(), 'name')),
            'enclosure' => $attachment === null ? '' : ($single ? View::categoryLink($category) : View::postLink($post)) . '/attachment',
            'enclosuresize' => $attachment['bytes'] ?? '',
            'keywords' => $single ? ($category['seo_keywords'] ?? '') : ($post['seo_keywords'] ?? ''),
            'description' => $single ? ($category['seo_description'] ?? '') : ($post['seo_description'] ?? ''),
            'istop' => $post['is_top'] ?? 0,
            'isrecommend' => $post['is_recommended'] ?? 0,
            'isheadline' => $post['is_headline'] ?? 0,
        );
        $contentExtras = array();
        foreach ($context['extra_fields'] ?? array() as $field) {
            $content[$field['name']] = $field['value'];
            $contentExtras[$field['name']] = $field;
        }
        $source = self::renderCheckboxes($source, (int) $content['id'], $contentExtras, '{content:id}');
        $source = self::renderPictures($source, (int) $content['id'], $context['gallery'] ?? array(), '{content:id}');
        $source = self::renderTags($source, $post ? (int) $post['id'] : 0, $context['tags'] ?? array(), '{content:id}');
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

    private static function renderCheckboxes(string $source, int $currentId, array $fields, string $placeholder): string
    {
        return preg_replace_callback('~\{pboot:checkbox\s+((?:[^{}]|\{content:id\})*)\}(.*?)\{/pboot:checkbox\}~si', static function (array $match) use ($currentId, $fields, $placeholder): string {
            $attributes = self::attributes($match[1]);
            $id = $attributes['id'] ?? '';
            $name = $attributes['field'] ?? '';
            if ($currentId < 1 || ! in_array($id, array((string) $currentId, $placeholder), true)
                || ! preg_match('/^ext_[a-z][a-z0-9_]*$/D', $name)
                || ($fields[$name]['type'] ?? '') !== 'multi') {
                return '';
            }
            $html = '';
            $choices = array_values(array_filter(explode(',', (string) $fields[$name]['value']), static fn (string $value): bool => $value !== ''));
            foreach ($choices as $index => $value) {
                $html .= self::replaceSquareTags($match[2], 'checkbox', array('n' => $index, 'i' => $index + 1, 'text' => $value));
            }
            return $html;
        }, $source) ?? $source;
    }

    private static function renderPictures(string $source, int $currentId, array $gallery, string $placeholder): string
    {
        return preg_replace_callback('~\{pboot:pics\s+((?:[^{}]|\{content:id\})*)\}(.*?)\{/pboot:pics\}~si', static function (array $match) use ($currentId, $gallery, $placeholder): string {
            $attributes = self::attributes($match[1]);
            if ($currentId < 1 || ! in_array($attributes['id'] ?? '', array((string) $currentId, $placeholder), true)
                || ! in_array($attributes['field'] ?? 'pics', array('pics'), true)) {
                return '';
            }
            $limit = self::loopLimit($attributes['num'] ?? null);
            $html = '';
            foreach (array_slice($gallery, 0, $limit) as $index => $image) {
                $html .= self::replaceSquareTags($match[2], 'pics', array('n' => $index, 'i' => $index + 1, 'src' => $image['path'] ?? '', 'title' => $image['title'] ?? ''));
            }
            return $html;
        }, $source) ?? $source;
    }

    private static function renderTags(string $source, int $currentId, array $tags, string $placeholder): string
    {
        return preg_replace_callback('~\{pboot:tags\s+((?:[^{}]|\{content:id\})*)\}(.*?)\{/pboot:tags\}~si', static function (array $match) use ($currentId, $tags, $placeholder): string {
            $attributes = self::attributes($match[1]);
            if ($currentId < 1 || ! in_array($attributes['id'] ?? '', array((string) $currentId, $placeholder), true)) {
                return '';
            }
            $limit = self::loopLimit($attributes['num'] ?? null);
            $html = '';
            foreach (array_slice($tags, 0, $limit) as $index => $tag) {
                $html .= self::replaceSquareTags($match[2], 'tags', array('n' => $index, 'i' => $index + 1, 'link' => '/tag/' . rawurlencode((string) ($tag['slug'] ?? '')), 'text' => $tag['name'] ?? ''));
            }
            return $html;
        }, $source) ?? $source;
    }

    private static function loopLimit(?string $raw): int
    {
        if ($raw === null) {
            return 100;
        }
        return ctype_digit($raw) && (int) $raw >= 1 && (int) $raw <= 100 ? (int) $raw : 0;
    }

    private static function attributes(string $source): array
    {
        preg_match_all('~\b([a-z]+)\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|(\{content:id\}|[^\s}]+))~i', $source, $matches, PREG_SET_ORDER);
        $attributes = array();
        foreach ($matches as $match) {
            $attributes[strtolower($match[1])] = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : ($match[4] ?? ''));
        }
        return $attributes;
    }

    private static function validName(string $name): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9-]{0,63}\.html$/D', $name);
    }
}

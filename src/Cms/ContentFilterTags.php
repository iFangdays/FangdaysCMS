<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

/** Render Pboot-style select tags inside a trusted HTML template fragment. */
final class ContentFilterTags
{
    public static function html(string $source, array $fields, string $path, array $query = array()): string
    {
        $byName = array();
        foreach ($fields as $field) {
            if (in_array($field['field_type'] ?? null, array('select', 'multi'), true) && is_string($field['name'] ?? null)) {
                $byName[$field['name']] = $field;
            }
        }
        $source = preg_replace_callback('~\{pboot:select\s+([^}]*)\}(.*?)\{/pboot:select\}~s', static function (array $match) use ($byName, $path, $query): string {
            $name = self::attributes($match[1])['field'] ?? '';
            if (! isset($byName[$name])) {
                return '';
            }
            $field = $byName[$name];
            $current = (string) ($field['current'] ?? '');
            $output = '';
            foreach ($field['options'] as $index => $value) {
                $value = (string) $value;
                $body = preg_replace_callback('~\{pboot:if\(\s*[\x27\x22]?\[select:(value|current)\][\x27\x22]?\s*(==|!=)\s*[\x27\x22]?\[select:(value|current)\][\x27\x22]?\s*\)\}(.*?)\{/pboot:if\}~s', static function (array $condition) use ($value, $current): string {
                    $left = $condition[1] === 'value' ? $value : $current;
                    $right = $condition[3] === 'value' ? $value : $current;
                    $matches = $condition[2] === '==' ? $left === $right : $left !== $right;
                    $branches = explode('{else}', $condition[4], 2);
                    return $matches ? $branches[0] : ($branches[1] ?? '');
                }, $match[2]) ?? $match[2];
                $output .= strtr($body, array(
                    '[select:n]' => (string) $index,
                    '[select:i]' => (string) ($index + 1),
                    '[select:value]' => View::e($value),
                    '[select:current]' => View::e($current),
                    '[select:link]' => View::e(self::link($path, $query, $name, $value)),
                ));
            }
            return $output;
        }, $source) ?? $source;

        return preg_replace_callback('~\{pboot:selectall\s+([^}]*)\}~', static function (array $match) use ($byName, $path, $query): string {
            $attributes = self::attributes($match[1]);
            $name = $attributes['field'] ?? '';
            if (! isset($byName[$name])) {
                return '';
            }
            $active = (string) ($byName[$name]['current'] ?? '') === '';
            $class = $active ? ($attributes['active'] ?? '') : ($attributes['class'] ?? '');
            $text = $attributes['text'] ?? '全部';
            return '<a href="' . View::e(self::link($path, $query, $name, null)) . '" class="' . View::e($class) . '"' . ($active ? ' aria-current="true"' : '') . '>' . View::e($text) . '</a>';
        }, $source) ?? $source;
    }

    private static function attributes(string $source): array
    {
        $result = array();
        preg_match_all('~\b([a-z]+)\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s}]+))~', $source, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $result[$match[1]] = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : ($match[4] ?? ''));
        }
        return $result;
    }

    private static function link(string $path, array $query, string $field, ?string $value): string
    {
        unset($query['page'], $query[$field]);
        if ($value !== null) {
            $query[$field] = $value;
        }
        return $path . ($query ? '?' . http_build_query($query) : '');
    }
}

<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

/** Resolve Pboot-style text tags before the view escapes the output. */
final class OperationTags
{
    public static function plain(string $source, array $labels, array $company): string
    {
        $source = preg_replace_callback('/\{pboot:company([A-Za-z]+)\}/', static fn (array $match): string => (string) ($company[strtolower($match[1])] ?? ''), $source) ?? $source;
        return preg_replace_callback('/\{label:([A-Za-z][A-Za-z0-9_]*)\}/', static fn (array $match): string => (string) ($labels[$match[1]] ?? ''), $source) ?? $source;
    }

    /** Render the supported Pboot operation tags in a trusted HTML fragment. */
    public static function html(string $source, OperationsRepository $operations, array $labels = array(), array $company = array()): string
    {
        // Keep database values out of later tag parsing, including values that contain tag syntax.
        $tokens = array();
        $prefix = 'FD_TAG_' . bin2hex(random_bytes(12)) . '_';
        $token = static function (string $value) use (&$tokens, $prefix): string {
            $key = $prefix . count($tokens) . '_';
            $tokens[$key] = View::e($value);
            return $key;
        };
        $source = preg_replace_callback('/\{pboot:company([A-Za-z]+)\}/', static fn (array $match): string => $token((string) ($company[strtolower($match[1])] ?? '')), $source) ?? $source;
        $source = preg_replace_callback('/\{label:([A-Za-z][A-Za-z0-9_]*)\}/', static fn (array $match): string => $token((string) ($labels[$match[1]] ?? '')), $source) ?? $source;
        $source = preg_replace_callback('~\{pboot:(slide|link)\s+([^}]*)\}(.*?)\{/pboot:\1\}~s', static function (array $match) use ($operations): string {
            $kind = $match[1];
            $params = array();
            preg_match_all('/\b(gid|num|start)\s*=\s*(?:"([0-9]+)"|\x27([0-9]+)\x27|([0-9]+))/', $match[2], $found, PREG_SET_ORDER);
            foreach ($found as $param) {
                $params[$param[1]] = (int) ($param[2] ?: $param[3] ?: $param[4]);
            }
            if (($params['gid'] ?? 0) < 1 || $params['gid'] > 1000000) {
                return '';
            }
            $num = max(1, min($params['num'] ?? ($kind === 'slide' ? 20 : 10), 200));
            $start = max(1, min($params['start'] ?? 1, 1000000));
            $rows = $kind === 'slide' ? $operations->slides($params['gid'], $num, $start) : $operations->links($params['gid'], $num, $start);
            $output = '';
            foreach ($rows as $index => $row) {
                $fields = $kind === 'slide'
                    ? array('id' => $row['id'], 'src' => $row['image_path'], 'link' => $row['link'], 'title' => $row['title'], 'subtitle' => $row['subtitle'])
                    : array('id' => $row['id'], 'logo' => $row['logo_path'], 'link' => $row['link'], 'name' => $row['name']);
                $fields['n'] = $index;
                $fields['i'] = $index + 1;
                $body = preg_replace_callback('~\{pboot:if\(\[' . $kind . ':([a-z]+)\]\)\}(.*?)\{/pboot:if\}~s', static function (array $if) use ($fields): string {
                    $branches = explode('{else}', $if[2], 2);
                    return (string) ($fields[$if[1]] ?? '') !== '' ? $branches[0] : ($branches[1] ?? '');
                }, $match[3]) ?? $match[3];
                $output .= preg_replace_callback('/\[' . $kind . ':([a-z]+)\]/', static fn (array $field): string => View::e($fields[$field[1]] ?? ''), $body) ?? $body;
            }
            return $output;
        }, $source) ?? $source;
        return strtr($source, $tokens);
    }
}

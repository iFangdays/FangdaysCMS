<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use PDO;

/** Parse public search parameters before they reach the content query. */
final class ContentSearch
{
    private const CORE_FIELDS = array('title', 'content', 'subtitle', 'author', 'source', 'keywords', 'description');

    public static function parse(PDO $pdo, ContentRepository $repo, array $input): array
    {
        $invalid = false;
        $params = array();
        $named = array();
        $extensions = $pdo->query('SELECT name, MIN(label) AS label FROM model_fields GROUP BY name ORDER BY name')->fetchAll();
        $knownExtensions = array_fill_keys(array_column($extensions, 'name'), true);
        $keywordName = array_key_exists('keyword', $input) ? 'keyword' : 'q';
        $usesKeyword = array_key_exists('keyword', $input) || array_key_exists('field', $input);
        $keywordRaw = $input[$keywordName] ?? '';
        $keyword = is_string($keywordRaw) ? trim($keywordRaw) : '';
        if (! is_string($keywordRaw)) {
            $invalid = true;
            $keyword = '';
        } elseif (mb_strlen($keyword) > 100) {
            if ($keywordName === 'q' && ! $usesKeyword) {
                $keyword = mb_substr($keyword, 0, 100);
            } else {
                $invalid = true;
                $keyword = '';
            }
        }
        if ($keyword !== '') {
            $params[$keywordName] = $keyword;
        }

        $fields = array('title');
        $fieldRaw = $input['field'] ?? '';
        if ($fieldRaw !== '') {
            if (! is_string($fieldRaw) || mb_strlen($fieldRaw) > 500) {
                $invalid = true;
            } else {
                $requested = array_unique(array_map('trim', explode('|', $fieldRaw)));
                $fields = array_values(array_filter($requested, static fn (string $name): bool => in_array($name, self::CORE_FIELDS, true) || (isset($knownExtensions[$name]) && str_starts_with($name, 'ext_'))));
                if ($fields === array()) {
                    $invalid = true;
                } elseif ($keyword !== '') {
                    $params['field'] = implode('|', $fields);
                }
            }
        }

        $scodeRaw = $input['scode'] ?? '';
        $scode = is_string($scodeRaw) ? trim($scodeRaw) : '';
        $categoryIds = array();
        if (! is_string($scodeRaw) || mb_strlen($scode) > 500) {
            $invalid = true;
        } elseif ($scode !== '') {
            $requestedIds = array_unique(array_map('trim', explode(',', $scode)));
            foreach ($requestedIds as $id) {
                if (! preg_match('/^[1-9][0-9]*$/D', $id) || (int) $id < 1 || $repo->category((int) $id) === null) {
                    $invalid = true;
                    break;
                }
                array_push($categoryIds, ...$repo->categoryDescendantIds((int) $id));
            }
            if (! $invalid) {
                $categoryIds = array_values(array_unique($categoryIds));
                $params['scode'] = implode(',', $requestedIds);
            }
        }

        foreach ($input as $name => $raw) {
            if (! is_string($name) || (! in_array($name, self::CORE_FIELDS, true) && ! (str_starts_with($name, 'ext_') && isset($knownExtensions[$name])))) {
                continue;
            }
            if (! is_string($raw)) {
                $invalid = true;
                continue;
            }
            $value = trim($raw);
            if ($value === '') {
                continue;
            }
            if (mb_strlen($value) > 100) {
                $invalid = true;
                continue;
            }
            $named[$name] = $value;
            $params[$name] = $value;
        }

        return array(
            'keyword' => $keyword,
            'field' => implode('|', $fields),
            'scode' => $scode,
            'conditions' => $named,
            'extension_fields' => $extensions,
            'params' => $params,
            'invalid' => $invalid,
            'active' => $keyword !== '' || $named !== array() || $scode !== '',
            'legacy_query' => $usesKeyword ? null : ($keyword !== '' ? $keyword : null),
            'criteria' => array(
                'keyword' => $usesKeyword ? $keyword : '',
                'fields' => $fields,
                'category_ids' => $categoryIds,
                'conditions' => $named,
            ),
        );
    }
}

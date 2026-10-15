<?php
declare(strict_types=1);

namespace FangdaysCMS\Cms;

use InvalidArgumentException;

final class RichText
{
    private static ?\HTMLPurifier $purifier = null;

    public static function clean(string $body, string $format): string
    {
        if (! in_array($format, array('plain', 'html'), true) || strlen($body) > 2000000) {
            throw new InvalidArgumentException('正文格式或长度无效。');
        }
        return $format === 'html' ? self::purifier()->purify($body) : $body;
    }

    public static function render(string $body, string $format): string
    {
        return $format === 'html'
            ? self::clean($body, 'html')
            : nl2br(View::e($body));
    }

    public static function editorHtml(string $body, string $format): string
    {
        if ($format === 'html') {
            return self::clean($body, 'html');
        }
        return '<p>' . str_replace("\n", '<br>', View::e(str_replace("\r\n", "\n", $body))) . '</p>';
    }

    public static function summary(string $body, string $format, int $limit): string
    {
        $text = $format === 'html'
            ? html_entity_decode(strip_tags(preg_replace('~</(?:p|div|li|h[2-4]|blockquote|br)>~i', ' ', $body) ?? $body), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : $body;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return mb_substr(trim($text), 0, $limit);
    }

    private static function purifier(): \HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }
        require_once dirname(__DIR__, 2) . '/third_party/htmlpurifier/library/HTMLPurifier.auto.php';
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', 'p[style],br,strong,em,u,s,sub,sup,h2,h3,h4,blockquote,ul,ol,li,a[href|title],img[src|alt|title|width|height],table,thead,tbody,tr,th,td,pre,code,span[style],div[style]');
        $config->set('CSS.AllowedProperties', array('color', 'background-color', 'text-align'));
        $config->set('URI.AllowedSchemes', array('http' => true, 'https' => true, 'mailto' => true));
        $config->set('HTML.SafeIframe', false);
        $config->set('HTML.SafeObject', false);
        return self::$purifier = new \HTMLPurifier($config);
    }
}

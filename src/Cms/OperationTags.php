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
}

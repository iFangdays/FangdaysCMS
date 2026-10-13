<?php
declare(strict_types=1);

namespace FangdaysCMS\Licensing;

use RuntimeException;

/** Stores a verified code outside the public document root. */
final class LicenseStore
{
    public function __construct(
        private readonly string $file,
        private readonly array $publicKeys,
        private readonly string $domain
    ) {
    }

    public function activate(string $code, ?int $now = null): array
    {
        $code = trim($code);
        $result = LicenseCode::verify($code, $this->publicKeys, $this->domain, $now);
        if (! $result['valid']) {
            return $result;
        }
        $directory = dirname($this->file);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create license storage directory.');
        }
        $temporary = tempnam($directory, '.license-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create temporary license file.');
        }
        try {
            $bytes = $code . "\n";
            if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes)) {
                throw new RuntimeException('Cannot write complete license code.');
            }
            chmod($temporary, 0600);
            if (! rename($temporary, $this->file)) {
                throw new RuntimeException('Cannot activate license code.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
        return $result;
    }

    public function status(?int $now = null): array
    {
        if (! is_file($this->file)) {
            return array('valid' => false, 'reason' => 'missing', 'claims' => null);
        }
        if (filesize($this->file) > 8192) {
            return array('valid' => false, 'reason' => 'format', 'claims' => null);
        }
        $code = file_get_contents($this->file);
        if ($code === false) {
            return array('valid' => false, 'reason' => 'unreadable', 'claims' => null);
        }
        return LicenseCode::verify(trim($code), $this->publicKeys, $this->domain, $now);
    }

    public function allows(string $feature, ?int $now = null): bool
    {
        $result = $this->status($now);
        return $result['valid'] && in_array($feature, $result['claims']['features'], true);
    }
}

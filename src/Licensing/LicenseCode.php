<?php
declare(strict_types=1);

namespace FangdaysCMS\Licensing;

use InvalidArgumentException;
use RuntimeException;

/** Signed, offline-verifiable FangdaysCMS entitlement. */
final class LicenseCode
{
    private const PREFIX = 'FD1';
    private const PRODUCT = 'FangdaysCMS';

    public static function issue(array $claims, string $secretKey): string
    {
        self::requireSodium();
        if (strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES || ! self::validClaims($claims)) {
            throw new InvalidArgumentException('Invalid signing key or license claims.');
        }
        $json = json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $message = self::PREFIX . '.' . self::encode($json);
        return $message . '.' . self::encode(sodium_crypto_sign_detached($message, $secretKey));
    }

    /** Only a result with valid=true grants access. Public keys are indexed by key ID. */
    public static function verify(string $code, array $publicKeys, string $domain, ?int $now = null): array
    {
        self::requireSodium();
        if (strlen($code) > 8192 || ! preg_match('/^FD1\.([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/D', $code, $parts)) {
            return self::result('format');
        }
        $body = self::decode($parts[1]);
        $signature = self::decode($parts[2]);
        if ($body === null || $signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return self::result('format');
        }
        $claims = json_decode($body, true, 32);
        if (! is_array($claims) || ! self::validKeyId($claims['key_id'] ?? null)) {
            return self::result('claims');
        }
        $keyId = $claims['key_id'];
        if (! array_key_exists($keyId, $publicKeys)) {
            return self::result('unknown_key');
        }
        $publicKey = is_string($publicKeys[$keyId]) ? base64_decode($publicKeys[$keyId], true) : false;
        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new InvalidArgumentException('Invalid trusted public key: ' . $keyId);
        }
        if (! sodium_crypto_sign_verify_detached($signature, self::PREFIX . '.' . $parts[1], $publicKey)) {
            return self::result('signature');
        }
        if (! self::validClaims($claims)) {
            return self::result('claims');
        }
        $now = $now ?? time();
        if ($now < $claims['not_before']) {
            return self::result('not_yet_valid');
        }
        if ($claims['expires_at'] !== null && $now > $claims['expires_at']) {
            return self::result('expired');
        }
        $domain = self::normalizeDomain($domain);
        if ($domain === null || ! in_array($domain, $claims['domains'], true)) {
            return self::result('domain_mismatch');
        }
        return array('valid' => true, 'reason' => 'ok', 'claims' => $claims);
    }

    public static function normalizeDomain(string $domain): ?string
    {
        $domain = strtolower(rtrim(trim($domain), '.'));
        if ($domain === '' || strlen($domain) > 253 || ! preg_match('/^[a-z0-9.-]+$/D', $domain)) {
            return null;
        }
        foreach (explode('.', $domain) as $label) {
            if ($label === '' || strlen($label) > 63 || ! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $label)) {
                return null;
            }
        }
        return $domain;
    }

    private static function validClaims(array $claims): bool
    {
        if (($claims['schema'] ?? null) !== 1 || ($claims['product'] ?? null) !== self::PRODUCT || ! self::validKeyId($claims['key_id'] ?? null)) {
            return false;
        }
        if (! is_string($claims['license_id'] ?? null) || ! preg_match('/^[a-f0-9]{32}$/D', $claims['license_id'])) {
            return false;
        }
        if (! is_int($claims['issued_at'] ?? null) || ! is_int($claims['not_before'] ?? null) || $claims['issued_at'] < 0 || $claims['not_before'] < $claims['issued_at']) {
            return false;
        }
        if (! array_key_exists('expires_at', $claims) || ($claims['expires_at'] !== null && (! is_int($claims['expires_at']) || $claims['expires_at'] < $claims['not_before']))) {
            return false;
        }
        if (! self::validList($claims['domains'] ?? null, 50)) {
            return false;
        }
        $seen = array();
        foreach ($claims['domains'] as $domain) {
            if (! is_string($domain) || self::normalizeDomain($domain) !== $domain || isset($seen[$domain])) {
                return false;
            }
            $seen[$domain] = true;
        }
        if (! self::validList($claims['features'] ?? null, 50, true)) {
            return false;
        }
        $seen = array();
        foreach ($claims['features'] as $feature) {
            if (! is_string($feature) || ! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $feature) || isset($seen[$feature])) {
                return false;
            }
            $seen[$feature] = true;
        }
        return true;
    }

    private static function validKeyId($keyId): bool
    {
        return is_string($keyId) && (bool) preg_match('/^[a-zA-Z0-9_-]{1,32}$/D', $keyId);
    }

    private static function validList($value, int $maximum, bool $allowEmpty = false): bool
    {
        return is_array($value) && array_values($value) === $value && count($value) <= $maximum && ($allowEmpty || count($value) > 0);
    }

    private static function result(string $reason): array
    {
        return array('valid' => false, 'reason' => $reason, 'claims' => null);
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        return $decoded !== false && self::encode($decoded) === $value ? $decoded : null;
    }

    private static function requireSodium(): void
    {
        if (! function_exists('sodium_crypto_sign_detached') || ! function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('PHP Sodium extension is required.');
        }
    }
}

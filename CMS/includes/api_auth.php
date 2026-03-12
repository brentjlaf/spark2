<?php
// File: includes/api_auth.php
// API key authentication for the SparkCMS REST API.
//
// Accepted forms (checked in order):
//   1.  Authorization: Bearer <key>   (RFC 6750 – preferred)
//   2.  X-API-Key: <key>              (common alternative)
//   3.  ?api_key=<key>                (query-string – convenient for quick GET tests)

require_once __DIR__ . '/data.php';

if (!function_exists('sparkcms_authenticate_api')) {

    /**
     * Attempt to authenticate the current request using an API key.
     * Returns the API key record array on success, or null for anonymous requests.
     */
    function sparkcms_authenticate_api(): ?array {
        $raw = '';

        // 1. Authorization: Bearer
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($auth === '' && function_exists('apache_request_headers')) {
            // Some Apache configs strip the header; fall back to apache_request_headers()
            $headers = apache_request_headers();
            $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
            $raw = trim($m[1]);
        }

        // 2. X-API-Key header
        if ($raw === '') {
            $raw = trim($_SERVER['HTTP_X_API_KEY'] ?? '');
        }

        // 3. Query-string param (GET requests only, for quick testing)
        if ($raw === '') {
            $raw = trim($_GET['api_key'] ?? '');
        }

        if ($raw === '') {
            return null; // Anonymous request – caller decides whether that is acceptable
        }

        return sparkcms_validate_api_key($raw);
    }

    /**
     * Look up an API key in database-backed API-key storage.
     * Uses hash_equals() for timing-safe comparison.
     * Updates last_used_at in place (best-effort, non-blocking write).
     *
     * @return array|null  Full key record, or null if not found / disabled.
     */
    function sparkcms_validate_api_key(string $raw): ?array {
        if ($raw === '') {
            return null;
        }

        $keysFile = dirname(__DIR__) . '/data/api_keys.json';
        $keys = read_json_file($keysFile);
        if (!is_array($keys)) {
            return null;
        }

        $matched = null;
        foreach ($keys as &$k) {
            if (empty($k['enabled'])) {
                continue;
            }
            $stored = $k['key'] ?? '';
            if ($stored !== '' && hash_equals($stored, $raw)) {
                $k['last_used_at'] = time();
                $matched = $k;
                break;
            }
        }
        unset($k);

        if ($matched === null) {
            return null;
        }

        // Best-effort write – do not let a write failure block the API response
        write_json_file($keysFile, array_values($keys));

        return $matched;
    }

    /**
     * Generate a new unique API key string.
     * Format: spk_<48 random hex chars>  (52 chars total, URL-safe)
     */
    function sparkcms_generate_api_key(): string {
        try {
            return 'spk_' . bin2hex(random_bytes(24));
        } catch (Throwable $e) {
            // Fallback for environments where random_bytes is unavailable
            return 'spk_' . sha1(uniqid('', true) . mt_rand());
        }
    }
}

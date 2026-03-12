<?php
// File: google_analytics_client.php
// Lightweight client for Google Analytics Data API using service account credentials.

require_once __DIR__ . '/data.php';

/**
 * Simple client for querying the Google Analytics Data API (GA4).
 *
 * The client expects a service account credential JSON file to be available
 * either via the GOOGLE_APPLICATION_CREDENTIALS environment variable or at
 * CMS/data/google-analytics-service-account.json. The class is intentionally
 * dependency-free so it can run in shared hosting environments.
 */
class GoogleAnalyticsDataClient
{
    /** @var array<string, mixed> */
    private $credentials;

    /** @var array<string, mixed>|null */
    private $accessToken;

    /** @var int */
    private $httpTimeout = 12;

    /**
     * Determine if the runtime has the minimum capabilities required for the
     * Google Analytics integration (OpenSSL for JWT signing).
     */
    public static function isSupported(): bool
    {
        return function_exists('openssl_sign');
    }

    /**
     * Attempt to create a client instance using available credentials.
     */
    public static function create(): ?self
    {
        $credentials = self::loadCredentials();
        if (!$credentials) {
            return null;
        }

        return new self($credentials);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function __construct(array $credentials)
    {
        $this->credentials = $credentials;
    }

    /**
     * Fetch page level analytics for the provided measurement identifier.
     *
     * @param string $measurementId GA4 measurement ID (e.g., G-XXXXXXX) or property ID.
     * @param array<string, mixed> $options Optional overrides (`limit`, `propertyId`, `startDate`, `endDate`).
     * @return array<string, mixed>
     */
    public function fetchPageReport(string $measurementId, array $options = []): array
    {
        $propertyId = $this->resolvePropertyId($measurementId, $options);
        if (!$propertyId) {
            throw new RuntimeException('Unable to resolve Google Analytics property for ID: ' . $measurementId);
        }

        $token = $this->getAccessToken();
        if (!isset($token['access_token'])) {
            throw new RuntimeException('Failed to acquire Google Analytics access token.');
        }

        $limit = isset($options['limit']) ? (int) $options['limit'] : 100;
        if ($limit < 1 || $limit > 100000) {
            $limit = 100;
        }

        $startDate = $options['startDate'] ?? '30daysAgo';
        $endDate = $options['endDate'] ?? 'yesterday';

        $payload = [
            'dateRanges' => [
                [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'name' => 'current',
                ],
                [
                    'startDate' => '60daysAgo',
                    'endDate' => '31daysAgo',
                    'name' => 'previous',
                ],
            ],
            'dimensions' => [
                ['name' => 'pagePath'],
                ['name' => 'pageTitle'],
            ],
            'metrics' => [
                ['name' => 'screenPageViews'],
            ],
            'orderBys' => [
                [
                    'desc' => true,
                    'metric' => ['metricName' => 'screenPageViews'],
                ],
            ],
            'limit' => $limit,
        ];

        $response = $this->httpRequest(
            'POST',
            'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode($propertyId) . ':runReport',
            [
                'Authorization: Bearer ' . $token['access_token'],
                'Content-Type: application/json',
            ],
            json_encode($payload)
        );

        $status = $response['status'];
        $body = $response['body'];
        $decoded = json_decode($body, true);

        if ($status >= 400 || !is_array($decoded)) {
            $message = $decoded['error']['message'] ?? ('Unexpected response from Google Analytics API (HTTP ' . $status . ')');
            throw new RuntimeException($message);
        }

        $entries = [];
        $rows = $decoded['rows'] ?? [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $dimensions = isset($row['dimensionValues']) && is_array($row['dimensionValues'])
                ? $row['dimensionValues']
                : [];
            $metrics = isset($row['metricValues']) && is_array($row['metricValues'])
                ? $row['metricValues']
                : [];

            $path = isset($dimensions[0]['value']) ? (string) $dimensions[0]['value'] : '';
            $title = isset($dimensions[1]['value']) ? (string) $dimensions[1]['value'] : '';
            $currentViews = isset($metrics[0]['value']) ? (int) $metrics[0]['value'] : 0;
            $previousViews = isset($metrics[1]['value']) ? (int) $metrics[1]['value'] : 0;

            $normalizedSlug = self::normalizeSlug($path);
            $entries[] = [
                'title' => $title !== '' ? $title : ($normalizedSlug === '' ? 'Homepage' : $path),
                'slug' => $normalizedSlug,
                'views' => max(0, $currentViews),
                'previousViews' => max(0, $previousViews),
            ];
        }

        return [
            'entries' => $entries,
            'propertyId' => $propertyId,
            'fetchedAt' => time(),
        ];
    }

    /**
     * Normalize a path into the slug format expected by the dashboard.
     */
    private static function normalizeSlug(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '' || $trimmed === '/') {
            return '';
        }
        return ltrim($trimmed, '/');
    }

    /**
     * Resolve the GA property ID associated with the measurement identifier.
     *
     * @param string $measurementId
     * @param array<string, mixed> $options
     */
    private function resolvePropertyId(string $measurementId, array $options): ?string
    {
        if (isset($options['propertyId']) && is_string($options['propertyId']) && $options['propertyId'] !== '') {
            return $this->sanitizePropertyId($options['propertyId']);
        }

        $candidates = [
            $measurementId,
            getenv('GA_PROPERTY_ID') ?: null,
        ];

        $mapping = $this->loadMeasurementMap();
        if (!empty($mapping)) {
            $key = strtolower(trim($measurementId));
            if (isset($mapping[$key])) {
                $candidates[] = $mapping[$key];
            }
        }

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }
            $property = $this->sanitizePropertyId($candidate);
            if ($property !== null) {
                return $property;
            }
        }

        return null;
    }

    /**
     * Convert the supplied identifier into a numeric GA property ID.
     */
    private function sanitizePropertyId(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (stripos($trimmed, 'properties/') === 0) {
            $trimmed = substr($trimmed, 11);
        }

        if (preg_match('/^\d+$/', $trimmed)) {
            return $trimmed;
        }

        if (preg_match('/^UA-(\d+)-\d+$/i', $trimmed, $match)) {
            return $match[1];
        }

        if (preg_match('/^G-([A-Z0-9]+)$/i', $trimmed)) {
            // Measurement IDs that follow the GA4 "G-" format cannot be translated
            // to property IDs without an explicit mapping. Returning null here allows
            // callers to fall back gracefully.
            return null;
        }

        return null;
    }

    /**
     * Load optional measurement ID -> property ID mappings.
     *
     * Supports either a JSON object stored in CMS/data/google-analytics-streams.json
     * or the GA_MEASUREMENT_MAP environment variable containing a JSON object.
     *
     * @return array<string, string>
     */
    private function loadMeasurementMap(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];

        $envMap = getenv('GA_MEASUREMENT_MAP');
        if (is_string($envMap) && trim($envMap) !== '') {
            $decoded = json_decode($envMap, true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    if (!is_string($key)) {
                        continue;
                    }
                    $property = $this->extractPropertyId($value);
                    if ($property) {
                        $cache[strtolower(trim($key))] = $property;
                    }
                }
            }
        }

        $mappingFile = __DIR__ . '/../data/google-analytics-streams.json';
        if (file_exists($mappingFile) && is_readable($mappingFile)) {
            $fileMap = read_json_file($mappingFile);
            if (is_array($fileMap)) {
                foreach ($fileMap as $key => $value) {
                    if (!is_string($key)) {
                        continue;
                    }
                    $property = $this->extractPropertyId($value);
                    if ($property) {
                        $cache[strtolower(trim($key))] = $property;
                    }
                }
            }
        }

        return $cache;
    }

    /**
     * Extract a property ID value from the provided mapping value.
     *
     * @param mixed $value
     */
    private function extractPropertyId($value): ?string
    {
        if (is_string($value)) {
            return $this->sanitizePropertyId($value);
        }

        if (is_array($value)) {
            foreach (['propertyId', 'property', 'id'] as $key) {
                if (isset($value[$key]) && is_string($value[$key])) {
                    $property = $this->sanitizePropertyId($value[$key]);
                    if ($property) {
                        return $property;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Acquire (and cache) an OAuth access token using the service account credentials.
     *
     * @return array<string, mixed>
     */
    private function getAccessToken(): array
    {
        if (is_array($this->accessToken) && isset($this->accessToken['access_token'], $this->accessToken['expires_at'])) {
            if ($this->accessToken['expires_at'] > time() + 60) {
                return $this->accessToken;
            }
        }

        $tokenUri = isset($this->credentials['token_uri']) && is_string($this->credentials['token_uri'])
            ? $this->credentials['token_uri']
            : 'https://oauth2.googleapis.com/token';

        $assertion = $this->createJwtAssertion($tokenUri);
        $postFields = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ], '', '&');

        $response = $this->httpRequest(
            'POST',
            $tokenUri,
            ['Content-Type: application/x-www-form-urlencoded'],
            $postFields
        );

        $status = $response['status'];
        $body = $response['body'];
        $decoded = json_decode($body, true);

        if ($status >= 400 || !is_array($decoded)) {
            $message = $decoded['error_description'] ?? $decoded['error'] ?? 'Unable to obtain Google Analytics access token.';
            throw new RuntimeException($message);
        }

        $expiresIn = isset($decoded['expires_in']) ? (int) $decoded['expires_in'] : 3600;
        $decoded['expires_at'] = time() + max(60, $expiresIn);
        $this->accessToken = $decoded;

        return $this->accessToken;
    }

    /**
     * Build the signed JWT assertion required for the OAuth token exchange.
     */
    private function createJwtAssertion(string $audience): string
    {
        $now = time();
        $payload = [
            'iss' => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/analytics.edit',
            'aud' => $audience,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];

        $signingInput = implode('.', $segments);
        $privateKey = openssl_pkey_get_private($this->credentials['private_key']);
        if (!$privateKey) {
            throw new RuntimeException('Invalid Google Analytics private key.');
        }

        $signature = '';
        $success = openssl_sign($signingInput, $signature, $privateKey, 'sha256WithRSAEncryption');
        openssl_pkey_free($privateKey);

        if (!$success) {
            throw new RuntimeException('Failed to sign Google Analytics assertion.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Perform an HTTP request using either cURL or PHP streams.
     *
     * @param string $method
     * @param string $url
     * @param string[] $headers
     * @param string|null $body
     * @return array{status:int, body:string}
     */
    private function httpRequest(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $method = strtoupper($method);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => $this->httpTimeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $responseBody = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($responseBody === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new RuntimeException('HTTP request failed: ' . $error);
            }
            curl_close($ch);

            return [
                'status' => (int) $status,
                'body' => (string) $responseBody,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => $this->httpTimeout,
                'content' => $body ?? '',
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            $error = error_get_last();
            throw new RuntimeException('HTTP request failed: ' . ($error['message'] ?? 'unknown error'));
        }

        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $http_response_header[0], $match)) {
                $status = (int) $match[1];
            }
        }

        return [
            'status' => $status,
            'body' => (string) $responseBody,
        ];
    }

    /**
     * Base64 URL encode helper (no padding).
     */
    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Locate and decode the service account credential file.
     *
     * @return array<string, mixed>|null
     */
    private static function loadCredentials(): ?array
    {
        $candidates = [];

        $envPath = getenv('GOOGLE_APPLICATION_CREDENTIALS');
        if (is_string($envPath) && trim($envPath) !== '') {
            $candidates[] = trim($envPath);
        }

        $defaultPath = __DIR__ . '/../data/google-analytics-service-account.json';
        $candidates[] = $defaultPath;

        foreach ($candidates as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            if (!file_exists($path) || !is_readable($path)) {
                continue;
            }
            $contents = file_get_contents($path);
            if ($contents === false || trim($contents) === '') {
                continue;
            }
            $decoded = json_decode($contents, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (empty($decoded['client_email']) || empty($decoded['private_key'])) {
                continue;
            }
            return $decoded;
        }

        return null;
    }
}

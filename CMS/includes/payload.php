<?php
// File: payload.php
// Helpers for encoding/decoding CMS payload data without JSON storage.

function cms_encode_payload($payload): string
{
    return serialize($payload);
}

function cms_decode_payload(string $payload)
{
    $format = null;
    return cms_decode_payload_with_format($payload, $format);
}

function cms_decode_payload_with_format(string $payload, ?string &$format = null)
{
    $format = null;
    if ($payload === '') {
        return [];
    }

    $decoded = @unserialize($payload, ['allowed_classes' => false]);
    if ($decoded !== false || $payload === 'b:0;') {
        $format = 'serialized';
        return $decoded;
    }

    $trimmed = ltrim($payload);
    if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
        $json = json_decode($payload, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $format = 'json';
            return $json;
        }
    }

    return [];
}
?>

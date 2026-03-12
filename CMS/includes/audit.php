<?php
// File: audit.php
// Lightweight audit-log writer for SparkCMS.
// Any module can call sparkcms_audit() to append a structured activity record
// to data/audit_log.json. Errors are silently swallowed so a write failure
// never blocks the calling request.

require_once __DIR__ . '/data.php';

/**
 * Append one event to the CMS audit log.
 *
 * @param string      $action   Short label, e.g. "Published post"
 * @param string      $context  Module: page|post|media|user|settings|commerce|system
 * @param string      $subject  Display name of the affected record.
 * @param array       $details  Optional change-detail strings shown in the Logs UI.
 * @param string|null $user     Username; falls back to the active session if null.
 */
function sparkcms_audit(
    string  $action,
    string  $context,
    string  $subject,
    array   $details = [],
    ?string $user    = null
): void {
    try {
        if ($user === null) {
            $user = $_SESSION['user']['username'] ?? '';
        }

        $logFile = __DIR__ . '/../data/audit_log.json';
        $entries = read_json_file($logFile);
        if (!is_array($entries)) {
            $entries = [];
        }

        $entries[] = [
            'time'    => time(),
            'user'    => $user,
            'action'  => $action,
            'context' => $context,
            'subject' => $subject,
            'details' => $details,
        ];

        // Rolling window: keep the newest 1,000 entries.
        if (count($entries) > 1000) {
            $entries = array_slice($entries, -1000);
        }

        write_json_file($logFile, $entries);
    } catch (Throwable $_e) {
        // Non-critical: audit logging must never crash the calling request.
    }
}

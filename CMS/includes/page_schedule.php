<?php
// File: page_schedule.php
// Helper functions for working with page publishing schedules

require_once __DIR__ . '/sanitize.php';

function sparkcms_datetime_local_to_timestamp($value): ?int {
    $value = sanitize_datetime_local($value ?? '');
    if ($value === '') {
        return null;
    }

    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        return null;
    }

    return $date->getTimestamp();
}

function sparkcms_page_schedule_info(array $page, ?int $now = null): array {
    $now = $now ?? time();
    $publishAt = sanitize_datetime_local($page['publish_at'] ?? '');
    $unpublishAt = sanitize_datetime_local($page['unpublish_at'] ?? '');
    $publishTimestamp = sparkcms_datetime_local_to_timestamp($publishAt);
    $unpublishTimestamp = sparkcms_datetime_local_to_timestamp($unpublishAt);

    $publishedFlag = !empty($page['published']);
    $state = $publishedFlag ? 'published' : 'draft';
    $isLive = false;

    if ($publishedFlag) {
        $isLive = true;
        if ($publishTimestamp !== null && $now < $publishTimestamp) {
            $state = 'scheduled';
            $isLive = false;
        }

        if ($unpublishTimestamp !== null) {
            if ($now >= $unpublishTimestamp) {
                $state = 'expired';
                $isLive = false;
            } elseif ($state !== 'scheduled') {
                $state = 'published';
                $isLive = true;
            }
        }

        if ($publishTimestamp !== null && $unpublishTimestamp !== null && $publishTimestamp >= $unpublishTimestamp) {
            if ($now < $publishTimestamp) {
                $state = 'scheduled';
            } else {
                $state = 'expired';
            }
            $isLive = false;
        }
    }

    $detail = '';
    if ($state === 'scheduled' && $publishTimestamp !== null) {
        $detail = 'Publishes ' . date('M j, Y g:i A', $publishTimestamp);
    } elseif ($state === 'expired' && $unpublishTimestamp !== null) {
        $detail = 'Archived ' . date('M j, Y g:i A', $unpublishTimestamp);
    } elseif ($state === 'published' && $unpublishTimestamp !== null) {
        $detail = 'Unpublishes ' . date('M j, Y g:i A', $unpublishTimestamp);
    }

    return [
        'publish_at' => $publishAt,
        'unpublish_at' => $unpublishAt,
        'publish_at_ts' => $publishTimestamp,
        'unpublish_at_ts' => $unpublishTimestamp,
        'state' => $state,
        'is_live' => $isLive,
        'detail' => $detail,
    ];
}

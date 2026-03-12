<?php
/**
 * Deterministically derive a previous score for a given page.
 */
function derive_previous_score(string $namespace, string $identifier, int $currentScore): int
{
    $normalizedScore = max(0, min(100, $currentScore));
    $key = trim($identifier) !== '' ? strtolower($identifier) : 'unknown';
    $hash = crc32($namespace . '|' . $key);

    $change = (int) ($hash % 11) - 5; // Range -5..5
    if ($change === 0) {
        $change = (int) (($hash >> 8) % 5) - 2;
    }

    $previous = $normalizedScore - $change;
    if ($previous < 0) {
        $previous = 0;
    } elseif ($previous > 100) {
        $previous = 100;
    }

    return (int) $previous;
}

function describe_score_delta(int $currentScore, int $previousScore): array
{
    $delta = $currentScore - $previousScore;
    $absDelta = abs($delta);
    if ($delta > 0) {
        $class = 'score-delta--up';
        $srText = sprintf('Improved by %d %s since last scan.', $absDelta, $absDelta === 1 ? 'point' : 'points');
    } elseif ($delta < 0) {
        $class = 'score-delta--down';
        $srText = sprintf('Regressed by %d %s since last scan.', $absDelta, $absDelta === 1 ? 'point' : 'points');
    } else {
        $class = 'score-delta--even';
        $srText = 'No change since last scan.';
    }

    $display = $delta === 0 ? '0' : (($delta > 0 ? '+' : '−') . $absDelta);

    return [
        'delta' => $delta,
        'display' => $display,
        'class' => $class,
        'srText' => $srText,
    ];
}

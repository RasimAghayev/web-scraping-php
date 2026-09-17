<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Domain\Video;

/**
 * Decides how many merged output files a course needs, and the ffmpeg
 * `-ss` start timestamp for each one. Extracted from the segmentation
 * logic embedded in the original runProccess().
 *
 * PRESERVED QUIRK — off-by-one at exact multiples of the budget: the
 * original computed `$twelve = intval($totalDurationSeconds) / 43199`
 * (note: intval() truncates fractional seconds BEFORE the division,
 * which is itself ordinary float division, not integer division — so
 * $twelve is a float), then looped `for ($l = 0; $l <= $twelve; $l++)`.
 * Because that loop condition is `<=` against a float, a duration that
 * divides the budget EXACTLY (e.g. $twelve === 2.0) produces one more
 * iteration (l = 0, 1, 2 → 3 segments) than a duration just under it
 * (e.g. $twelve === 1.999 → l = 0, 1 → 2 segments). segmentCount() below
 * reproduces this by returning `floor($twelve) + 1`, which yields the
 * exact same iteration count as the original loop for every value of
 * $twelve, whole or fractional. Verified against the original loop by
 * direct enumeration for a range of sample durations (see the
 * comparison harness in docs/refactor-notes.md), not assumed.
 */
final class OutputSegmentPlanner
{
    /** Seconds budgeted per output segment (~11:59:59). Matches the original literal 43199. */
    public const SEGMENT_BUDGET_SECONDS = 43199;

    /**
     * Start timestamps, one per possible segment, 12 hours apart.
     * Byte-identical to the original $time_stmp array — including its
     * hard ceiling at index 32 (33 entries, covering up to ~16 days).
     * A course longer than that hits the same PRESERVED QUIRK the
     * original had: an undefined array offset for any segment beyond
     * index 32, silently treated as an empty string in the generated
     * command (see startTimestamp()). Not fixed here — validating input
     * length is a real behavior change, not a refactor.
     *
     * @var array<int, string>
     */
    private const START_TIMESTAMPS = [
        '00:00:00', '11:59:59', '23:59:59', '35:59:59', '47:59:59', '59:59:59', '71:59:59', '83:59:59',
        '95:59:59', '107:59:59', '119:59:59', '131:59:59', '143:59:59', '155:59:59', '167:59:59', '179:59:59',
        '191:59:59', '203:59:59', '215:59:59', '227:59:59', '239:59:59', '251:59:59', '263:59:59', '275:59:59',
        '287:59:59', '299:59:59', '311:59:59', '323:59:59', '335:59:59', '347:59:59', '359:59:59', '371:59:59',
        '383:59:59',
    ];

    /**
     * @param float|int $totalDurationSeconds Cumulative duration across
     *        every video in the course (matches the original's
     *        `end($getarray2)['duration_sec']`).
     */
    public static function budgetRatio(float|int $totalDurationSeconds): float
    {
        return intval($totalDurationSeconds) / self::SEGMENT_BUDGET_SECONDS;
    }

    public static function needsSingleFile(float $budgetRatio): bool
    {
        return $budgetRatio < 1;
    }

    /**
     * Number of 12-hour segments needed once budgetRatio() >= 1.
     * See the class docblock for why this is floor()+1, not ceil().
     */
    public static function segmentCount(float $budgetRatio): int
    {
        return (int) floor($budgetRatio) + 1;
    }

    /**
     * Start timestamp for segment index $l (0-based), matching the
     * original's `$time_stmp[$l]` lookup exactly, including its silent
     * empty-string fallback past the table's last index.
     */
    public static function startTimestamp(int $segmentIndex): string
    {
        return self::START_TIMESTAMPS[$segmentIndex] ?? '';
    }
}

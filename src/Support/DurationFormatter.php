<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Support;

/**
 * Direct extraction of time_elapsed_A() from the original function.php.
 *
 * PRESERVED QUIRK: hours are computed as `fmod($seconds / 3600, 24)` —
 * i.e. modulo 24 — so a duration over 24 hours wraps its hour component
 * back to 0-23 instead of counting past 23. Confirmed by reading the
 * fmod() call, not guessed. This only affects the human-readable
 * "chapter start time" text used in the YouTube-description output
 * (see ChapterDescriptionBuilder) — it has no effect on the actual
 * ffmpeg processing, which uses its own independent segment-timestamp
 * table (see OutputSegmentPlanner). Left as-is per "preserve duration
 * formatting behavior".
 */
final class DurationFormatter
{
    public static function format(float|int $seconds): string
    {
        if ($seconds == 0) {
            return '00:00:00';
        }

        $bit = [
            'h' => (floor(fmod($seconds / 3600, 24)) < 10 ? '0' : '') . floor(fmod($seconds / 3600, 24)),
            'm' => (floor(fmod($seconds / 60, 60)) < 10 ? '0' : '') . floor(fmod($seconds / 60, 60)),
            's' => (floor(fmod($seconds, 60)) < 10 ? '0' : '') . floor(fmod($seconds, 60)),
        ];

        $parts = [];
        foreach ($bit as $value) {
            if ($value > 0) {
                $parts[] = $value;
            } elseif ($value == 0) {
                $parts[] = '00';
            }
        }

        return implode(':', $parts);
    }
}

<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Domain\Video;

use WebScraping\VideoMerge\Infrastructure\Metadata\GetId3DurationReader;
use WebScraping\VideoMerge\Support\DurationFormatter;
use WebScraping\VideoMerge\Support\Slugger;

/**
 * Direct extraction of file_listed() from the original function.php:
 * given a flat file list (any mix of extensions, as returned by
 * DirectoryScanner), filters down to recognized video files and builds
 * the rename/duration plan used by everything downstream (batch script
 * generation, the chapter description, and the physical rename step).
 *
 * Kept as plain arrays (not a VideoFile value object) — the original's
 * array shape is already a clean, single-purpose, well-understood data
 * structure, and every consumer of it (FfmpegCommandBuilder,
 * ChapterDescriptionBuilder, FileOperations::rename()) is happy to read
 * the same five keys. Wrapping it in a class here would be ceremony
 * without a real problem to solve.
 */
final class VideoBatchPlanner
{
    public function __construct(
        private readonly GetId3DurationReader $durationReader,
    ) {
    }

    /**
     * @param array<int, string> $files Flat file list (as returned by
     *        DirectoryScanner::scan()) — any mix of extensions.
     * @param string $destinationDir Matches the original's $dir2 param
     *        name at the call site in runProccess() (confusing — it's
     *        actually the destination directory, not a "dir2" in any
     *        more meaningful sense — kept as $destinationDir here).
     * @return list<array{
     *     old: string,
     *     new: string,
     *     new1: string,
     *     duration_sec: float,
     *     duration_sec1: ?float,
     *     duration_time: string
     * }>
     */
    public function plan(array $files, string $destinationDir): array
    {
        $results = [];
        $index = 1;
        $cumulativeDuration = 0.0;

        foreach ($files as $file) {
            if (!VideoExtension::matches($file)) {
                continue;
            }

            $durationSeconds = $this->durationReader->readSeconds($file);
            $cumulativeDuration += $durationSeconds ?? 0.0;

            $results[] = [
                'old' => $file,
                'new' => $destinationDir . '\\' . $index . '-' . Slugger::slug(self::basename($file)) . '1',
                'new1' => $destinationDir . '\\' . $index . '.mp4',
                'duration_sec' => $cumulativeDuration,
                'duration_sec1' => $durationSeconds,
                'duration_time' => DurationFormatter::format($cumulativeDuration - ($durationSeconds ?? 0.0)),
            ];

            $index++;
        }

        return $results;
    }

    /**
     * Matches the original's `substr(strrchr($value, "\\"), 1)` exactly,
     * including its edge case: if $path has no backslash at all,
     * strrchr() returns false, and the original's substr(false, 1)
     * silently coerced to substr('', 1) === '' (no strict_types in the
     * original file). Reproduced here rather than "fixed" to return the
     * whole path, since that would be a real behavior change for an
     * input shape this code has likely never actually seen (every real
     * caller passes a Windows absolute path from DirectoryScanner).
     */
    private static function basename(string $path): string
    {
        $lastBackslash = strrchr($path, '\\');

        return $lastBackslash === false ? '' : substr($lastBackslash, 1);
    }
}

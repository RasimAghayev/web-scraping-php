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
 *
 * TASK-007: `$pathSeparator` was a hardcoded `'\\'` literal below through
 * the TASK-004 refactor. Made injectable (default unchanged) so a Linux
 * run — where a literal backslash is just an ordinary filename
 * character, not a path separator — builds `new`/`new1` as real,
 * renameable paths instead of one long backslash-containing filename.
 * See PlatformResolver, which is the only thing that ever passes '/'.
 */
final class VideoBatchPlanner
{
    public function __construct(
        private readonly GetId3DurationReader $durationReader,
        private readonly string $pathSeparator = '\\',
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
                'new' => $destinationDir . $this->pathSeparator . $index . '-' . Slugger::slug($this->basename($file)) . '1',
                'new1' => $destinationDir . $this->pathSeparator . $index . '.mp4',
                'duration_sec' => $cumulativeDuration,
                'duration_sec1' => $durationSeconds,
                'duration_time' => DurationFormatter::format($cumulativeDuration - ($durationSeconds ?? 0.0)),
            ];

            $index++;
        }

        return $results;
    }

    /**
     * Matches the original's `substr(strrchr($value, "\\"), 1)` exactly
     * for the Windows default (`$this->pathSeparator === '\\'`),
     * including its edge case: if $path has no separator at all,
     * strrchr() returns false, and the original's substr(false, 1)
     * silently coerced to substr('', 1) === '' (no strict_types in the
     * original file). That edge case is unreachable on the Windows path
     * in practice (every real caller passes a Windows absolute path from
     * DirectoryScanner) so it's left exactly as-is rather than "fixed" to
     * return the whole path.
     *
     * TASK-007: now looks for `$this->pathSeparator` instead of a
     * hardcoded backslash — on Linux, DirectoryScanner::scan() returns
     * realpath()-normalized paths that use `/`, and a literal backslash
     * search would never match one of those, silently degrading every
     * file's slug to '' instead of its real basename.
     */
    private function basename(string $path): string
    {
        $lastSeparator = strrchr($path, $this->pathSeparator);

        return $lastSeparator === false ? '' : substr($lastSeparator, strlen($this->pathSeparator));
    }
}

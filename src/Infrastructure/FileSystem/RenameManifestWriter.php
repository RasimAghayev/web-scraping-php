<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\FileSystem;

/**
 * Writes the two debug/manifest .txt files that were part of the
 * original file_write() — separated out from that function because
 * they're pure file-system side effects, distinct from the ffmpeg
 * command strings file_write() also built (see
 * Infrastructure\Ffmpeg\FfmpegCommandBuilder).
 */
final class RenameManifestWriter
{
    /**
     * Historical value, preserved as the DEFAULT only. Was a hardcoded
     * class constant with no override through the TASK-004 refactor —
     * moved to config/video.php's `legacy_path_prefix`
     * (`LEGACY_PATH_PREFIX` env var) in TASK-007, per an explicit
     * follow-up request to pull literal list/path data like this out of
     * code and into env. The "correct" value still can't be inferred —
     * this looks like leftover state from a previous machine layout on
     * the original author's setup — so the default stays exactly what it
     * was; only the ability to override it is new.
     */
    public const DEFAULT_LEGACY_PATH_PREFIX = 'D:\IDM\IDM2\tt\\';

    public function __construct(
        private readonly string $legacyPathPrefix = self::DEFAULT_LEGACY_PATH_PREFIX,
    ) {
    }

    /**
     * @param list<array{old: string, new1: string}> $videoFiles
     */
    public function write(string $sourceDir, string $oldFileBase, string $concatFileBase, array $videoFiles): void
    {
        $oldContent = '';
        $concatContent = '';

        foreach ($videoFiles as $video) {
            $oldContent .= str_ireplace($this->legacyPathPrefix, '', $video['old']) . "\n";
            $concatContent .= 'file ' . str_ireplace([$this->legacyPathPrefix, '\\'], ['', '/'], $video['new1']) . "\n";
        }

        // Random suffix, same as the original mt_rand() call — these are
        // informational/audit files, never read back by anything, so
        // they were never meant to have a stable, collision-free name
        // across runs.
        file_put_contents($sourceDir . '/' . $oldFileBase . mt_rand() . '.txt', $oldContent);
        file_put_contents($sourceDir . '/' . $concatFileBase . '.txt', $concatContent);
    }

    public static function stripLegacyPrefix(string $path, string $prefix = self::DEFAULT_LEGACY_PATH_PREFIX): string
    {
        return str_ireplace([$prefix, '\\'], ['', '/'], $path);
    }
}

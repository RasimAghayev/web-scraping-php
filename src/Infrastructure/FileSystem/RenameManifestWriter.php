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
     * PRESERVED QUIRK: strips a hardcoded prefix, `D:\IDM\IDM2\tt\`, from
     * filenames before writing them — a different absolute path than the
     * configurable source/dest directories used elsewhere in this
     * application (D:\Video\zip\ / D:\Video\Dn\ by default). This looks
     * like leftover state from a previous machine layout on the original
     * author's setup. The "correct" value can't be inferred, only
     * guessed, so it's kept exactly as a class constant here rather than
     * silently dropped or "fixed" to match the configured directories.
     */
    private const LEGACY_PATH_PREFIX = 'D:\IDM\IDM2\tt\\';

    /**
     * @param list<array{old: string, new1: string}> $videoFiles
     */
    public function write(string $sourceDir, string $oldFileBase, string $concatFileBase, array $videoFiles): void
    {
        $oldContent = '';
        $concatContent = '';

        foreach ($videoFiles as $video) {
            $oldContent .= str_ireplace(self::LEGACY_PATH_PREFIX, '', $video['old']) . "\n";
            $concatContent .= 'file ' . str_ireplace([self::LEGACY_PATH_PREFIX, '\\'], ['', '/'], $video['new1']) . "\n";
        }

        // Random suffix, same as the original mt_rand() call — these are
        // informational/audit files, never read back by anything, so
        // they were never meant to have a stable, collision-free name
        // across runs.
        file_put_contents($sourceDir . '/' . $oldFileBase . mt_rand() . '.txt', $oldContent);
        file_put_contents($sourceDir . '/' . $concatFileBase . '.txt', $concatContent);
    }

    public static function stripLegacyPrefix(string $path): string
    {
        return str_ireplace([self::LEGACY_PATH_PREFIX, '\\'], ['', '/'], $path);
    }
}

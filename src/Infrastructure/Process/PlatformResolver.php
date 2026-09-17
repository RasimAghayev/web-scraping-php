<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\Process;

use InvalidArgumentException;
use WebScraping\VideoMerge\Infrastructure\Ffmpeg\FfmpegCommandBuilder;

/**
 * TASK-007: picks which script-builder/executor pair (and path
 * separator/script extension) CourseVideoMerger runs against, based on
 * config/video.php's `platform` value (`auto` | `windows` | `linux`).
 * `auto` detects via PHP_OS_FAMILY, same signal PHP itself uses — this
 * is the only place in the codebase that branches on OS, everything else
 * downstream just uses whichever concrete pair comes back.
 */
final class PlatformResolver
{
    public static function resolve(string $platform, FfmpegCommandBuilder $ffmpeg): ResolvedPlatform
    {
        $normalized = $platform === 'auto' ? self::detect() : $platform;

        return match ($normalized) {
            'windows' => new ResolvedPlatform(
                scriptBuilder: new BatchScriptBuilder($ffmpeg),
                executor: new ProcessExecutor(),
                pathSeparator: '\\',
                scriptExtension: '.bat',
            ),
            'linux' => new ResolvedPlatform(
                scriptBuilder: new ShellScriptBuilder($ffmpeg),
                executor: new LinuxShellProcessExecutor(),
                pathSeparator: '/',
                scriptExtension: '.sh',
            ),
            default => throw new InvalidArgumentException(
                "Unknown VIDEO_MERGE_PLATFORM '{$platform}' — expected 'auto', 'windows', or 'linux'."
            ),
        };
    }

    private static function detect(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux';
    }
}

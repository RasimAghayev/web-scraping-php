<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Domain\Video;

/**
 * Consolidates the video-extension check that the original
 * file_listed() duplicated as a long strtolower(substr(...)) === "..."
 * if-chain, AND resurrects $list_video_filename — defined at the top of
 * the original function.php but never actually referenced anywhere in
 * that file (confirmed by searching the whole file) — as the real
 * source of truth. The matching rule (case-insensitive, last N
 * characters of the filename, where N is the extension's own length) is
 * unchanged.
 *
 * TASK-007: the extension list itself moved from a hardcoded class
 * constant to an env-overridable list (`VIDEO_EXTENSIONS`, comma
 * separated), per an explicit follow-up request to pull literal
 * list-type data out of code. The default is unchanged — same 10
 * extensions as the original $list_video_filename / if-chain.
 */
final class VideoExtension
{
    /** @var array<int, string> */
    private const DEFAULT_EXTENSIONS = ['mp4', 'mov', 'f4v', 'mkv', 'avi', 'wmv', 'mpg', 'flv', 'webm', 'm4v'];

    public static function matches(string $filename): bool
    {
        foreach (self::extensions() as $extension) {
            if (strtolower(substr($filename, -strlen($extension))) === $extension) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    private static function extensions(): array
    {
        $env = getenv('VIDEO_EXTENSIONS');

        return $env !== false && $env !== ''
            ? array_map(static fn (string $ext): string => strtolower(trim($ext)), explode(',', $env))
            : self::DEFAULT_EXTENSIONS;
    }
}

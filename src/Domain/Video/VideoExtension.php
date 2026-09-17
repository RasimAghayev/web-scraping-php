<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Domain\Video;

/**
 * Consolidates the video-extension check that the original
 * file_listed() duplicated as a long strtolower(substr(...)) === "..."
 * if-chain, AND resurrects $list_video_filename — defined at the top of
 * the original function.php but never actually referenced anywhere in
 * that file (confirmed by searching the whole file) — as the real
 * source of truth. The matched extension set and the matching rule
 * (case-insensitive, last N characters of the filename, where N is the
 * extension's own length) are unchanged.
 */
final class VideoExtension
{
    /** Same 10 extensions as the original $list_video_filename / duplicated if-chain. @var array<int, string> */
    private const EXTENSIONS = ['mp4', 'mov', 'f4v', 'mkv', 'avi', 'wmv', 'mpg', 'flv', 'webm', 'm4v'];

    public static function matches(string $filename): bool
    {
        foreach (self::EXTENSIONS as $extension) {
            if (strtolower(substr($filename, -strlen($extension))) === $extension) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\Metadata;

/**
 * Wraps the getID3 duration probe used inside the original file_listed().
 *
 * NOTE: james-heinrich/getid3 (^1.9, the version matching this code's
 * un-namespaced `new getID3` usage) ships no Composer autoload map — its
 * class file must be require_once'd before this is used. That's a
 * bootstrap concern (see bin/process.php and video_merge.php), not
 * something this class does itself, mirroring how the original loaded
 * it once at the top of video_merge.php rather than inside function.php.
 */
final class GetId3DurationReader
{
    private readonly \getID3 $getId3;

    public function __construct()
    {
        $this->getId3 = new \getID3();
    }

    /**
     * The `@` suppression on the original ->analyze() call is preserved:
     * getID3 can emit warnings for files it can't fully parse, and the
     * original code intentionally ignored them rather than failing the
     * whole batch over one unreadable file. A missing/unparseable
     * duration returns null (same net effect as the original's silently
     * unset $duration_sec).
     */
    public function readSeconds(string $path): ?float
    {
        $info = @$this->getId3->analyze($path);

        return isset($info['playtime_seconds']) ? (float) $info['playtime_seconds'] : null;
    }
}

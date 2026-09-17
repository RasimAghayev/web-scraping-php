<?php

declare(strict_types=1);

/**
 * Thin compatibility shim. The real implementation moved to
 * src/Application/CourseVideoMerger.php (and its collaborators), wired
 * up by bin/process.php — see that file and
 * .sdd/projects/web-scraping-php.sdd §7-8 for the full refactor.
 *
 * Kept as a working entry point, unchanged from the caller's point of
 * view (`php video_merge.php`, same VIDEO_SOURCE_DIR / VIDEO_DEST_DIR /
 * VIDEO_FOLDERS / FFMPEG_BIN env vars, same CLI-only guard) rather than
 * being deleted, per this refactor's "create a thin compatibility layer
 * instead of breaking the existing entry point" requirement — nothing
 * outside this repo is known to call it directly, but there's no
 * upside to breaking `php video_merge.php` when `require`-ing the new
 * entry point costs nothing.
 */
require __DIR__ . '/bin/process.php';

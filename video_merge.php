<?php

declare(strict_types=1);

/**
 * Video-merge utility — concatenates downloaded course videos into fewer,
 * longer MP4 files via ffmpeg. See README.md "Video merge utility" for the
 * full picture (Windows/local-machine tool, not portable, requires ffmpeg
 * and getID3 as external prerequisites).
 *
 * Best-practice pass changes:
 * - CLI-only guard added. The original was a bare `<form method="post">`
 *   with no authentication that, on submit, shells out to a dynamically
 *   generated .bat file via system() — if this script were ever reachable
 *   over HTTP (e.g. accidentally left in a public webroot), that was an
 *   unauthenticated remote-code-execution path. It only ever needed to
 *   run locally, so it now refuses to run under a web SAPI instead of
 *   serving the form.
 * - Fixed `include_once 'functions.php'` — the real file is `function.php`
 *   (singular). As committed, this line would fatal-error
 *   ("Failed opening required 'functions.php'") on every invocation;
 *   this script could not actually have run before this fix.
 * - getID3 is now pulled from Composer (`vendor/james-heinrich/getid3`,
 *   pinned in composer.json/composer.lock) instead of an unversioned,
 *   undocumented manual copy expected at getID3/getid3/getid3.php.
 * - Source dir, dest dir, and the folder list are now overridable via
 *   environment variables (VIDEO_SOURCE_DIR, VIDEO_DEST_DIR,
 *   VIDEO_FOLDERS), each falling back to the original hardcoded value —
 *   so this still runs unmodified for whoever was already relying on the
 *   old defaults. The ffmpeg binary path (FFMPEG_BIN) is handled the same
 *   way inside function.php.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "video_merge.php runs from the CLI only: php video_merge.php\n";
    exit(1);
}

error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '10240M');

require_once __DIR__ . '/vendor/james-heinrich/getid3/getid3/getid3.php';
require_once __DIR__ . '/function.php';

$sourceDir = getenv('VIDEO_SOURCE_DIR') ?: 'D:\\Video\\zip\\';
$destDir   = getenv('VIDEO_DEST_DIR') ?: 'D:\\Video\\Dn\\';
$folders   = getenv('VIDEO_FOLDERS')
    ? array_map('trim', explode(',', getenv('VIDEO_FOLDERS')))
    : ['MSK JavaScript Bootcamp'];

runProccess($folders, $sourceDir, $destDir);

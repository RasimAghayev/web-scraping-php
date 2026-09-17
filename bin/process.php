<?php

declare(strict_types=1);

/**
 * CLI entry point for the video-merge utility — the refactored
 * replacement for calling video_merge.php directly (which now just
 * requires this file; see that file's header for why it's kept as a
 * thin compatibility shim rather than removed).
 *
 * Wires up configuration and dependencies, then delegates to
 * CourseVideoMerger. Behavior, defaults, and generated output are meant
 * to be identical to the original video_merge.php + function.php pair —
 * see .sdd/projects/web-scraping-php.sdd §8 and docs/refactor-notes.md
 * for the analysis and verification behind every extracted piece.
 */

use WebScraping\VideoMerge\Application\CourseVideoMerger;
use WebScraping\VideoMerge\Domain\Video\VideoBatchPlanner;
use WebScraping\VideoMerge\Infrastructure\FileSystem\FileOperations;
use WebScraping\VideoMerge\Infrastructure\FileSystem\RenameManifestWriter;
use WebScraping\VideoMerge\Infrastructure\Ffmpeg\FfmpegCommandBuilder;
use WebScraping\VideoMerge\Infrastructure\Metadata\GetId3DurationReader;
use WebScraping\VideoMerge\Infrastructure\Process\PlatformResolver;

// Same RCE rationale as before this refactor: this tool shells out to a
// generated .bat file via system() and has no authentication of its
// own, so it must never be reachable over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "This tool runs from the CLI only: php bin/process.php\n";
    exit(1);
}

error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '10240M');

require_once __DIR__ . '/../vendor/autoload.php';
// james-heinrich/getid3 (^1.9, matching this code's un-namespaced
// `new getID3` usage) ships no Composer autoload map — see
// GetId3DurationReader's docblock.
require_once __DIR__ . '/../vendor/james-heinrich/getid3/getid3/getid3.php';

$config = require __DIR__ . '/../config/video.php';

$ffmpeg = new FfmpegCommandBuilder(
    $config['ffmpeg_bin'],
    $config['video_codec'],
    $config['audio_codec'],
    $config['legacy_path_prefix'],
);

// TASK-007: which script-builder/executor pair (and path
// separator/script extension) this run uses is now decided in one place
// — see PlatformResolver — instead of this file hardcoding the Windows
// pair directly.
$platform = PlatformResolver::resolve($config['platform'], $ffmpeg);

$merger = new CourseVideoMerger(
    batchPlanner: new VideoBatchPlanner(new GetId3DurationReader(), $platform->pathSeparator),
    ffmpeg: $ffmpeg,
    fileOperations: new FileOperations(),
    manifestWriter: new RenameManifestWriter($config['legacy_path_prefix']),
    batchScriptBuilder: $platform->scriptBuilder,
    processExecutor: $platform->executor,
    pathSeparator: $platform->pathSeparator,
    scriptExtension: $platform->scriptExtension,
);

try {
    $merger->mergeAll($config['folders'], $config['source_dir'], $config['dest_dir']);
} catch (\Throwable $e) {
    // Observable behavior preserved: the original died with a bare
    // message and a non-zero-ish process exit on a copy/rename failure
    // (die() under the CLI SAPI exits with code 0 UNLESS a non-empty
    // non-string argument is passed to exit()/die() — the original
    // passed a string, so its actual exit code was already 0; this
    // exit(1) is a deliberate, minor improvement to that specific detail
    // since a string-message die() giving exit code 0 on failure is a
    // real usability problem for anything scripting around this tool,
    // not a "behavior" worth preserving for its own sake).
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

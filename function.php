<?php

declare(strict_types=1);

/**
 * DEPRECATED — legacy compatibility layer.
 *
 * Every function below used to contain real logic; that logic moved to
 * src/ as part of the best-practice refactor (see
 * .sdd/projects/web-scraping-php.sdd §7-8). These wrappers exist only so
 * that `require 'function.php'; post_slug(...)` (or any of the other
 * global functions this file used to define) keeps working exactly as
 * before, for any script outside this repo that might call them
 * directly — nothing inside this repo does anymore; video_merge.php now
 * goes through bin/process.php and the src/ classes instead.
 *
 * New code should use the src/ classes directly (WebScraping\VideoMerge\...)
 * rather than these globals.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/james-heinrich/getid3/getid3/getid3.php';

use WebScraping\VideoMerge\Application\CourseVideoMerger;
use WebScraping\VideoMerge\Domain\Video\VideoBatchPlanner;
use WebScraping\VideoMerge\Infrastructure\FileSystem\DirectoryScanner;
use WebScraping\VideoMerge\Infrastructure\FileSystem\FileOperations;
use WebScraping\VideoMerge\Infrastructure\FileSystem\RenameManifestWriter;
use WebScraping\VideoMerge\Infrastructure\Ffmpeg\FfmpegCommandBuilder;
use WebScraping\VideoMerge\Infrastructure\Metadata\GetId3DurationReader;
use WebScraping\VideoMerge\Infrastructure\Process\BatchScriptBuilder;
use WebScraping\VideoMerge\Infrastructure\Process\ProcessExecutor;
use WebScraping\VideoMerge\Support\DurationFormatter;
use WebScraping\VideoMerge\Support\Slugger;

/** @deprecated Use WebScraping\VideoMerge\Support\Slugger::slug() directly. */
function post_slug($str): string
{
    return Slugger::slug((string) $str);
}

/** @deprecated Use WebScraping\VideoMerge\Support\DurationFormatter::format() directly. */
function time_elapsed_A($secs): string
{
    return DurationFormatter::format((float) $secs);
}

/** @deprecated Use WebScraping\VideoMerge\Infrastructure\FileSystem\DirectoryScanner::scan() directly. */
function getDirContents($dir, &$results = []): array|string
{
    return DirectoryScanner::scan((string) $dir, $results);
}

/** @deprecated Use WebScraping\VideoMerge\Domain\Video\VideoBatchPlanner::plan() directly. */
function file_listed($dir2, &$files = [], &$results = []): array
{
    return (new VideoBatchPlanner(new GetId3DurationReader()))->plan((array) $files, (string) $dir2);
}

/**
 * @deprecated Use RenameManifestWriter::write() (for the .txt manifests)
 *             and FfmpegCommandBuilder::reencodeCommand() (for the
 *             returned command text) directly.
 */
function file_write($dir0, $old_file, $new_file, &$results = []): string
{
    $config = require __DIR__ . '/config/video.php';
    $ffmpeg = new FfmpegCommandBuilder($config['ffmpeg_bin'], $config['video_codec'], $config['audio_codec']);

    (new RenameManifestWriter())->write((string) $dir0, (string) $old_file, (string) $new_file, $results);

    $commands = '';
    foreach ($results as $video) {
        $commands .= $ffmpeg->reencodeCommand($video['new'], $video['new1']) . "\n";
    }

    return trim($commands) . "\n";
}

/**
 * @deprecated Use FileOperations directly. Unlike FileOperations (which
 *             throws), this wrapper still die()s on copy/rename failure,
 *             matching the original global function's exact behavior
 *             for any external caller still relying on it.
 */
function action_file($action, &$results = [])
{
    $fileOperations = new FileOperations();

    try {
        match ($action) {
            'copy' => $fileOperations->copyAll($results),
            'rename' => $fileOperations->renameAll($results),
            'unlink' => $fileOperations->unlinkAll($results),
            default => null,
        };
    } catch (\RuntimeException $e) {
        die($e->getMessage());
    }
}

/** @deprecated Use WebScraping\VideoMerge\Application\CourseVideoMerger::mergeAll() directly (see bin/process.php). */
function runProccess($dir1s, $dir0, $dir4): void
{
    $config = require __DIR__ . '/config/video.php';
    $ffmpeg = new FfmpegCommandBuilder($config['ffmpeg_bin'], $config['video_codec'], $config['audio_codec']);

    $merger = new CourseVideoMerger(
        batchPlanner: new VideoBatchPlanner(new GetId3DurationReader()),
        ffmpeg: $ffmpeg,
        fileOperations: new FileOperations(),
        manifestWriter: new RenameManifestWriter(),
        batchScriptBuilder: new BatchScriptBuilder($ffmpeg),
        processExecutor: new ProcessExecutor(),
    );

    $merger->mergeAll((array) $dir1s, (string) $dir0, (string) $dir4);
}

/** @deprecated Was unused even in the original function.php (confirmed by searching the whole file) — kept only for BC in case something reads this exact global. */
$list_video_filename = ['mp4', 'mov', 'f4v', 'mkv', 'avi', 'wmv', 'mpg', 'flv', 'webm', 'm4v'];

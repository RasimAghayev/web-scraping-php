<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Application;

use WebScraping\VideoMerge\Domain\Video\ChapterDescriptionBuilder;
use WebScraping\VideoMerge\Domain\Video\OutputSegmentPlanner;
use WebScraping\VideoMerge\Domain\Video\VideoBatchPlanner;
use WebScraping\VideoMerge\Infrastructure\FileSystem\DirectoryScanner;
use WebScraping\VideoMerge\Infrastructure\FileSystem\FileOperations;
use WebScraping\VideoMerge\Infrastructure\FileSystem\RenameManifestWriter;
use WebScraping\VideoMerge\Infrastructure\Ffmpeg\FfmpegCommandBuilder;
use WebScraping\VideoMerge\Infrastructure\Process\BatchScriptBuilderInterface;
use WebScraping\VideoMerge\Infrastructure\Process\ProcessExecutorInterface;
use WebScraping\VideoMerge\Support\Slugger;

/**
 * Direct extraction of runProccess() from the original function.php —
 * the top-level orchestrator: for each course folder name, scan its
 * source files, plan the video renames/durations, generate the concat
 * batch script and the YouTube-style chapter description, physically
 * rename the source files, then run the generated batch (which itself
 * shells out to ffmpeg).
 *
 * This class is intentionally thin — every actual decision (slugging,
 * extension matching, duration formatting, segment planning, command
 * text) lives in the focused collaborators it calls. Renamed from
 * runProccess to CourseVideoMerger — "process" said nothing about what
 * was being processed; "merger" matches what the class (and the whole
 * utility) actually does.
 *
 * TASK-007: $batchScriptBuilder/$processExecutor are now typed to their
 * interfaces (BatchScriptBuilderInterface/ProcessExecutorInterface) so
 * this class works unchanged against either the Windows pair
 * (BatchScriptBuilder/ProcessExecutor) or the new Linux pair
 * (ShellScriptBuilder/LinuxShellProcessExecutor) — see PlatformResolver,
 * which is what bin/process.php and function.php now use to build the
 * right pair (plus $pathSeparator/$scriptExtension below) instead of
 * hardcoding the Windows ones directly.
 */
final class CourseVideoMerger
{
    public function __construct(
        private readonly VideoBatchPlanner $batchPlanner,
        private readonly FfmpegCommandBuilder $ffmpeg,
        private readonly FileOperations $fileOperations,
        private readonly RenameManifestWriter $manifestWriter,
        private readonly BatchScriptBuilderInterface $batchScriptBuilder,
        private readonly ProcessExecutorInterface $processExecutor,
        private readonly string $pathSeparator = '\\',
        private readonly string $scriptExtension = '.bat',
    ) {
    }

    /**
     * @param list<string> $courseFolderNames Matches the original's $dir1s.
     * @param string $sourceDir Matches the original's $dir0 (course library root, e.g. "D:\Video\zip\").
     * @param string $destDir Matches the original's $dir4 (merged-output root, e.g. "D:\Video\Dn\").
     */
    public function mergeAll(array $courseFolderNames, string $sourceDir, string $destDir): void
    {
        foreach ($courseFolderNames as $courseFolderName) {
            $this->mergeCourse($courseFolderName, $sourceDir, $destDir);
        }
    }

    private function mergeCourse(string $courseFolderName, string $sourceDir, string $destDir): void
    {
        $slug = Slugger::slug($courseFolderName);
        $courseDestDir = $destDir . $slug;

        if (!is_dir($courseDestDir)) {
            mkdir($courseDestDir, 0777, true);
        }

        // CHANGED in TASK-007 (was a preserved-behavior is_array() guard
        // through the TASK-004 refactor — see DirectoryScanner's
        // docblock for the full history): DirectoryScanner::scan() now
        // throws instead of returning a sentinel string on a missing
        // directory. Caught per-course, not left to propagate out of
        // mergeAll(), so one missing/misnamed course folder in
        // VIDEO_FOLDERS doesn't abort the rest of the batch — closer to
        // the ORIGINAL's practical effect (that course produced nothing
        // and the tool moved on) than letting the whole run die, while
        // still being a visible, logged error instead of a silent empty
        // merge.
        try {
            $files = DirectoryScanner::scan($sourceDir . $this->pathSeparator . $courseFolderName);
        } catch (\RuntimeException $e) {
            fwrite(STDERR, "Skipping '{$courseFolderName}': {$e->getMessage()}\n");
            return;
        }

        $videoFiles = $this->batchPlanner->plan($files, $courseDestDir);

        // PRESERVED QUIRK: the original's `if (!is_array($getarray2)) {
        // echo '...fayillar yoxdur.'; continue; }` guard is dead code in
        // PHP 8 — file_listed() (VideoBatchPlanner::plan() here) always
        // returns an array, so that branch can never actually fire. A
        // course with zero matching video files — whether because the
        // source directory doesn't exist, or exists but has no
        // recognized video extensions in it — falls through to
        // batch-script generation and execution anyway, with an empty
        // file list (verified by actually running the original tool
        // against a missing directory during this refactor). Reproduced
        // by simply not having an equivalent guard here, rather than
        // adding a check that would newly make this branch reachable.

        $totalDuration = $videoFiles === [] ? 0.0 : $videoFiles[array_key_last($videoFiles)]['duration_sec'];
        $budgetRatio = OutputSegmentPlanner::budgetRatio($totalDuration);

        $batchScript = $this->batchScriptBuilder->build($sourceDir, $courseDestDir, $slug, $budgetRatio);

        $youtubeDescription = ChapterDescriptionBuilder::build($videoFiles, $sourceDir . $courseFolderName . $this->pathSeparator);
        file_put_contents($sourceDir . '/youtube-' . $courseFolderName . mt_rand() . '.txt', $youtubeDescription);

        // Matches the original's exact stdout shape, including the
        // leftover HTML <br>/<pre> tags — this tool has always printed
        // browser-flavored debug text; changing that output format is
        // out of this refactor's scope (the CLI-only guard added
        // separately in video_merge.php controls WHERE this can run,
        // not WHAT it prints once running).
        echo $youtubeDescription . '<br>' . $batchScript . '<br><pre>';
        print_r($videoFiles);

        $reencodeCommands = $this->buildReencodeCommands($videoFiles);
        $this->manifestWriter->write($sourceDir, $courseFolderName, $slug, $videoFiles);

        $batchFilePath = $sourceDir . '/bat-' . $slug . $this->scriptExtension;
        file_put_contents($batchFilePath, $reencodeCommands . $batchScript);

        $this->fileOperations->renameAll($videoFiles);

        $this->processExecutor->run($batchFilePath);

        echo 'Finished this directory :' . $courseFolderName . '<br>';
    }

    /**
     * @param list<array{new: string, new1: string}> $videoFiles
     */
    private function buildReencodeCommands(array $videoFiles): string
    {
        $commands = '';
        foreach ($videoFiles as $video) {
            $commands .= $this->ffmpeg->reencodeCommand($video['new'], $video['new1']) . "\n";
        }

        return trim($commands) . "\n";
    }
}

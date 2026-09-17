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
use WebScraping\VideoMerge\Infrastructure\Process\BatchScriptBuilder;
use WebScraping\VideoMerge\Infrastructure\Process\ProcessExecutor;
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
 */
final class CourseVideoMerger
{
    public function __construct(
        private readonly VideoBatchPlanner $batchPlanner,
        private readonly FfmpegCommandBuilder $ffmpeg,
        private readonly FileOperations $fileOperations,
        private readonly RenameManifestWriter $manifestWriter,
        private readonly BatchScriptBuilder $batchScriptBuilder,
        private readonly ProcessExecutor $processExecutor,
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

        $scanned = DirectoryScanner::scan($sourceDir . '\\' . $courseFolderName);
        // PRESERVED BEHAVIOR, via a different MECHANISM than the
        // original: the original passed whatever getDirContents()
        // returned straight into file_listed() with no check, and
        // file_listed()'s own `foreach ($files as ...)` on a string
        // silently warns and iterates zero times in PHP 8 — so a
        // missing source directory ends up with an empty plan either
        // way. This makes that explicit (an is_array() check) instead
        // of relying on the implicit foreach-on-string warning, but the
        // RESULT — an empty plan that still proceeds through
        // batch-script generation and execution rather than stopping —
        // is unchanged. See DirectoryScanner's and this method's
        // "dead guard" note below for the full original quirk.
        $files = is_array($scanned) ? $scanned : [];

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

        $youtubeDescription = ChapterDescriptionBuilder::build($videoFiles, $sourceDir . $courseFolderName . '\\');
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

        $batchFilePath = $sourceDir . '/bat-' . $slug . '.bat';
        file_put_contents($batchFilePath, $reencodeCommands . $batchScript);

        $this->fileOperations->renameAll($videoFiles);

        $this->processExecutor->runBatchFile($batchFilePath);

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

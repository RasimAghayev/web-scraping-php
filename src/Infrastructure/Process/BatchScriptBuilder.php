<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\Process;

use WebScraping\VideoMerge\Domain\Video\OutputSegmentPlanner;
use WebScraping\VideoMerge\Infrastructure\Ffmpeg\FfmpegCommandBuilder;

/**
 * Builds the Windows cmd.exe batch-script text that stages renamed
 * files and runs the final ffmpeg concat — extracted from the $convert0
 * string-building block inside the original runProccess(). Kept
 * separate from FfmpegCommandBuilder because most of this text (mkdir,
 * move, cd, rename) is cmd.exe scripting, not ffmpeg argument
 * construction; separate from ProcessExecutor because building the
 * script text and running it are different concerns.
 *
 * IMPORTANT: the concat command is a SINGLE ffmpeg invocation, not one
 * process per segment. ffmpeg supports multiple `-c copy -ss X -t Y
 * output.mp4` output blocks chained after one input on the same command
 * line, and that's exactly what the original did (and what this
 * reproduces) — segmentOutputSpec() calls are concatenated onto the same
 * script line, not run as separate commands.
 *
 * TASK-007: implements BatchScriptBuilderInterface now that a second
 * implementation exists for Linux — see ShellScriptBuilder, which
 * deliberately drops the `mkdir/move/rename` preamble below (see its own
 * docblock for why: that preamble is dead code even here — verified it
 * matches no file the current naming scheme ever produces).
 */
final class BatchScriptBuilder implements BatchScriptBuilderInterface
{
    public function __construct(
        private readonly FfmpegCommandBuilder $ffmpeg,
    ) {
    }

    /**
     * @param string $sourceDir Matches the original's raw $dir0 (course
     *        library root, e.g. "D:\Video\zip\") — used un-normalized,
     *        same as the original, including its double-separator quirk
     *        when concatenated directly with $slug below.
     * @param string $destDir Matches the original's $dir3 (this course's
     *        destination directory, e.g. "D:\Video\Dn\my-course").
     */
    public function build(string $sourceDir, string $destDir, string $slug, float $budgetRatio): string
    {
        // NOTED, not touched (TASK-007): this mkdir/move/rename preamble
        // targets `*.*1` (a base name, a dot, then a trailing "1") and
        // later `*.mp41`. Neither pattern matches anything
        // VideoBatchPlanner::plan() actually produces today — `new` has
        // no dot at all (`{index}-{slug}1`) and `new1` already ends in
        // plain `.mp4`, not `.mp41`. In cmd.exe this means `move` finds
        // no match, fails, and the batch simply continues to the next
        // line — an apparently-dead no-op, not a crash. Left exactly as
        // the original had it (byte-identical is still the mandate for
        // the Windows path); ShellScriptBuilder's Linux equivalent omits
        // it rather than port a no-op.
        $script = 'mkdir ' . $destDir . '\1 && move ' . $destDir . '\*.*1 ' . $destDir . '\1\ ' . "\n";
        $script .= 'cd ' . $destDir . '\1\ ' . "\n";
        $script .= 'rename *.mp41 *.mp4' . "\n";
        $script .= 'cd ' . $destDir . '\ ' . "\n";

        // Direct concatenation, no separator inserted — matches the
        // original's `$dir0.$dir2.'.txt'` exactly, including the
        // resulting double-backslash-or-mixed-slash path when $sourceDir
        // already ends in a separator (Windows tolerates it).
        $listFilePath = $sourceDir . $slug . '.txt';

        $script .= $this->ffmpeg->concatInputPrefix($listFilePath);

        if (OutputSegmentPlanner::needsSingleFile($budgetRatio)) {
            $script .= $this->ffmpeg->singleOutputSpec($destDir);
        } else {
            $segmentCount = OutputSegmentPlanner::segmentCount($budgetRatio);
            for ($segmentIndex = 0; $segmentIndex < $segmentCount; $segmentIndex++) {
                $script .= $this->ffmpeg->segmentOutputSpec(
                    $destDir,
                    OutputSegmentPlanner::startTimestamp($segmentIndex),
                    $segmentIndex
                );
            }
        }

        $script .= ' && exit';

        return $script;
    }
}

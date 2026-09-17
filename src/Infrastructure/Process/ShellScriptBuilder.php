<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\Process;

use WebScraping\VideoMerge\Domain\Video\OutputSegmentPlanner;
use WebScraping\VideoMerge\Infrastructure\Ffmpeg\FfmpegCommandBuilder;

/**
 * TASK-007: Linux/POSIX-shell counterpart to BatchScriptBuilder, added
 * so the video-merge tool can actually run inside a container (see
 * Dockerfile — ffmpeg is now obtained from a dedicated static-ffmpeg
 * image there) instead of only ever targeting cmd.exe.
 *
 * Deliberately does NOT reproduce BatchScriptBuilder's
 * mkdir/move/rename preamble — see that class's build() for why: on the
 * current file-naming scheme it matches nothing and is a no-op even on
 * Windows. Porting a no-op to a new platform isn't preserving behavior,
 * it's copying dead weight. What actually matters — the ffmpeg concat
 * invocation — is produced by the exact same FfmpegCommandBuilder
 * methods BatchScriptBuilder uses, so the two builders only ever differ
 * in scripting-language wrapper, never in ffmpeg argument content.
 */
final class ShellScriptBuilder implements BatchScriptBuilderInterface
{
    public function __construct(
        private readonly FfmpegCommandBuilder $ffmpeg,
    ) {
    }

    public function build(string $sourceDir, string $destDir, string $slug, float $budgetRatio): string
    {
        $listFilePath = $sourceDir . $slug . '.txt';

        // No shebang/`set -e` header here, matching BatchScriptBuilder's
        // approach of not emitting an `@echo off` header either: this
        // return value is a fragment appended AFTER the per-file
        // reencode lines (see CourseVideoMerger::mergeCourse()), and the
        // whole file is always run via an explicit interpreter
        // invocation (`sh scriptPath` — LinuxShellProcessExecutor), never
        // executed directly, so a shebang would do nothing useful and,
        // placed mid-file by the concatenation, would only be confusing.
        $script = $this->ffmpeg->concatInputPrefix($listFilePath);

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

        return $script;
    }
}

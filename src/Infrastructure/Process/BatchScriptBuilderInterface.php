<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\Process;

/**
 * TASK-007: extracted alongside ProcessExecutorInterface so
 * CourseVideoMerger can generate either a cmd.exe batch script
 * (BatchScriptBuilder) or a POSIX shell script (ShellScriptBuilder) for
 * the staging + final-concat step, selected by PlatformResolver.
 */
interface BatchScriptBuilderInterface
{
    public function build(string $sourceDir, string $destDir, string $slug, float $budgetRatio): string;
}

<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\Process;

/**
 * TASK-007: extracted so CourseVideoMerger can run against either the
 * original Windows executor (ProcessExecutor, cmd.exe) or the new
 * LinuxShellProcessExecutor (sh) without knowing which one it has —
 * selected by PlatformResolver based on config/video.php's `platform`
 * key. Before this, ProcessExecutor was a concrete final class with no
 * seam for a second implementation.
 */
interface ProcessExecutorInterface
{
    public function run(string $scriptPath): void;
}

<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\Process;

/**
 * TASK-007: Linux counterpart to ProcessExecutor (cmd.exe). Runs the
 * generated script — a plain sequence of ffmpeg command lines, see
 * ShellScriptBuilder — through `sh`. `escapeshellarg()` is used here
 * (unlike ProcessExecutor's raw concatenation) because a shell, unlike
 * cmd.exe's `cmd /c`, treats a bare unquoted path containing spaces as
 * multiple arguments; ProcessExecutor doesn't need it because its path
 * always comes from this codebase's own generated filenames, not
 * because concatenation is generally safe.
 */
final class LinuxShellProcessExecutor implements ProcessExecutorInterface
{
    public function run(string $scriptPath): void
    {
        system('sh ' . escapeshellarg($scriptPath));
    }
}

<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\Process;

/**
 * Isolates the one OS process-execution call the original code made:
 * `system("cmd /c " . $path)`, run against a generated .bat file. Kept
 * as a thin wrapper around system() rather than switching to
 * proc_open()/Symfony Process — changing the execution mechanism is a
 * bigger behavior risk than this refactor's scope calls for (buffering,
 * exit-code handling, and output interleaving with the run's `echo`
 * calls could all shift subtly). The win here is that this is now a
 * single, mockable seam instead of a call buried inside a 60-line
 * function.
 */
final class ProcessExecutor
{
    public function runBatchFile(string $batchFilePath): void
    {
        system('cmd /c ' . $batchFilePath);
    }
}

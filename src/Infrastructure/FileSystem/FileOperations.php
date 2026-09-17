<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\FileSystem;

use RuntimeException;

/**
 * Direct extraction of action_file() from the original function.php.
 *
 * The original used die() with a bare Azerbaijani message on copy/rename
 * failure ("Nese seflik var faylin yerdeyismesinde." / "...ad
 * deyismesinde." — "something's wrong moving/renaming the file"),
 * killing the PHP process immediately and unconditionally. Replaced with
 * a RuntimeException carrying an equivalent English message plus the
 * actual paths involved, which:
 *   - stays testable (a die() can't be asserted against in a unit test;
 *     an exception can),
 *   - preserves the OBSERVABLE control-flow behavior end-to-end:
 *     bin/process.php's top-level catch still prints a one-line message
 *     and exits(1), so a script run from the CLI stops the same way it
 *     always did.
 *
 * TASK-007: the message TEXT itself changed (Azerbaijani -> English) —
 * the same fix already applied to DirectoryScanner's sentinel string,
 * extended here to the two hardcoded Azerbaijani messages found in this
 * class, per an explicit follow-up request to replace embedded literal
 * strings like these. Not "preserve behavior" territory (that mandate
 * covers the TASK-004 refactor's own scope); this task's instruction is
 * explicitly to fix this exact pattern wherever it appears.
 *
 * copy()/unlink() are unused by the current flow (only "rename" is ever
 * called from CourseVideoMerger, matching the original's only call site
 * in runProccess()) but are kept for parity with the original's public
 * surface.
 */
final class FileOperations
{
    /**
     * @param list<array{old: string, new: string}> $files
     */
    public function copyAll(array $files): void
    {
        foreach ($files as $file) {
            if (!copy($file['old'], $file['new'])) {
                throw new RuntimeException(sprintf(
                    'Something went wrong copying the file. (copy %s -> %s)',
                    $file['old'],
                    $file['new']
                ));
            }
        }
    }

    /**
     * @param list<array{old: string, new: string}> $files
     */
    public function renameAll(array $files): void
    {
        foreach ($files as $file) {
            if (!rename($file['old'], $file['new'])) {
                throw new RuntimeException(sprintf(
                    'Something went wrong renaming the file. (rename %s -> %s)',
                    $file['old'],
                    $file['new']
                ));
            }
        }
    }

    /**
     * @param list<array{old: string}> $files
     */
    public function unlinkAll(array $files): void
    {
        foreach ($files as $file) {
            // The original silently ignored unlink() failures (no die(),
            // no return-value check) — preserved as-is.
            unlink($file['old']);
        }
    }
}

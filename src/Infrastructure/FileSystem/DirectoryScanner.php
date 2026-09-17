<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\FileSystem;

/**
 * Direct extraction of getDirContents() from the original function.php —
 * a recursive directory listing that returns every non-directory file
 * under $dir (depth-first, natsort-ordered per directory level).
 *
 * FIXED (was a preserved quirk through the TASK-004 refactor, changed in
 * TASK-007 per an explicit follow-up request to stop signaling errors
 * via embedded literal strings): a missing $dir used to return the
 * literal STRING 'Qovluq yoxdur--' (Azerbaijani: "directory doesn't
 * exist--") instead of an array or throwing. Every caller assumed an
 * array; in PHP 8 that mismatch didn't fatal-error — passing a string to
 * `foreach` just emits a warning and iterates zero times — so a missing
 * source directory silently produced an EMPTY result set rather than a
 * visible error (verified by actually running the original tool against
 * a missing directory — see docs/refactor-notes.md).
 *
 * Now throws a real exception instead. This IS an observable-behavior
 * change from the original (a missing course directory now stops that
 * course instead of silently producing an empty merge) — deliberate, not
 * a silent side effect: TASK-004's "preserve behavior" mandate covered
 * that refactor only; this task's instruction is explicitly to fix this
 * exact pattern. CourseVideoMerger::mergeCourse() catches this
 * per-course so one missing directory doesn't abort a whole
 * `VIDEO_FOLDERS` batch — see that class for the new try/catch.
 */
final class DirectoryScanner
{
    /**
     * @param array<int, string> $results
     * @return array<int, string>
     * @throws \RuntimeException if $dir does not exist.
     */
    public static function scan(string $dir, array &$results = []): array
    {
        if (!is_dir($dir)) {
            throw new \RuntimeException("Source directory does not exist: {$dir}");
        }

        $files = scandir($dir);
        natsort($files);

        foreach ($files as $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path)) {
                $results[] = $path;
            } elseif ($value !== '.' && $value !== '..') {
                self::scan($path, $results);
                if (is_dir($path)) {
                    continue;
                }
                $results[] = $path;
            }
        }

        return $results;
    }
}

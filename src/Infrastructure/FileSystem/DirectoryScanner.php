<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\FileSystem;

/**
 * Direct extraction of getDirContents() from the original function.php —
 * a recursive directory listing that returns every non-directory file
 * under $dir (depth-first, natsort-ordered per directory level).
 *
 * PRESERVED QUIRK: if $dir does not exist, this returns the STRING
 * 'Qovluq yoxdur--' (Azerbaijani: "directory doesn't exist--") instead
 * of an array or throwing. Every caller in the original code assumed an
 * array; in PHP 8 that mismatch does NOT fatal-error — passing a string
 * to `foreach` just emits a warning and iterates zero times — so in
 * practice a missing source directory silently produces an EMPTY result
 * set rather than a clean, visible error. This was VERIFIED by actually
 * running the original tool against a missing source directory during
 * this refactor (see docs/refactor-notes.md): it printed the ffmpeg
 * warning "the system cannot find the path specified" and still logged
 * "Finished this directory", rather than the "no video files found"
 * message the `if (!is_array(...))` guard in runProccess() looks like
 * it was meant to produce.
 *
 * Deliberately NOT changed to an exception here: doing so would change
 * observable behavior (the caller currently proceeds with an empty
 * batch rather than stopping), which the "preserve behavior" requirement
 * for this refactor rules out. Flagged as a real candidate for a future,
 * deliberate fix — not made silently as part of a refactor.
 */
final class DirectoryScanner
{
    /**
     * @param array<int, string> $results
     * @return array<int, string>|string
     */
    public static function scan(string $dir, array &$results = []): array|string
    {
        if (!is_dir($dir)) {
            return 'Qovluq yoxdur--';
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

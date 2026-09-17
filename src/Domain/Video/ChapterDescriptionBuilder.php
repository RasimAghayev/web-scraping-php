<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Domain\Video;

/**
 * Builds the YouTube-description-style chapter index text (one
 * "HH:MM:SS - cleaned filename" line per source video) — extracted from
 * the $youtube-building loop inside the original runProccess().
 */
final class ChapterDescriptionBuilder
{
    /**
     * Release-group/tag noise stripped from filenames, plus the source
     * directory prefix and every recognized video extension. Same list,
     * same order, as the original str_replace() call.
     *
     * @var array<int, string>
     */
    private const NOISE_STRINGS = [
        '.mp4', '.mov', '.m4v', '.f4v', '.mkv', '.avi', '.wmv', '.mpg', '.flv', '.webm',
        '--- [ FreeCourseWeb.com ] ---', '--- [ DevCourseWeb.com ] ---', 'DevCourseWeb.com',
        '---[TutFlix.ORG]---', '[TutFlix(dot)ORG]. ', '--[TutFlix.ORG]--', '[Udemycourses.me]',
        '[TutFlix.ORG]', '[TutFlix.org]', 'TutFlix.ORG', 'FreeCourseWeb.com', '_Downloadly.ir',
        'Downloadly.ir', 'Lesson', 'lesson', '[HowToFree.Org] ', '[HowToFree.Org]', '[TG @coursenav] ', '---',
    ];

    /**
     * @param list<array{old: string, duration_time: string}> $videoFiles
     *        Only 'old' (original absolute path) and 'duration_time'
     *        (cumulative start offset, formatted HH:MM:SS) are read.
     * @param string $sourceCourseDir The course's source directory
     *        (matches the original's `$dir0 . $dir1 . "\\"`), stripped
     *        from each filename's front.
     */
    public static function build(array $videoFiles, string $sourceCourseDir): string
    {
        $lines = '';

        foreach ($videoFiles as $video) {
            $hours = explode(':', $video['duration_time']);

            // PRESERVED QUIRK: any start hour past 11 has 12 subtracted,
            // producing a 12-hour-cycle display instead of a real 24h+
            // clock (e.g. "13:xx:xx" becomes "01:xx:xx"). Confirmed from
            // the original's `if($hrs[0]>11)` block; kept exactly as-is.
            if ((int) $hours[0] > 11) {
                $hours[0] = ((int) $hours[0] - 12 < 10 ? '0' : '') . ((int) $hours[0] - 12);
                $displayTime = implode(':', $hours);
            } else {
                $displayTime = $video['duration_time'];
            }

            $cleanedName = str_replace(
                array_merge([$sourceCourseDir], self::NOISE_STRINGS),
                '',
                $video['old']
            );

            $lines .= $displayTime . ' - ' . $cleanedName . "\n";
        }

        return $lines;
    }
}

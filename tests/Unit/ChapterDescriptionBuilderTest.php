<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Domain\Video\ChapterDescriptionBuilder;

final class ChapterDescriptionBuilderTest extends TestCase
{
    private const SOURCE_DIR = 'D:\\Video\\zip\\MSK JavaScript Bootcamp\\';

    public function testStripsSourceDirAndReleaseTagNoise(): void
    {
        $videoFiles = [
            ['old' => self::SOURCE_DIR . '01 - Intro --- [ FreeCourseWeb.com ] ---.mp4', 'duration_time' => '00:00:00'],
        ];

        self::assertSame(
            "00:00:00 - 01 - Intro \n",
            ChapterDescriptionBuilder::build($videoFiles, self::SOURCE_DIR)
        );
    }

    public function testBuildsOneLinePerVideoInOrder(): void
    {
        $videoFiles = [
            ['old' => self::SOURCE_DIR . 'a.mp4', 'duration_time' => '00:00:00'],
            ['old' => self::SOURCE_DIR . 'b.mov', 'duration_time' => '01:00:00'],
        ];

        self::assertSame(
            "00:00:00 - a\n01:00:00 - b\n",
            ChapterDescriptionBuilder::build($videoFiles, self::SOURCE_DIR)
        );
    }

    /**
     * NOT a bug introduced by this refactor — see the "PRESERVED QUIRK"
     * note in ChapterDescriptionBuilder. Any start hour past 11 has 12
     * subtracted, so the displayed chapter time cycles every 12 hours
     * instead of showing a real 24h+ clock. Confirmed against the
     * original's `if($hrs[0]>11)` block with a real comparison harness.
     */
    public function testHoursPastElevenAreDisplayedOnATwelveHourCycle(): void
    {
        $videoFiles = [
            ['old' => self::SOURCE_DIR . 'late.mp4', 'duration_time' => '13:05:09'],
        ];

        self::assertSame(
            "01:05:09 - late\n",
            ChapterDescriptionBuilder::build($videoFiles, self::SOURCE_DIR)
        );
    }

    public function testHourElevenIsNotAffectedByTheCycle(): void
    {
        $videoFiles = [
            ['old' => self::SOURCE_DIR . 'edge.mp4', 'duration_time' => '11:59:59'],
        ];

        self::assertSame(
            "11:59:59 - edge\n",
            ChapterDescriptionBuilder::build($videoFiles, self::SOURCE_DIR)
        );
    }
}

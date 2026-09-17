<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Domain\Video\VideoBatchPlanner;
use WebScraping\VideoMerge\Infrastructure\Metadata\GetId3DurationReader;

final class VideoBatchPlannerTest extends TestCase
{
    public function testDefaultSeparatorMatchesTheOriginalWindowsBehavior(): void
    {
        $planner = new VideoBatchPlanner(new GetId3DurationReader());

        $plan = $planner->plan(['D:\\Video\\zip\\course\\a.mp4'], 'D:\\Video\\Dn\\course');

        self::assertSame('D:\\Video\\Dn\\course\\1-a.mp41', $plan[0]['new']);
        self::assertSame('D:\\Video\\Dn\\course\\1.mp4', $plan[0]['new1']);
    }

    /**
     * TASK-007: before injecting the separator, basename() only ever
     * looked for a backslash. A realpath()-normalized Linux path like
     * '/data/zip/course/a.mp4' has none, so the slug silently degraded
     * to '' for every file. This asserts the real basename ('a.mp4') is
     * recovered when the separator is '/'.
     */
    public function testLinuxSeparatorProducesRealSlashSeparatedPathsAndSlugs(): void
    {
        $planner = new VideoBatchPlanner(new GetId3DurationReader(), '/');

        $plan = $planner->plan(['/data/zip/course/a.mp4'], '/data/dn/course');

        self::assertSame('/data/dn/course/1-a.mp41', $plan[0]['new']);
        self::assertSame('/data/dn/course/1.mp4', $plan[0]['new1']);
    }

    public function testNonVideoFilesAreSkippedAndIndexOnlyAdvancesForMatches(): void
    {
        $planner = new VideoBatchPlanner(new GetId3DurationReader(), '/');

        $plan = $planner->plan(['/data/zip/course/readme.txt', '/data/zip/course/b.mkv'], '/data/dn/course');

        self::assertCount(1, $plan);
        self::assertSame('/data/dn/course/1-b.mkv1', $plan[0]['new']);
    }
}

<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Domain\Video\OutputSegmentPlanner;

final class OutputSegmentPlannerTest extends TestCase
{
    public function testShortCourseNeedsASingleFile(): void
    {
        $ratio = OutputSegmentPlanner::budgetRatio(1000);
        self::assertTrue(OutputSegmentPlanner::needsSingleFile($ratio));
    }

    public function testCourseJustUnderTheBudgetStillNeedsASingleFile(): void
    {
        $ratio = OutputSegmentPlanner::budgetRatio(43198);
        self::assertTrue(OutputSegmentPlanner::needsSingleFile($ratio));
    }

    /**
     * NOT a bug introduced by this refactor — see
     * OutputSegmentPlanner's "off-by-one at exact multiples" docblock.
     * A duration landing exactly on a budget multiple produces one
     * extra segment versus a duration just under it, because the
     * original's loop bound is `<=` against a float ratio. Confirmed
     * against the original runProccess() loop with a real comparison
     * harness, not guessed.
     */
    public function testDurationAtExactBudgetMultipleProducesOneExtraSegment(): void
    {
        $ratio = OutputSegmentPlanner::budgetRatio(43199); // exactly 1x the budget
        self::assertFalse(OutputSegmentPlanner::needsSingleFile($ratio));
        self::assertSame(2, OutputSegmentPlanner::segmentCount($ratio));
    }

    public function testDurationJustOverTheBudgetProducesTwoSegments(): void
    {
        $ratio = OutputSegmentPlanner::budgetRatio(43200);
        self::assertSame(2, OutputSegmentPlanner::segmentCount($ratio));
    }

    public function testStartTimestampsAreTwelveHoursApart(): void
    {
        self::assertSame('00:00:00', OutputSegmentPlanner::startTimestamp(0));
        self::assertSame('11:59:59', OutputSegmentPlanner::startTimestamp(1));
        self::assertSame('23:59:59', OutputSegmentPlanner::startTimestamp(2));
    }

    /**
     * NOT a bug introduced by this refactor — the original's $time_stmp
     * table has exactly 33 entries and was never guarded against a
     * longer course; indexing past it silently produced an "Undefined
     * array offset" warning and an empty string in PHP 8, rather than a
     * fatal error. Reproduced here via the null-coalescing fallback in
     * startTimestamp(), not fixed with new validation.
     */
    public function testStartTimestampPastTheTableFallsBackToEmptyString(): void
    {
        self::assertSame('', OutputSegmentPlanner::startTimestamp(33));
    }
}

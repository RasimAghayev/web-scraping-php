<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Support\DurationFormatter;

final class DurationFormatterTest extends TestCase
{
    public function testZeroSecondsIsAllZeroes(): void
    {
        self::assertSame('00:00:00', DurationFormatter::format(0));
    }

    public function testUnderOneMinute(): void
    {
        self::assertSame('00:00:59', DurationFormatter::format(59));
    }

    public function testExactlyOneHour(): void
    {
        self::assertSame('01:00:00', DurationFormatter::format(3600));
    }

    public function testHoursMinutesAndSeconds(): void
    {
        self::assertSame('01:01:01', DurationFormatter::format(3661));
    }

    /**
     * NOT a bug introduced by this refactor — see DurationFormatter's
     * "PRESERVED QUIRK" docblock. Hours are `fmod($seconds / 3600, 24)`,
     * so a duration of exactly 24 hours wraps back to "00", not "24".
     * Confirmed against the original time_elapsed_A() with a real
     * comparison harness, not guessed.
     */
    public function testTwentyFourHoursWrapsToZero(): void
    {
        self::assertSame('00:00:00', DurationFormatter::format(86400));
    }

    public function testJustUnderTwentyFourHoursDoesNotWrap(): void
    {
        self::assertSame('23:59:59', DurationFormatter::format(86399));
    }

    public function testFractionalSecondsAreFlooredNotRounded(): void
    {
        self::assertSame('00:00:00', DurationFormatter::format(0.4));
    }
}

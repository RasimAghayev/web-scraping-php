<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Domain\Video\OutputSegmentPlanner;
use WebScraping\VideoMerge\Infrastructure\Ffmpeg\FfmpegCommandBuilder;
use WebScraping\VideoMerge\Infrastructure\Process\ShellScriptBuilder;

final class ShellScriptBuilderTest extends TestCase
{
    private ShellScriptBuilder $builder;

    protected function setUp(): void
    {
        $ffmpeg = new FfmpegCommandBuilder(
            '/usr/local/bin/ffmpeg',
            '-c:v libx264 -s 1920x1080 -r 30 -b:v 2M',
            '-c:a aac -ac 2 -ar 44100 -b:a 192k'
        );
        $this->builder = new ShellScriptBuilder($ffmpeg);
    }

    /**
     * Unlike BatchScriptBuilder, no mkdir/move/rename preamble — see
     * ShellScriptBuilder's class docblock for why (that preamble is
     * dead code even on the Windows side it was copied from).
     */
    public function testSingleFileCourseGeneratesOneOutputBlockWithNoPreamble(): void
    {
        $ratio = OutputSegmentPlanner::budgetRatio(1000);

        self::assertSame(
            '/usr/local/bin/ffmpeg -safe 0 -f concat -segment_time_metadata 1 -i /data/zip/my-course.txt '
                . '-c copy /data/dn/my-course.mp4 ',
            $this->builder->build('/data/zip/', '/data/dn/my-course', 'my-course', $ratio)
        );
    }

    public function testLongCourseChainsMultipleSegmentsOntoOneFfmpegInvocation(): void
    {
        $ratio = OutputSegmentPlanner::budgetRatio(90000); // just over 2x the ~12h budget -> 3 segments

        $script = $this->builder->build('/data/zip/', '/data/dn/my-course', 'my-course', $ratio);

        self::assertSame(1, substr_count($script, '/usr/local/bin/ffmpeg'), 'exactly one ffmpeg invocation, not one per segment');
        self::assertStringContainsString('-ss 00:00:00 -t 11:59:59 /data/dn/my-course-0.mp4', $script);
        self::assertStringContainsString('-ss 11:59:59 -t 11:59:59 /data/dn/my-course-1.mp4', $script);
        self::assertStringContainsString('-ss 23:59:59 -t 11:59:59 /data/dn/my-course-2.mp4', $script);
    }
}

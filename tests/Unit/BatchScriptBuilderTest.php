<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Domain\Video\OutputSegmentPlanner;
use WebScraping\VideoMerge\Infrastructure\Ffmpeg\FfmpegCommandBuilder;
use WebScraping\VideoMerge\Infrastructure\Process\BatchScriptBuilder;

final class BatchScriptBuilderTest extends TestCase
{
    private BatchScriptBuilder $builder;

    protected function setUp(): void
    {
        $ffmpeg = new FfmpegCommandBuilder(
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
            '-c:v libx264 -s 1920x1080 -r 30 -b:v 2M',
            '-c:a aac -ac 2 -ar 44100 -b:a 192k'
        );
        $this->builder = new BatchScriptBuilder($ffmpeg);
    }

    public function testSingleFileCourseGeneratesOneOutputBlock(): void
    {
        $ratio = OutputSegmentPlanner::budgetRatio(1000);

        self::assertSame(
            "mkdir D:\\Video\\Dn\\my-course\\1 && move D:\\Video\\Dn\\my-course\\*.*1 D:\\Video\\Dn\\my-course\\1\\ \n"
                . "cd D:\\Video\\Dn\\my-course\\1\\ \n"
                . "rename *.mp41 *.mp4\n"
                . "cd D:\\Video\\Dn\\my-course\\ \n"
                . "C:\\ffmpeg\\bin\\ffmpeg.exe -safe 0 -f concat -segment_time_metadata 1 -i D:\\Video\\zip\\my-course.txt "
                . "-c copy D:\\Video\\Dn\\my-course.mp4  && exit",
            $this->builder->build('D:\\Video\\zip\\', 'D:\\Video\\Dn\\my-course', 'my-course', $ratio)
        );
    }

    /**
     * A single ffmpeg invocation carries multiple `-c copy -ss X -t Y`
     * output blocks chained after one `-i <concat-list>` input — NOT one
     * process per segment. This is verbatim from the original
     * runProccess(); see BatchScriptBuilder's class docblock.
     */
    public function testLongCourseChainsMultipleSegmentsOntoOneFfmpegInvocation(): void
    {
        $ratio = OutputSegmentPlanner::budgetRatio(90000); // just over 2x the ~12h budget -> 3 segments

        $script = $this->builder->build('D:\\Video\\zip\\', 'D:\\Video\\Dn\\my-course', 'my-course', $ratio);

        self::assertSame(1, substr_count($script, 'ffmpeg.exe'), 'exactly one ffmpeg invocation, not one per segment');
        self::assertStringContainsString('-ss 00:00:00 -t 11:59:59 D:\\Video\\Dn\\my-course-0.mp4', $script);
        self::assertStringContainsString('-ss 11:59:59 -t 11:59:59 D:\\Video\\Dn\\my-course-1.mp4', $script);
        self::assertStringContainsString('-ss 23:59:59 -t 11:59:59 D:\\Video\\Dn\\my-course-2.mp4', $script);
        self::assertStringEndsWith(' && exit', $script);
    }
}

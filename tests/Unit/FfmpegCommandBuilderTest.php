<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Infrastructure\Ffmpeg\FfmpegCommandBuilder;

final class FfmpegCommandBuilderTest extends TestCase
{
    private FfmpegCommandBuilder $ffmpeg;

    protected function setUp(): void
    {
        $this->ffmpeg = new FfmpegCommandBuilder(
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
            '-c:v libx264 -s 1920x1080 -r 30 -b:v 2M',
            '-c:a aac -ac 2 -ar 44100 -b:a 192k'
        );
    }

    public function testReencodeCommandConvertsBackslashesToForwardSlashes(): void
    {
        self::assertSame(
            'C:\\ffmpeg\\bin\\ffmpeg.exe -i D:/Video/Dn/course/1-intro1 -cpu-used 32 -map_metadata -1 '
                . '-c:v libx264 -s 1920x1080 -r 30 -b:v 2M -c:a aac -ac 2 -ar 44100 -b:a 192k '
                . '-preset ultrafast -profile:v main -pix_fmt yuv420p -movflags +faststart D:/Video/Dn/course/1.mp4',
            $this->ffmpeg->reencodeCommand('D:\\Video\\Dn\\course\\1-intro1', 'D:\\Video\\Dn\\course\\1.mp4')
        );
    }

    /**
     * NOT a bug introduced by this refactor — see
     * RenameManifestWriter::stripLegacyPrefix()'s docblock. The legacy
     * `D:\IDM\IDM2\tt\` prefix is stripped from paths passed into the
     * ffmpeg command line, same as the original file_write().
     */
    public function testReencodeCommandStripsTheLegacyPathPrefix(): void
    {
        $command = $this->ffmpeg->reencodeCommand('D:\\IDM\\IDM2\\tt\\2-lesson1', 'D:\\IDM\\IDM2\\tt\\2.mp4');

        self::assertStringContainsString(' -i 2-lesson1 ', $command);
        self::assertStringEndsWith('faststart 2.mp4', $command);
    }

    public function testConcatInputPrefixHasATrailingSpace(): void
    {
        self::assertSame(
            'C:\\ffmpeg\\bin\\ffmpeg.exe -safe 0 -f concat -segment_time_metadata 1 -i D:\\Video\\zip\\my-course.txt ',
            $this->ffmpeg->concatInputPrefix('D:\\Video\\zip\\my-course.txt')
        );
    }

    public function testSingleOutputSpec(): void
    {
        self::assertSame(
            '-c copy D:\\Video\\Dn\\my-course.mp4 ',
            $this->ffmpeg->singleOutputSpec('D:\\Video\\Dn\\my-course')
        );
    }

    /** The trailing DOUBLE space is verbatim from the original — see the method's docblock. */
    public function testSegmentOutputSpecHasTwoTrailingSpaces(): void
    {
        self::assertSame(
            '-c copy -ss 11:59:59 -t 11:59:59 D:\\Video\\Dn\\my-course-1.mp4  ',
            $this->ffmpeg->segmentOutputSpec('D:\\Video\\Dn\\my-course', '11:59:59', 1)
        );
    }
}

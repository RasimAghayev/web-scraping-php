<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Domain\Video\VideoExtension;

final class VideoExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function filenameProvider(): iterable
    {
        yield 'mp4' => ['course/01-intro.mp4', true];
        yield 'uppercase MP4' => ['course/01-intro.MP4', true];
        yield 'mov' => ['course/01-intro.mov', true];
        yield 'webm (4-char extension)' => ['course/01-intro.webm', true];
        yield 'uppercase WEBM' => ['course/01-intro.WEBM', true];
        yield 'm4v' => ['course/01-intro.m4v', true];
        yield 'mixed case MpG' => ['course/weird.MpG', true];
        yield 'not a video extension' => ['course/notes.txt', false];
        yield 'no extension at all' => ['README', false];
        yield 'zip archive' => ['course.zip', false];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('filenameProvider')]
    public function testMatchesRecognizedVideoExtensionsCaseInsensitively(string $filename, bool $expected): void
    {
        self::assertSame($expected, VideoExtension::matches($filename));
    }
}

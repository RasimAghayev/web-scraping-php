<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Infrastructure\FileSystem\DirectoryScanner;

final class DirectoryScannerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dirscan-test-' . uniqid();
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'sub', 0777, true);
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'a.mp4', 'x');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'sub' . DIRECTORY_SEPARATOR . 'b.mkv', 'x');
    }

    protected function tearDown(): void
    {
        @unlink($this->tempDir . DIRECTORY_SEPARATOR . 'a.mp4');
        @unlink($this->tempDir . DIRECTORY_SEPARATOR . 'sub' . DIRECTORY_SEPARATOR . 'b.mkv');
        @rmdir($this->tempDir . DIRECTORY_SEPARATOR . 'sub');
        @rmdir($this->tempDir);
    }

    public function testFindsFilesRecursivelyAcrossSubdirectories(): void
    {
        $results = DirectoryScanner::scan($this->tempDir);

        self::assertIsArray($results);
        self::assertCount(2, $results);

        $basenames = array_map('basename', $results);
        sort($basenames);
        self::assertSame(['a.mp4', 'b.mkv'], $basenames);
    }

    /**
     * TASK-007: was 'Qovluq yoxdur--' (a hardcoded Azerbaijani sentinel
     * string) through the TASK-004 refactor — see DirectoryScanner's
     * docblock for the full history. Fixed to throw per an explicit
     * follow-up request to stop signaling errors via embedded literal
     * strings.
     */
    public function testMissingDirectoryThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        DirectoryScanner::scan($this->tempDir . '-does-not-exist');
    }
}

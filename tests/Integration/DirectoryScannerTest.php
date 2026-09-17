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
     * NOT a bug introduced by this refactor — see DirectoryScanner's
     * "PRESERVED QUIRK" docblock. A missing directory returns a
     * hardcoded Azerbaijani string instead of an empty array or an
     * exception; verified against the original getDirContents() by
     * actually running it against a missing directory during this
     * refactor.
     */
    public function testMissingDirectoryReturnsTheLegacySentinelString(): void
    {
        self::assertSame(
            'Qovluq yoxdur--',
            DirectoryScanner::scan($this->tempDir . '-does-not-exist')
        );
    }
}

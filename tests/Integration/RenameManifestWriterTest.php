<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Infrastructure\FileSystem\RenameManifestWriter;

final class RenameManifestWriterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'manifest-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);
    }

    public function testWritesOldAndConcatManifestFiles(): void
    {
        $writer = new RenameManifestWriter();
        $videoFiles = [
            ['old' => 'D:\\Video\\zip\\course\\a.mp4', 'new1' => 'D:\\Video\\Dn\\course\\1.mp4'],
            ['old' => 'D:\\Video\\zip\\course\\b.mp4', 'new1' => 'D:\\Video\\Dn\\course\\2.mp4'],
        ];

        $writer->write($this->tempDir, 'course', 'course-slug', $videoFiles);

        // '[0-9]' distinguishes the randomized old-file manifest
        // ("course<mt_rand digits>.txt") from the slug-based concat
        // manifest ("course-slug.txt"), which also starts with "course".
        $oldFiles = glob($this->tempDir . DIRECTORY_SEPARATOR . 'course[0-9]*.txt') ?: [];
        self::assertCount(1, $oldFiles, 'exactly one randomized old-file manifest was written');
        self::assertSame(
            "D:\\Video\\zip\\course\\a.mp4\nD:\\Video\\zip\\course\\b.mp4\n",
            file_get_contents($oldFiles[0])
        );

        $concatContent = file_get_contents($this->tempDir . DIRECTORY_SEPARATOR . 'course-slug.txt');
        self::assertSame(
            "file D:/Video/Dn/course/1.mp4\nfile D:/Video/Dn/course/2.mp4\n",
            $concatContent
        );
    }

    /**
     * NOT a bug introduced by this refactor — see
     * RenameManifestWriter::stripLegacyPrefix()'s docblock. The old-file
     * manifest only strips the legacy prefix (no backslash conversion);
     * the concat manifest additionally converts backslashes to forward
     * slashes. This asymmetry is verbatim from the original file_write().
     */
    public function testOldManifestKeepsBackslashesButConcatManifestDoesNot(): void
    {
        $writer = new RenameManifestWriter();
        $videoFiles = [
            ['old' => 'D:\\IDM\\IDM2\\tt\\a.mp4', 'new1' => 'D:\\IDM\\IDM2\\tt\\1.mp4'],
        ];

        $writer->write($this->tempDir, 'course', 'course-slug', $videoFiles);

        // '[0-9]' distinguishes the randomized old-file manifest
        // ("course<mt_rand digits>.txt") from the slug-based concat
        // manifest ("course-slug.txt"), which also starts with "course".
        $oldFiles = glob($this->tempDir . DIRECTORY_SEPARATOR . 'course[0-9]*.txt') ?: [];
        self::assertSame("a.mp4\n", file_get_contents($oldFiles[0]));
        self::assertSame("file 1.mp4\n", file_get_contents($this->tempDir . DIRECTORY_SEPARATOR . 'course-slug.txt'));
    }
}

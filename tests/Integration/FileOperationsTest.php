<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Infrastructure\FileSystem\FileOperations;

/**
 * TASK-007: no test existed for this class before this task (confirmed
 * by searching tests/) — added alongside the Azerbaijani-message fix
 * (see FileOperations's class docblock) so the new message text is
 * actually asserted, not just eyeballed.
 */
final class FileOperationsTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'file-ops-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);
    }

    public function testRenameAllMovesEveryFile(): void
    {
        $old = $this->tempDir . DIRECTORY_SEPARATOR . 'a.mp4';
        $new = $this->tempDir . DIRECTORY_SEPARATOR . 'a-renamed.mp4';
        file_put_contents($old, 'x');

        (new FileOperations())->renameAll([['old' => $old, 'new' => $new]]);

        self::assertFileDoesNotExist($old);
        self::assertFileExists($new);
    }

    public function testRenameAllThrowsWithPathsOnFailureInsteadOfDying(): void
    {
        $missing = $this->tempDir . DIRECTORY_SEPARATOR . 'does-not-exist.mp4';
        $target = $this->tempDir . DIRECTORY_SEPARATOR . 'target.mp4';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Something went wrong renaming the file. (rename {$missing} -> {$target})");

        // @: rename() against a missing source also emits a PHP warning
        // (unrelated to this test — it's the same "no return-value check
        // ever mattered because die() would fire first" gap the original
        // action_file() had). renameAll() already turns the return-value
        // check into the RuntimeException asserted above; the warning
        // itself isn't what's under test here.
        @(new FileOperations())->renameAll([['old' => $missing, 'new' => $target]]);
    }
}

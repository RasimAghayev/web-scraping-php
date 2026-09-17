<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebScraping\VideoMerge\Infrastructure\Ffmpeg\FfmpegCommandBuilder;
use WebScraping\VideoMerge\Infrastructure\Process\BatchScriptBuilder;
use WebScraping\VideoMerge\Infrastructure\Process\PlatformResolver;
use WebScraping\VideoMerge\Infrastructure\Process\ProcessExecutor;
use WebScraping\VideoMerge\Infrastructure\Process\LinuxShellProcessExecutor;
use WebScraping\VideoMerge\Infrastructure\Process\ShellScriptBuilder;

final class PlatformResolverTest extends TestCase
{
    private FfmpegCommandBuilder $ffmpeg;

    protected function setUp(): void
    {
        $this->ffmpeg = new FfmpegCommandBuilder('/usr/bin/ffmpeg', '-c:v libx264', '-c:a aac');
    }

    public function testWindowsResolvesToTheCmdExePair(): void
    {
        $platform = PlatformResolver::resolve('windows', $this->ffmpeg);

        self::assertInstanceOf(BatchScriptBuilder::class, $platform->scriptBuilder);
        self::assertInstanceOf(ProcessExecutor::class, $platform->executor);
        self::assertSame('\\', $platform->pathSeparator);
        self::assertSame('.bat', $platform->scriptExtension);
    }

    public function testLinuxResolvesToTheShellPair(): void
    {
        $platform = PlatformResolver::resolve('linux', $this->ffmpeg);

        self::assertInstanceOf(ShellScriptBuilder::class, $platform->scriptBuilder);
        self::assertInstanceOf(LinuxShellProcessExecutor::class, $platform->executor);
        self::assertSame('/', $platform->pathSeparator);
        self::assertSame('.sh', $platform->scriptExtension);
    }

    public function testAutoDetectsBasedOnPhpOsFamily(): void
    {
        $platform = PlatformResolver::resolve('auto', $this->ffmpeg);

        self::assertSame(
            PHP_OS_FAMILY === 'Windows' ? '\\' : '/',
            $platform->pathSeparator
        );
    }

    public function testUnknownPlatformThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown VIDEO_MERGE_PLATFORM 'bsd'");

        PlatformResolver::resolve('bsd', $this->ffmpeg);
    }
}

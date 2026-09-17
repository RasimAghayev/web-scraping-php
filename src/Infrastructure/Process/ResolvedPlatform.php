<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\Process;

/**
 * TASK-007: everything PlatformResolver decides for one video-merge run,
 * bundled so bin/process.php/function.php have a single object to wire
 * into CourseVideoMerger instead of four separate resolver calls.
 */
final class ResolvedPlatform
{
    public function __construct(
        public readonly BatchScriptBuilderInterface $scriptBuilder,
        public readonly ProcessExecutorInterface $executor,
        public readonly string $pathSeparator,
        public readonly string $scriptExtension,
    ) {
    }
}

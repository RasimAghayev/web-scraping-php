<?php

declare(strict_types=1);

namespace WebScraping\VideoMerge\Infrastructure\Ffmpeg;

use WebScraping\VideoMerge\Infrastructure\FileSystem\RenameManifestWriter;

/**
 * Pure ffmpeg argument-string construction — no filesystem access, no
 * process execution. Extracted from two places in the original
 * function.php: the per-file re-encode line inside file_write(), and
 * the concat/segment command built inline in runProccess() (there,
 * mixed together with cmd.exe batch-script text — see
 * Infrastructure\Process\BatchScriptBuilder for that separation).
 *
 * Every literal flag, flag order, and even incidental whitespace below
 * is copied verbatim from the original strings — this generates
 * byte-identical ffmpeg command lines, not just equivalent ones.
 */
final class FfmpegCommandBuilder
{
    public function __construct(
        private readonly string $ffmpegBin,
        private readonly string $videoCodec,
        private readonly string $audioCodec,
        private readonly string $legacyPathPrefix = RenameManifestWriter::DEFAULT_LEGACY_PATH_PREFIX,
    ) {
    }

    /**
     * Per-file normalize/re-encode step. Matches file_write()'s
     * $cmd_content loop body exactly, including running both input and
     * output paths through the same legacy-prefix-strip +
     * backslash-to-forward-slash conversion the original applied.
     */
    public function reencodeCommand(string $inputPath, string $outputPath): string
    {
        return $this->ffmpegBin . ' -i ' . RenameManifestWriter::stripLegacyPrefix($inputPath, $this->legacyPathPrefix)
            . ' -cpu-used 32 -map_metadata -1 ' . $this->videoCodec . ' ' . $this->audioCodec
            . ' -preset ultrafast -profile:v main -pix_fmt yuv420p -movflags +faststart '
            . RenameManifestWriter::stripLegacyPrefix($outputPath, $this->legacyPathPrefix);
    }

    /**
     * The shared "-i <concat list>" prefix common to both the
     * single-output and segmented-output cases in the original
     * runProccess(). Trailing space preserved verbatim (the original
     * appends the output spec directly onto this string with no
     * separator of its own).
     */
    public function concatInputPrefix(string $listFilePath): string
    {
        return $this->ffmpegBin . ' -safe 0 -f concat -segment_time_metadata 1 -i ' . $listFilePath . ' ';
    }

    /** Single-output spec, used when the course fits in one ~12h file. Trailing space preserved verbatim. */
    public function singleOutputSpec(string $outputPath): string
    {
        return '-c copy ' . $outputPath . '.mp4 ';
    }

    /**
     * One segment's output spec. Two trailing spaces preserved verbatim
     * (the original's literal ends in `.mp4  ` — two spaces — since
     * multiple calls to this are concatenated directly onto the same
     * ffmpeg command line, one per -ss/-t output block).
     */
    public function segmentOutputSpec(string $outputBasePath, string $startTimestamp, int $segmentIndex): string
    {
        return '-c copy -ss ' . $startTimestamp . ' -t 11:59:59 ' . $outputBasePath . '-' . $segmentIndex . '.mp4  ';
    }
}

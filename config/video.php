<?php

declare(strict_types=1);

use WebScraping\VideoMerge\Infrastructure\FileSystem\RenameManifestWriter;

/**
 * Video-merge configuration. Every value here defaults to exactly what
 * the original hardcoded in function.php/video_merge.php — nothing
 * changes for an existing setup unless one of these env vars is
 * explicitly set. The directory/binary/folder settings were made
 * env-overridable in an earlier pass (TASK-003/004); TASK-007 extends
 * the same treatment to the remaining hardcoded literals identified as
 * candidates (ffmpeg codec/audio flags, the video-extension whitelist,
 * the legacy path-prefix constant) — see docs/architecture.md
 * "Configuration" for the full table and which class reads each value.
 */
return [
    'source_dir' => getenv('VIDEO_SOURCE_DIR') ?: 'D:\\Video\\zip\\',
    'dest_dir' => getenv('VIDEO_DEST_DIR') ?: 'D:\\Video\\Dn\\',
    'folders' => getenv('VIDEO_FOLDERS')
        ? array_map('trim', explode(',', getenv('VIDEO_FOLDERS')))
        : ['MSK JavaScript Bootcamp'],
    'ffmpeg_bin' => getenv('FFMPEG_BIN') ?: 'E:\\DevOps\\OpenServer\\domains\\videomerge.azp\\old\\ffmpeg\\bin\\ffmpeg.exe',

    // Matches the original's hardcoded $video_codec / $audio_codec
    // strings in file_write() exactly, now overridable (TASK-007).
    'video_codec' => getenv('VIDEO_CODEC_ARGS') ?: '-c:v libx264 -s 1920x1080 -r 30 -b:v 2M',
    'audio_codec' => getenv('AUDIO_CODEC_ARGS') ?: '-c:a aac -ac 2 -ar 44100 -b:a 192k',

    // TASK-007: RenameManifestWriter::write() and
    // FfmpegCommandBuilder::reencodeCommand() both strip this prefix;
    // see RenameManifestWriter's docblock for why the default can't be
    // "corrected" to match source_dir/dest_dir above — it's independent,
    // leftover state from a different machine layout, not a typo of
    // these two.
    'legacy_path_prefix' => getenv('LEGACY_PATH_PREFIX') ?: RenameManifestWriter::DEFAULT_LEGACY_PATH_PREFIX,

    // Execution platform for the batch/shell script + ffmpeg concat step
    // (TASK-007's Linux pipeline — see
    // Infrastructure\Process\ProcessExecutorFactory). 'auto' detects via
    // PHP_OS_FAMILY; force with VIDEO_MERGE_PLATFORM=windows|linux (e.g.
    // to test the Linux path from a Windows host, or vice versa).
    'platform' => getenv('VIDEO_MERGE_PLATFORM') ?: 'auto',
];

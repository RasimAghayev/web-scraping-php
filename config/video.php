<?php

declare(strict_types=1);

/**
 * Video-merge configuration. Every value here defaults to exactly what
 * the original hardcoded in function.php/video_merge.php — nothing
 * changes for an existing setup unless one of these env vars is
 * explicitly set. The four directory/binary/folder settings were
 * already made env-overridable in a prior pass (see
 * .sdd/projects/web-scraping-php.sdd); this consolidates them alongside
 * the encoding settings that were previously hardcoded inline in
 * file_write(), per this refactor's "move hardcoded environment-specific
 * values into configuration" goal.
 */
return [
    'source_dir' => getenv('VIDEO_SOURCE_DIR') ?: 'D:\\Video\\zip\\',
    'dest_dir' => getenv('VIDEO_DEST_DIR') ?: 'D:\\Video\\Dn\\',
    'folders' => getenv('VIDEO_FOLDERS')
        ? array_map('trim', explode(',', getenv('VIDEO_FOLDERS')))
        : ['MSK JavaScript Bootcamp'],
    'ffmpeg_bin' => getenv('FFMPEG_BIN') ?: 'E:\\DevOps\\OpenServer\\domains\\videomerge.azp\\old\\ffmpeg\\bin\\ffmpeg.exe',

    // Matches the original's hardcoded $video_codec / $audio_codec
    // strings in file_write() exactly.
    'video_codec' => '-c:v libx264 -s 1920x1080 -r 30 -b:v 2M',
    'audio_codec' => '-c:a aac -ac 2 -ar 44100 -b:a 192k',
];

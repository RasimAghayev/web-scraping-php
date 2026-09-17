# Architecture

Added in TASK-007 as part of expanding `docs/` — this is the reference for
the video-merge utility's structure; `docs/refactor-notes.md` is the
history of *how* it got here (the TASK-004 extraction from the original
`function.php` monolith) and stays the place for byte-identical-behavior
verification detail. This file is the current-state map.

The scraper (`index.php`) isn't covered here beyond a one-line mention —
it's a single script with no internal layering to document.

## Architecture style

**Layered / Clean-Architecture-inspired.** Not a label reached for — it's
what the directory structure below actually enforces:

- `Domain/Video/*` has no filesystem, network, or process-execution calls
  anywhere in it. It's pure planning/rules (extension matching, duration
  bookkeeping, segment-count math, chapter-description text). Every
  method is either `static` or takes plain scalars/arrays in, returns
  plain scalars/arrays out.
- `Infrastructure/*` is where every side effect lives — filesystem
  (`FileSystem/`), the external `ffmpeg` binary (`Ffmpeg/`), OS process
  execution (`Process/`), video-metadata probing (`Metadata/`) — each
  behind a small, focused class. `Infrastructure/Process` additionally
  defines two interfaces (`ProcessExecutorInterface`,
  `BatchScriptBuilderInterface`) so the layer above doesn't need to know
  whether it's talking to the Windows or Linux implementation.
- `Application/CourseVideoMerger` is the only orchestrator — it calls
  `Domain` to decide *what* to do and `Infrastructure` to actually do it,
  and contains no business rules of its own beyond sequencing.
- `Support/*` is framework-free, dependency-free helpers (slugging,
  duration formatting) used by more than one layer.

What this is **not**, and why those labels were left off the README
([R112]):

- **Not DDD.** No entities or aggregates with enforced invariants, no
  repositories, no ubiquitous-language modeling exercise — `Domain/Video`
  is "the business rules live in one place", which is necessary for
  layering but isn't the same claim DDD makes.
- **Not CQRS.** There's no command/query split — `mergeAll()` is a single
  imperative pipeline, not two models kept in sync.
- **Not event-driven.** No events, no message bus, no pub/sub — every call
  is a direct, synchronous method call down through the layers.

## Directory map

```
bin/process.php                        CLI entry point (wires config -> classes -> CourseVideoMerger)
config/video.php                       All env-overridable configuration (see table below)
src/
├── Application/
│   └── CourseVideoMerger.php          Orchestrator: scan -> plan -> script -> rename -> execute
├── Domain/Video/
│   ├── VideoExtension.php             Extension whitelist + match rule
│   ├── VideoBatchPlanner.php          Builds the rename/duration plan (the `old`/`new`/`new1` shape)
│   ├── OutputSegmentPlanner.php       ~12h segment-count/timestamp math
│   └── ChapterDescriptionBuilder.php  YouTube-style chapter description text
├── Infrastructure/
│   ├── FileSystem/
│   │   ├── DirectoryScanner.php       Recursive file listing (throws on missing dir)
│   │   ├── FileOperations.php         copy/rename/unlink, throws RuntimeException on failure
│   │   └── RenameManifestWriter.php   Writes the old-file + concat-list .txt manifests
│   ├── Ffmpeg/
│   │   └── FfmpegCommandBuilder.php   Pure ffmpeg argument-string construction, no I/O
│   ├── Process/
│   │   ├── ProcessExecutorInterface.php       run(scriptPath): void
│   │   ├── BatchScriptBuilderInterface.php    build(...): string
│   │   ├── ProcessExecutor.php                Windows: system('cmd /c ' . $path)
│   │   ├── BatchScriptBuilder.php             Windows: generates the .bat text
│   │   ├── LinuxShellProcessExecutor.php      Linux: system('sh ' . escapeshellarg($path))
│   │   ├── ShellScriptBuilder.php             Linux: generates the .sh text
│   │   ├── PlatformResolver.php               Picks the pair above from `platform` config
│   │   └── ResolvedPlatform.php               DTO PlatformResolver returns
│   └── Metadata/
│       └── GetId3DurationReader.php   Wraps getID3's duration probe
└── Support/
    ├── Slugger.php                    Filename slugging (Cyrillic transliteration + cleanup)
    └── DurationFormatter.php          Seconds -> "HH:MM:SS"-ish text
```

`function.php` and `video_merge.php` are deprecated compatibility shims
over the classes above — see `function.php`'s own header for why they're
kept rather than deleted.

## Configuration

Every entry is read once, in `config/video.php`, via `getenv()` with the
original hardcoded literal as the fallback default — nothing changes for
an existing setup unless the variable is explicitly set.

| Key | Env var | Default | Read by |
|---|---|---|---|
| `source_dir` | `VIDEO_SOURCE_DIR` | `D:\Video\zip\` | `bin/process.php` → `CourseVideoMerger::mergeAll()` |
| `dest_dir` | `VIDEO_DEST_DIR` | `D:\Video\Dn\` | `bin/process.php` → `CourseVideoMerger::mergeAll()` |
| `folders` | `VIDEO_FOLDERS` | `MSK JavaScript Bootcamp` | `bin/process.php` → `CourseVideoMerger::mergeAll()` |
| `ffmpeg_bin` | `FFMPEG_BIN` | (Windows path) | `FfmpegCommandBuilder` (via constructor) |
| `video_codec` | `VIDEO_CODEC_ARGS` | `-c:v libx264 -s 1920x1080 -r 30 -b:v 2M` | `FfmpegCommandBuilder::reencodeCommand()` |
| `audio_codec` | `AUDIO_CODEC_ARGS` | `-c:a aac -ac 2 -ar 44100 -b:a 192k` | `FfmpegCommandBuilder::reencodeCommand()` |
| `legacy_path_prefix` | `LEGACY_PATH_PREFIX` | `D:\IDM\IDM2\tt\` | `RenameManifestWriter`, `FfmpegCommandBuilder` |
| — (class const) | `VIDEO_EXTENSIONS` | 10-extension list | `VideoExtension::matches()` (reads env directly, not via `config/video.php`) |
| `platform` | `VIDEO_MERGE_PLATFORM` | `auto` | `PlatformResolver::resolve()` |

`VIDEO_EXTENSIONS` is the one exception to "everything flows through
`config/video.php`" — `VideoExtension` reads `getenv()` directly, matching
where the original hardcoded list actually lived (a class constant, not
config). Documented here rather than "fixed" to route through config,
since that would move a value nothing else needs out of the one class
that owns the rule it's part of.

## Execution-platform resolution

```
config/video.php ['platform']  (auto | windows | linux)
              │
              ▼
    PlatformResolver::resolve()
      │                    │
      │ 'auto' -> detect   │
      │ via PHP_OS_FAMILY  │
      ▼                    ▼
 ┌─────────────┐   ┌──────────────────────┐
 │  'windows'  │   │       'linux'        │
 │ BatchScript │   │  ShellScriptBuilder  │
 │  Builder +  │   │          +           │
 │  Process    │   │  LinuxShellProcess   │
 │  Executor   │   │      Executor        │
 │  sep: '\\'  │   │       sep: '/'       │
 └─────────────┘   └──────────────────────┘
```

`CourseVideoMerger` and `VideoBatchPlanner` are typed to the interfaces /
take the separator as a constructor parameter — neither one branches on
platform itself; `PlatformResolver` is the only place that does.

## Docker

`Dockerfile` builds one image used by both `docker-compose.yml` services:

- `scraper` — default `CMD ["index.php"]`.
- `video-merge` — overrides `command: ["bin/process.php"]`, sets
  `VIDEO_MERGE_PLATFORM=linux` (baked into the image via `ENV`) and
  `FFMPEG_BIN=/usr/local/bin/ffmpeg` (the binary copied in from the
  `mwader/static-ffmpeg` stage), and bind-mounts host directories for
  source/dest.

See the root `README.md`'s "Video-merge in Docker" section for the
end-to-end verification that was actually run (synthetic clips in,
correct merged duration out) before this was documented as working.

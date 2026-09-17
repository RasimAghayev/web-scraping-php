# web-scraping-php

A small PHP toolkit with two independent parts:

1. **Scraper** (`index.php`) — crawls a course-listing site and dumps the
   results as JSON, using Symfony's HTTP browser + DOM crawler components.
2. **Video merge utility** (`bin/process.php`, `src/`) — a CLI helper for
   stitching downloaded course videos into fewer, longer MP4 files with
   `ffmpeg`. It is unrelated to the scraper and doesn't consume its output
   (see [Video merge utility](#video-merge-utility)).

### Architecture style

Honest label, not an aspirational one ([R112]): the **scraper** is a single
top-to-bottom script (`index.php`) — there's no layering to name. The
**video-merge utility** is **Layered / Clean-Architecture-inspired**:
`Domain/Video` (pure planning/rules, no I/O), `Infrastructure/*`
(filesystem, ffmpeg, process-execution, metadata — each behind a narrow
class or interface), `Application/CourseVideoMerger` (orchestration only),
`Support` (framework-free helpers). `Infrastructure/Process` additionally
uses interface-based dependency inversion (`ProcessExecutorInterface`,
`BatchScriptBuilderInterface`) so `Application` can run against either the
Windows or Linux execution pair without knowing which — see
[docs/architecture.md](docs/architecture.md) for the full breakdown. It is
**not** DDD (no entities/aggregates with enforced invariants, no ubiquitous
language modeling), **not** CQRS (no command/query split), and **not**
event-driven (no events/message bus) — those labels would describe an
ambition the code doesn't have, not the code itself.

## Requirements

- PHP >= 8.0 (the code already used PHP 8-only syntax — union return types,
  named arguments — before this was made explicit in `composer.json`)
- [Composer](https://getcomposer.org/)
- `ext-json`
- `ffmpeg`, for the video merge utility only (external binary, not a
  Composer package)

## Installation

```bash
composer install
```

This installs the dependencies declared in `composer.json`:

| Package | Version | Used for |
|---|---|---|
| `symfony/browser-kit` | ^6.4 | HTTP browser + form/link crawling (scraper) |
| `symfony/http-client` | ^6.4 | HTTP transport underlying browser-kit |
| `symfony/css-selector` | ^6.4 | CSS-selector support for DOM crawling |
| `james-heinrich/getid3` | ^1.9 | Video duration probing (video merge utility) |
| `phpunit/phpunit` (dev) | ^10.5 | Test suite |
| `phpstan/phpstan` (dev) | ^1.11 | Static analysis (`vendor/bin/phpstan analyse`) |

> **PHP 8.1 ceiling, and why `composer.json` briefly said otherwise**: this
> repo targets PHP >= 8.0 in principle but PHP 8.1 in practice (its own
> `phpunit.xml.dist`/CI-equivalent runs on 8.1). Renovate had auto-merged
> two bumps — `symfony/browser-kit` to `^8.0` and `phpunit/phpunit` to
> `^13.0` — that both silently require PHP >= 8.4, breaking
> `composer install` on 8.1 (verified: 23 "requires php >=8.4" resolution
> failures). Fixed in TASK-007 by pinning back to the latest majors that
> actually support 8.1 (`symfony/browser-kit ^6.4`, matching its
> `http-client`/`css-selector` siblings; `phpunit/phpunit ^10.5`) — not a
> revert of "newer is better", a correction of two merges that never
> should have gone through un-gated for this project's real PHP floor.

> **Note on `fabpot/goutte`**: this repo previously depended on it, but
> Goutte is abandoned upstream and its `Client` class was itself just a
> deprecated subclass of `Symfony\Component\BrowserKit\HttpBrowser` —
> so it's been replaced with that same underlying component directly.
> `index.php`'s scraping logic is unchanged; only the HTTP client wrapper
> around it is different. Likewise `guzzlehttp/guzzle` was a leftover
> direct dependency of the old Goutte-based setup that nothing in this
> repo ever imported directly — dropped.

## Scraper

### What it does

`index.php` requests a fixed listing page —
`https://downloadly.ir/download/elearning/video-tutorials/` (overridable via
the `SCRAPE_URL` environment variable) — and:

1. Reads the pagination block for a list of "next page" links (`next_link`
   in the output), via the CSS selector `.pagination navigation`.
   **Verified against the live site**: this selector matches nothing —
   `next_link` always comes back empty. It targets a `<navigation>` element
   (not a real HTML5 tag) as a descendant of `.pagination`; it likely meant
   `.pagination .navigation` or similar. Left as-is — fixing selector
   *behavior* (as opposed to the surrounding PHP) is a separate change from
   this pass.
2. For every `<article>` on the page, follows its detail link and scrapes
   that page's file list, keeping only links to `.rar` files.
3. Translates the Persian labels/units found on the source site into English
   (`مگابایت`/`گیگابایت` → `MB`/`GB`, `دانلود بخش`/`دانلود` → `Part`/`Download`)
   so the output is readable without a translator.
4. Prints one JSON object to stdout:

```json
{
  "next_link": [],
  "running_link": [
    {
      "description": "...",
      "link": "https://downloadly.ir/...",
      "file_list": [["Part 1 – 1 GB", "https://.../file.rar"], "..."]
    }
  ]
}
```

(Real sample output, one real page: 33 articles, `next_link: []` — see point
1 above.)

Errors (network failures, etc.) are reported on stderr with a non-zero exit
code, so stdout only ever contains valid JSON or nothing — safe to pipe to a
file.

### Running it

```bash
php index.php > output.json
```

`max_execution_time` is raised to 3600s (1 hour) inside the script itself,
since a full crawl of every article's detail page can take a while.

A second target was previously left as a commented-out alternative URL in
the source (`https://downloadly.ir/tag/easy-Learning/`) — set `SCRAPE_URL`
to that (or any other listing page on the same site) instead of editing
code:

```bash
SCRAPE_URL="https://downloadly.ir/tag/easy-Learning/" php index.php > output.json
```

### Notes / limitations

- The target site's markup can change at any time; the selectors in
  `index.php` (`.pagination navigation`, `article`, `//p/a`) reflect the
  live site as of this pass and aren't guaranteed to keep matching.
- No retry/backoff, no rate limiting — a network hiccup partway through
  exits non-zero rather than resuming.

## Video merge utility

`bin/process.php` (backed by `src/`) is a **separate CLI tool** — it is not
part of the scraper pipeline and doesn't consume its JSON output.
`video_merge.php` and `function.php` still exist as thin, deprecated
compatibility shims over the same `src/` classes (see their own file
headers); new usage should go through `bin/process.php` directly.

**CLI only.** The tool refuses to run under a web server (checks
`PHP_SAPI`) and exits immediately if it detects one — the original version
was a bare, unauthenticated HTML `<form method="post">` that, on submit,
shelled out to a dynamically generated batch file; if this script were ever
reachable over HTTP, that was an unauthenticated remote-code-execution
path. Run it from a terminal instead:

```bash
php bin/process.php
```

### What it does

Given a folder of downloaded course videos, it will:

1. Recursively list every video file (`mp4`, `mov`, `f4v`, `mkv`, `avi`,
   `wmv`, `mpg`, `flv`, `webm`, `m4v` by default — see `VIDEO_EXTENSIONS`
   below) under a source directory.
2. Read each file's duration with **getID3** (`james-heinrich/getid3`, a
   real, versioned Composer dependency).
3. Generate an `ffmpeg concat` file list and a staging/concat script.
4. Run that script, which shells out to `ffmpeg` to re-encode each clip
   and then concatenate them into merged MP4 output(s), splitting into
   multiple files if the combined runtime would exceed roughly 12 hours.

Step 3/4's script is either a Windows `.bat` (via `cmd.exe`) or a POSIX
`.sh` (via `sh`) — see [Execution platform](#execution-platform) below;
same ffmpeg command content either way, only the scripting wrapper differs.

### Configuration

Every value below has a working default (the original author's own,
Windows-only machine layout) and is overridable via environment variable —
no source edit required:

| Variable | Default | Meaning |
|---|---|---|
| `VIDEO_SOURCE_DIR` | `D:\Video\zip\` | Where downloaded videos are read from |
| `VIDEO_DEST_DIR` | `D:\Video\Dn\` | Where merged output is written |
| `VIDEO_FOLDERS` | `MSK JavaScript Bootcamp` | Comma-separated subfolder names to process |
| `FFMPEG_BIN` | `E:\DevOps\...\ffmpeg.exe` | Path to the `ffmpeg` binary |
| `VIDEO_CODEC_ARGS` | `-c:v libx264 -s 1920x1080 -r 30 -b:v 2M` | ffmpeg video re-encode flags |
| `AUDIO_CODEC_ARGS` | `-c:a aac -ac 2 -ar 44100 -b:a 192k` | ffmpeg audio re-encode flags |
| `LEGACY_PATH_PREFIX` | `D:\IDM\IDM2\tt\` | A leftover machine-specific prefix stripped from manifest/ffmpeg paths — see `RenameManifestWriter`'s docblock for why the default can't be "corrected" |
| `VIDEO_EXTENSIONS` | `mp4,mov,f4v,mkv,avi,wmv,mpg,flv,webm,m4v` | Recognized video extensions (case-insensitive) |
| `VIDEO_MERGE_PLATFORM` | `auto` | `auto` \| `windows` \| `linux` — which execution pipeline to use, see below |

Full table with which class reads each value: `docs/architecture.md`.

Example (bare metal, Windows):

```bash
VIDEO_SOURCE_DIR="D:\Downloads\" VIDEO_DEST_DIR="D:\Merged\" \
VIDEO_FOLDERS="Course A,Course B" FFMPEG_BIN="C:\ffmpeg\bin\ffmpeg.exe" \
php bin/process.php
```

### Execution platform

Two execution pipelines exist side by side, selected by `VIDEO_MERGE_PLATFORM`
(`config/video.php`'s `platform` key):

- **`windows`** (the tool's original design, still the default when run
  directly on a Windows host): `BatchScriptBuilder` generates a `.bat`
  script; `ProcessExecutor` runs it via `system('cmd /c ' . $path)`.
- **`linux`** (added in TASK-007, and what the Docker image below uses):
  `ShellScriptBuilder` generates a POSIX `.sh` script (same ffmpeg command
  content, no `cmd.exe`-specific staging); `LinuxShellProcessExecutor` runs
  it via `system('sh ' . escapeshellarg($path))`. Path-building
  (`VideoBatchPlanner`, `CourseVideoMerger`) also switches from `\` to `/`
  under this mode, since a literal backslash is just an ordinary filename
  character on Linux, not a separator.
- **`auto`** (the config default) detects via `PHP_OS_FAMILY`.

Selection happens in one place — `Infrastructure\Process\PlatformResolver`
— everything downstream (`CourseVideoMerger`) just uses whichever
interface implementation (`ProcessExecutorInterface`,
`BatchScriptBuilderInterface`) comes back.

### Known limitations (flagged, not fixed in this pass)

- **No `ffmpeg` flag/codec portability beyond `VIDEO_CODEC_ARGS`/
  `AUDIO_CODEC_ARGS`** — those two env vars cover the re-encode step;
  every other ffmpeg flag (concat/segment output specs in
  `FfmpegCommandBuilder`) is still a fixed literal.
- **The Windows `mkdir`/`move`/`rename` preamble in `BatchScriptBuilder`
  is dead code** on the current file-naming scheme (verified: it targets
  patterns no file `VideoBatchPlanner::plan()` produces actually matches)
  — left exactly as the original had it (still byte-identical for the
  Windows path), not fixed, since "fix a dead branch nobody's relying on"
  and "preserve this exact tool's behavior" are in tension and this pass
  chose the latter for the Windows path specifically. `ShellScriptBuilder`
  (the new Linux path) simply doesn't reproduce it.

## Docker

Two services: `scraper` and `video-merge`.

```bash
docker compose build
docker compose run --rm scraper > output.json
```

Override the target page the same way as bare-metal, via env (`.env`, copy
from `.env.example`, or inline):

```bash
SCRAPE_URL="https://downloadly.ir/tag/easy-Learning/" docker compose run --rm scraper > output.json
```

**Verified**: a real `docker compose build` + `docker compose run` against
the live site returned valid JSON — 33 articles, `next_link: []` — the same
signature as the bare-metal run documented above.

### Video-merge in Docker

TASK-007 made this real, where it was previously excluded entirely (the
tool's only hard Windows dependencies were an `ffmpeg.exe` binary at a
fixed path, and shelling out to `cmd.exe` — both now have a real Linux
counterpart, see [Execution platform](#execution-platform) above). The
image obtains `ffmpeg` from a **dedicated ffmpeg image**
(`mwader/static-ffmpeg`), copied in via multi-stage `COPY --from=` exactly
like `vendor/` is copied from the composer stage — not `apk`-installed,
not manually downloaded.

```bash
mkdir -p data/video-source/my-course data/video-dest
# ...copy your course's video files into data/video-source/my-course...
VIDEO_MERGE_FOLDERS=my-course docker compose run --rm video-merge
# merged output appears under data/video-dest/
```

**Verified for real**, not just built: generated two short synthetic clips
with `ffmpeg`'s own `lavfi` test sources (2s + 3s), ran the containerized
tool against them end-to-end, and confirmed the final merged output's
duration (`ffprobe`: 5.06s) matches the sum of the inputs — the full
re-encode + concat pipeline, sourcing `ffmpeg` from the dedicated image,
actually works inside the container.

The `scraper` and `video-merge` services build from the same `Dockerfile`
(single codebase); `video-merge` overrides `command` to run
`bin/process.php` instead of the default `index.php`.

### Resource limits

`docker-compose.yml` caps `scraper` at 0.5 CPU / 128MB memory / 50 PIDs and
`video-merge` at 2.0 CPU / 2GB memory / 100 PIDs (R60/R64 — video
re-encoding is genuinely heavier than one HTTP GET), both with
`no-new-privileges`. `scraper` declares no bind mounts (stateless: remote
URL in, JSON to stdout). `video-merge` binds two host directories
(`VIDEO_MERGE_SOURCE_DIR`/`VIDEO_MERGE_DEST_DIR`, see `.env.example`) since
it genuinely reads and writes files on disk.

## License

Proprietary (see `composer.json`).

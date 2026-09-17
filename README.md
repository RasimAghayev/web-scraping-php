# web-scraping-php

A small PHP toolkit with two independent parts:

1. **Scraper** (`index.php`) — crawls a course-listing site and dumps the
   results as JSON, using Symfony's HTTP browser + DOM crawler components.
2. **Video merge utility** (`video_merge.php` + `function.php`) — a local,
   machine-specific CLI helper for stitching downloaded course videos into
   fewer, longer MP4 files with `ffmpeg`. It is unrelated to the scraper and
   is not portable out of the box (see [Video merge utility](#video-merge-utility)).

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

`video_merge.php` and `function.php` are a **separate, local automation
tool** for the repo owner's own machine — they are not part of the scraper
pipeline and don't consume its JSON output.

**CLI only.** `video_merge.php` refuses to run under a web server (checks
`PHP_SAPI`) and exits immediately if it detects one. The original version
was a bare, unauthenticated HTML `<form method="post">` that, on submit,
shelled out to a dynamically generated `.bat` file — if this script were
ever reachable over HTTP, that was an unauthenticated remote-code-execution
path. Run it from a terminal instead:

```bash
php video_merge.php
```

### What it does

Given a folder of downloaded course videos, it will:

1. Recursively list every video file (`mp4`, `mov`, `f4v`, `mkv`, `avi`,
   `wmv`, `mpg`, `flv`, `webm`, `m4v`) under a source directory.
2. Read each file's duration with **getID3** (now a real, versioned
   Composer dependency — `james-heinrich/getid3` — instead of an
   undocumented manual copy expected at a hardcoded path).
3. Generate an `ffmpeg concat` file list and a Windows batch (`.bat`) script.
4. Run that batch script (via PHP's `system()`), which shells out to
   `ffmpeg` to concatenate the clips into merged MP4 output(s), splitting
   into multiple files if the combined runtime would exceed roughly 12
   hours.

### Configuration

Still defaults to the original author's own machine layout, but every path
is now overridable via environment variables instead of requiring a source
edit:

| Variable | Default | Meaning |
|---|---|---|
| `VIDEO_SOURCE_DIR` | `D:\Video\zip\` | Where downloaded videos are read from |
| `VIDEO_DEST_DIR` | `D:\Video\Dn\` | Where merged output is written |
| `VIDEO_FOLDERS` | `MSK JavaScript Bootcamp` | Comma-separated subfolder names to process |
| `FFMPEG_BIN` | `E:\DevOps\OpenServer\domains\videomerge.azp\old\ffmpeg\bin\ffmpeg.exe` | Path to `ffmpeg.exe` |

Example:

```bash
VIDEO_SOURCE_DIR="D:\Downloads\" VIDEO_DEST_DIR="D:\Merged\" \
VIDEO_FOLDERS="Course A,Course B" FFMPEG_BIN="C:\ffmpeg\bin\ffmpeg.exe" \
php video_merge.php
```

### Known limitations (flagged, not fixed in this pass)

- **Still Windows-shaped**: paths, the generated `.bat` script, and
  `system()`-driven execution all assume Windows even after
  parameterization — only *where* things point is configurable now, not
  the OS assumptions baked into the generated commands.
- **`getDirContents()` in `function.php`** returns either an array or the
  string `'Qovluq yoxdur--'` depending on whether the source directory
  exists; every caller assumes an array. A missing/misconfigured
  `VIDEO_SOURCE_DIR` produces a PHP warning (`foreach() argument must be
  of type array|object, string given`) rather than a clean error message.
  Left as-is — deciding what should happen instead (throw? return `[]`?
  a clear CLI error?) is a real behavior decision, not something to guess
  at silently.
- **`file_write()` in `function.php`** strips a hardcoded prefix,
  `D:\IDM\IDM2\tt\`, from filenames — a different absolute path than the
  configurable source/dest directories above. This looks like leftover
  state from a previous machine layout; the "correct" value can't be
  inferred, so it's left untouched.
- No `ffmpeg` version/flag portability beyond what's baked into
  `function.php` (codec, resolution, bitrate are hardcoded there).

## License

Proprietary (see `composer.json`).

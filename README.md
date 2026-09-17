# web-scraping-php

A small PHP toolkit with two independent parts:

1. **Scraper** (`index.php`) — crawls a course-listing site with
   [Goutte](https://github.com/FriendsOfPHP/Goutte) and dumps the results as
   JSON.
2. **Video merge utility** (`video_merge.php` + `function.php`) — a local,
   machine-specific helper for stitching downloaded course videos into fewer,
   longer MP4 files with `ffmpeg`. It is unrelated to the scraper and is not
   portable out of the box (see [Video merge utility](#video-merge-utility)).

## Requirements

- PHP >= 7.4
- [Composer](https://getcomposer.org/)
- `ext-json`

## Installation

```bash
composer install
```

This installs the two runtime dependencies declared in `composer.json`:

| Package | Version | Used for |
|---|---|---|
| `fabpot/goutte` | ^4.0 | HTTP client + DOM crawler (scraper) |
| `guzzlehttp/guzzle` | ^7.4 | HTTP transport underlying Goutte |

## Scraper

### What it does

`index.php` requests a fixed listing page —
`https://downloadly.ir/download/elearning/video-tutorials/` — and:

1. Reads the pagination block for a list of "next page" links (`next_link`
   in the output), via the CSS selector `.pagination navigation`. Note: as
   written, that selector looks for a `<navigation>` element (not a real
   HTML5 tag) descending from `.pagination`, rather than `.pagination
   .navigation` — this may be a typo that leaves `next_link` empty against
   real markup, but that wasn't verified against the live site for this
   documentation pass; flagging it rather than asserting it as confirmed
   behavior. Left as-is either way — not part of this pass's scope.
2. For every `<article>` on the page, follows its detail link and scrapes
   that page's file list, keeping only links to `.rar` files.
3. Translates the Persian labels/units found on the source site into English
   (`مگابایت`/`گیگابایت` → `MB`/`GB`, `دانلود بخش`/`دانلود` → `Part`/`Download`)
   so the output is readable without a translator.
4. Prints one JSON object to stdout:

```json
{
  "next_link": ["..."],
  "running_link": [
    {
      "description": "...",
      "link": "https://downloadly.ir/...",
      "file_list": [["Part 1 - 1.2GB", "https://.../file.rar"], "..."]
    }
  ]
}
```

There's no CLI flag handling or separate output file — the script just runs
and writes JSON to stdout.

### Running it

```bash
php index.php > output.json
```

`max_execution_time` is raised to 3600s (1 hour) inside the script itself,
since a full crawl of every article's detail page can take a while.

A second target URL is present in the source as a commented-out line
(`https://downloadly.ir/tag/easy-Learning/`) — swap it in manually if you
want to scrape that section instead; there's no environment variable or
config file for this yet.

### Notes / limitations

- The target site's markup can change at any time; the CSS/XPath selectors
  in `index.php` (`.pagination navigation`, `article`, `//p/a`) are scraped
  from the live site as of when this was written and are not guaranteed to
  keep matching.
- No retry/backoff, no rate limiting, no error handling around failed
  requests — a network hiccup partway through will throw rather than resume.

## Video merge utility

`video_merge.php` and `function.php` are a **separate, local automation
tool** for the repo owner's own machine — they are not part of the scraper
pipeline and don't consume its JSON output. Documenting them here for
completeness, with their real constraints, rather than silently omitting
them or implying they're portable.

### What it does

Given a folder of downloaded course videos, it will:

1. Recursively list every video file (`mp4`, `mov`, `f4v`, `mkv`, `avi`,
   `wmv`, `mpg`, `flv`, `webm`, `m4v`) under a source directory.
2. Read each file's duration with **getID3**.
3. Generate an `ffmpeg concat` file list and a Windows batch (`.bat`) script.
4. Run that batch script (via PHP's `system()`), which shells out to
   `ffmpeg` to concatenate the clips into merged MP4 output(s), splitting
   into multiple files if the combined runtime would exceed roughly 12
   hours.

### Known limitations (not fixed here — out of scope for this README pass)

This script only runs as-is on the original author's own Windows machine:

- **Source/destination folders are hardcoded**: `D:\Video\zip\` and
  `D:\Video\Dn\` in `video_merge.php`.
- **The folder(s) to process are hardcoded**: `$dir1s = ['MSK JavaScript
  Bootcamp']`.
- **The `ffmpeg` path is hardcoded to one machine's install location**:
  `E:\DevOps\OpenServer\domains\videomerge.azp\old\ffmpeg\bin\ffmpeg.exe`
  (`function.php`).
- **getID3 is a manual dependency, not a Composer package.** The code does
  `include_once('getID3/getid3/getid3.php')`, but `getID3` is not in
  `composer.json` and is not vendored in this repo — you need to download
  [getID3](https://www.getid3.org/) yourself and place it at that exact
  path for `video_merge.php` to run.
- No `ffmpeg` version/flags portability beyond what's baked into
  `function.php`.

If you want to reuse the video-merge half elsewhere, treat the hardcoded
paths and the getID3 dependency as required manual setup, not bugs — they
were intentionally out of scope for this documentation pass, which covers
only what the code does and how to install/run it, not restructuring it for
portability.

## License

Proprietary (see `composer.json`).

# Development

Added in TASK-007 alongside `docs/architecture.md` — day-to-day commands
for working on this repo. Not a tutorial on the tools themselves, just
what this repo's own config expects.

## Install

```bash
composer install
```

PHP >= 8.1 (see the README's note on the PHP 8.1 ceiling and the two
Renovate bumps that briefly broke it).

## Tests

```bash
vendor/bin/phpunit
```

`phpunit.xml.dist` picks up everything under `tests/Unit` and
`tests/Integration`. As of TASK-007: 54 tests, all passing, PHP 8.1.9 /
PHPUnit 10.5.64 — run for real before every commit in this task, not just
asserted.

Run one file or one test:

```bash
vendor/bin/phpunit tests/Unit/ShellScriptBuilderTest.php
vendor/bin/phpunit --filter testLinuxSeparatorProducesRealSlashSeparatedPathsAndSlugs
```

## Static analysis

```bash
vendor/bin/phpstan analyse
```

Added in TASK-007 (`phpstan.neon`, level 5, scoped to `src/` and `bin/` —
see that file's own comment for why `function.php`/`index.php`/
`video_merge.php` are excluded). `phpstan-baseline.neon` holds exactly one
pre-existing finding (a dead-code branch in `DirectoryScanner::scan()`,
documented in the baseline file itself) carried over from before PHPStan
existed in this repo — **new findings should be fixed, not added to the
baseline.**

## Video-merge, without touching real files

`bin/process.php` renames source files in place (`FileOperations::renameAll()`)
and shells out to `ffmpeg` — don't point it at real course footage while
developing. Two safer options:

- **Unit/integration tests** (`tests/Unit/VideoBatchPlannerTest.php`,
  `tests/Integration/*`) exercise the planning/manifest logic without
  running `ffmpeg` or touching real video files.
- **Docker, with synthetic clips** — the same approach used to verify the
  Linux pipeline for TASK-007:

  ```bash
  docker compose build
  mkdir -p /tmp/vm-source/testcourse /tmp/vm-dest
  docker run --rm -v /tmp/vm-source/testcourse:/out mwader/static-ffmpeg:7.1 \
    -f lavfi -i testsrc=duration=2:size=320x240:rate=15 \
    -f lavfi -i sine=frequency=440:duration=2 \
    -c:v libx264 -c:a aac -shortest -y /out/1-intro.mp4

  docker run --rm \
    -v /tmp/vm-source:/data/source -v /tmp/vm-dest:/data/dest \
    -e VIDEO_SOURCE_DIR=/data/source/ -e VIDEO_DEST_DIR=/data/dest/ \
    -e VIDEO_FOLDERS=testcourse \
    web-scraping-php bin/process.php
  ```

  `lavfi`'s `testsrc`/`sine` generate a real, valid, short video with no
  input file of your own needed — the whole loop (reencode -> concat ->
  merged output) runs against disposable data.

## Docker (scraper)

```bash
docker compose build
docker compose run --rm scraper > output.json
```

No synthetic-data concern here — the scraper only reads a remote URL and
writes JSON to stdout, nothing on disk to worry about accidentally
touching.

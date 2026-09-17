# `function.php` / `video_merge.php` refactor — analysis, design, and verification

This document is the full record of the behavior-preserving refactor of the
video-merge utility (TASK-004, following TASK-002/TASK-003's documentation
and dependency passes — see `.sdd/projects/web-scraping-php.sdd`). It
follows the required 10-section output format.

> This is the **history** of how `src/` got its current shape, kept for
> the byte-identical-behavior verification detail. For the **current**
> structure/configuration/platform-selection reference, see
> `docs/architecture.md`; for day-to-day commands, `docs/development.md`.
> Both added in TASK-007.

## 1. Current Code Analysis

**What the tool does**: a local, single-machine CLI utility that takes a
list of course folder names, and for each one: scans its video files,
reads each file's duration via getID3, plans new sequential filenames,
generates an ffmpeg re-encode command per file, generates a Windows
cmd.exe batch script that stages the renamed files and runs one final
ffmpeg **concat** invocation (producing either one merged file or several
~12-hour segments), writes a YouTube-style chapter-index text file,
physically renames the source files, then executes the generated batch
script via `system()`.

**Execution flow** (`video_merge.php` → `function.php`):
1. `video_merge.php` requires getID3 and `function.php`, then calls
   `runProccess($folders, $sourceDir, $destDir)`.
2. For each folder name: slug it (`post_slug`), create the destination
   directory, recursively scan the source folder (`getDirContents`),
   filter to video files and build the rename/duration plan
   (`file_listed`), decide single-file vs. multi-segment output based on
   total duration, build the cmd.exe batch script text and the ffmpeg
   re-encode commands (`file_write`), write the chapter-index and old/new
   manifest text files, physically rename the files (`action_file`), then
   run the batch (`system()`).

**Responsibilities identified per function** (all in the original,
un-refactored `function.php`):
- `getDirContents()` — recursive filesystem scan (mixes scanning with a
  return-type inconsistency, see below).
- `post_slug()` — Cyrillic/Serbian transliteration + slug generation
  (string logic only).
- `file_listed()` — video-extension filtering, getID3 duration probing,
  cumulative duration tracking, AND filename/slug generation, all in one
  function.
- `time_elapsed_A()` — pure duration formatting.
- `file_write()` — manifest file writing, ffmpeg command-string
  construction, AND hardcoded video/audio codec settings, all mixed
  together.
- `action_file()` — file copy/rename/unlink, with `die()` on failure.
- `runProccess()` — the orchestrator: also directly builds the cmd.exe
  batch script text, the ffmpeg concat command, the YouTube chapter
  description, calls `system()`, and echoes HTML-flavored debug output —
  at least six distinct responsibilities in one ~60-line function.

**Dependencies**: `getID3` (global, unnamespaced class from
`james-heinrich/getid3`), the filesystem (`scandir`, `realpath`, `copy`,
`rename`, `unlink`, `mkdir`, `file_put_contents`), `system()` (shells out
to cmd.exe, which itself shells out to ffmpeg).

**Global state**: `$list_video_filename` (defined at the top of the
original file, confirmed **unused** anywhere in that file — dead code).

**Hardcoded configuration** (all in `file_write()` / `runProccess()`):
the ffmpeg binary absolute path (two separate hardcoded occurrences),
video codec flags (`-c:v libx264 -s 1920x1080 -r 30 -b:v 2M`), audio codec
flags (`-c:a aac -ac 2 -ar 44100 -b:a 192k`), and (in `video_merge.php`)
the source directory, destination directory, and the folder list.

**Code smells**: functions with 4-6 responsibilities each; `die()` used
for both real errors and (implicitly, via its exit-code-0 default) as a
soft "stop" signal; a dead `$list_video_filename` global; a dead
`is_array()` guard (see below); HTML (`<br>`, `<pre>`) mixed into what is
now a CLI tool's output; deeply nested string concatenation for both
ffmpeg commands and cmd.exe scripting in the same function.

**Risks identified going into the refactor**: any change to the
Cyrillic/Latin transliteration table's *order*, the regex escaping in
`post_slug()`, the `$twelve`/segment-count arithmetic, or the ffmpeg
flag order/spacing would change real output for real files. All of these
were preserved byte-for-byte (see §9).

## 2. Problems / Code Smells (see also §1)

1. **Dead code, not a bug**: `$list_video_filename` was never read.
2. **Dead guard, not fixable without a real behavior decision**: in PHP 8,
   `getDirContents()` returning the string `'Qovluq yoxdur--'` for a
   missing directory doesn't reach `runProccess()`'s
   `if (!is_array($getarray2))` check the way it looks like it should —
   `file_listed()`'s own `foreach` on that string just warns and iterates
   zero times, so `$getarray2` is always an array by the time the guard
   runs. A missing source directory silently proceeds through the whole
   pipeline with an empty file list instead of stopping with the
   "no video files" message. **Verified by actually running the original
   tool against a missing directory** (not assumed) — it printed
   ffmpeg's "cannot find the path" error and still logged "Finished this
   directory". Flagged as a real, pre-existing bug candidate; not fixed,
   per this refactor's behavior-preservation mandate.
3. **Regex character-class range bug** in `post_slug()`:
   `/[^A-Za-z0-9 -.]/` has an unescaped hyphen between a space and a
   period, forming the byte range 0x20-0x2E instead of a literal
   "space, hyphen, period" allowlist — so punctuation like `! " # $ % &
   ' ( ) *` also survives that step (mopped up by the second regex
   afterward in most cases). Left as-is.
4. **Duplicate transliteration keys**: `ш`/`Ш`, `ч`/`Ч`, `ж`/`Ж` each
   appear twice in the Cyrillic table (once in a Serbian/Macedonian
   block, once in a Russian block). `str_replace()`'s left-to-right,
   already-partially-rewritten-string semantics mean the **first**
   occurrence wins — so these letters become the diacritic Latin
   characters `š/č/ž`, not the Russian block's intended "sh/ch/zh". Since
   those diacritics are multi-byte UTF-8 and the following regex is
   byte-wise, the letter is then stripped entirely rather than
   transliterated. **Discovered and confirmed empirically** while writing
   this refactor's tests (`Slugger::slug('школа') === 'kola'`, not
   `'shkola'`) — not a hypothesis, a measured fact. Left as-is.
5. **`die()` for control flow**: `action_file()` and the original's
   implicit reliance on `die()`'s exit-code-0-for-a-string-argument
   behavior meant a hard failure and a normal exit were indistinguishable
   to anything checking the process exit code.
6. **Modulo-24 duration wrap** in `time_elapsed_A()`:
   `fmod($seconds/3600, 24)` wraps a 24h+ duration's hour component back
   to 0. Only affects the human-readable chapter-index text, not ffmpeg
   processing (which uses its own independent timestamp table). Left
   as-is.
7. **Off-by-one at exact segment-budget multiples**: `$twelve` (the
   duration÷budget ratio) is compared with `<=` in a `for` loop, so a
   duration landing exactly on a multiple of the ~12h budget produces one
   extra output segment versus a duration just under it. Left as-is.

None of the above were "fixed" — each is preserved and now has a
docblock at its new home explaining exactly what it does and why it was
left alone (search each `src/` file for "PRESERVED QUIRK").

## 3. Refactoring Strategy

SOLID/KISS/YAGNI/DRY, no forced patterns. One class per genuinely distinct
responsibility identified in §1, each taking plain arrays/scalars in and
out — no DTOs, no interfaces, no factories, no DI container (the whole
object graph is 6 small `new` calls in `bin/process.php`). The original's
data shape (an array with `old`/`new`/`new1`/`duration_sec`/
`duration_sec1`/`duration_time` keys) is kept as-is rather than wrapped in
a value object — every consumer already agrees on it, and a wrapper class
would be ceremony with no behavior to attach.

## 4. Final Directory Structure

```
src/
├── Application/
│   └── CourseVideoMerger.php          (orchestrator — was runProccess())
├── Domain/
│   └── Video/
│       ├── VideoExtension.php          (extension matching)
│       ├── VideoBatchPlanner.php       (was file_listed())
│       ├── OutputSegmentPlanner.php    (segment-count/timestamp math)
│       └── ChapterDescriptionBuilder.php (YouTube chapter text)
├── Infrastructure/
│   ├── FileSystem/
│   │   ├── DirectoryScanner.php        (was getDirContents())
│   │   ├── FileOperations.php          (was action_file())
│   │   └── RenameManifestWriter.php    (manifest .txt writing)
│   ├── Ffmpeg/
│   │   └── FfmpegCommandBuilder.php    (pure command-string construction)
│   ├── Metadata/
│   │   └── GetId3DurationReader.php    (wraps getID3)
│   └── Process/
│       ├── BatchScriptBuilder.php      (cmd.exe script text)
│       └── ProcessExecutor.php         (system() wrapper)
└── Support/
    ├── Slugger.php                     (was post_slug())
    └── DurationFormatter.php           (was time_elapsed_A())

config/video.php     — all env-overridable settings, original defaults preserved
bin/process.php      — new CLI entry point
function.php         — DEPRECATED thin compatibility layer (global functions)
video_merge.php      — thin shim: require bin/process.php

tests/
├── Unit/            — 7 test classes, pure logic
└── Integration/      — 2 test classes, real filesystem
```

No `Repositories`, `Events`, `Commands`, or DTO layer — none of them
would replace real duplication or hide real complexity here; adding them
would be exactly the over-engineering this refactor was told to avoid.

## 5. Responsibility of Each Component

| Class | Responsibility | Was |
|---|---|---|
| `Slugger` | Cyrillic transliteration + slug generation | `post_slug()` |
| `DurationFormatter` | seconds → `HH:MM:SS` | `time_elapsed_A()` |
| `VideoExtension` | is this filename a recognized video? | inline if-chain in `file_listed()` |
| `OutputSegmentPlanner` | single-file vs. N-segment decision, start timestamps | inline `$twelve`/`$time_stmp` math in `runProccess()` |
| `ChapterDescriptionBuilder` | YouTube chapter-index text | `$youtube`-building loop in `runProccess()` |
| `VideoBatchPlanner` | filter to videos, read durations, build the rename plan | `file_listed()` |
| `DirectoryScanner` | recursive file listing | `getDirContents()` |
| `FileOperations` | copy/rename/unlink (throws instead of `die()`) | `action_file()` |
| `RenameManifestWriter` | writes the old-file and concat-list `.txt` manifests | half of `file_write()` |
| `FfmpegCommandBuilder` | pure ffmpeg argument-string construction | half of `file_write()` + inline concat string in `runProccess()` |
| `GetId3DurationReader` | wraps `new getID3()->analyze()` | inline in `file_listed()` |
| `BatchScriptBuilder` | cmd.exe staging + final ffmpeg invocation text | `$convert0` string-building in `runProccess()` |
| `ProcessExecutor` | `system()` call | inline in `runProccess()` |
| `CourseVideoMerger` | orchestration only | `runProccess()` |

## 6. Complete Refactored Code

Every file listed in §4 is real, complete, and committed in this repo —
see `src/`, `config/video.php`, and `bin/process.php`. Reproducing all ~14
files inline here would just duplicate what's already in the repo (and
risk drifting out of sync with it); read the files directly, each one
carries a docblock explaining its origin and any preserved quirk.

## 7. Tests

`tests/Unit/` (pure logic, no I/O): `SluggerTest`, `DurationFormatterTest`,
`VideoExtensionTest`, `OutputSegmentPlannerTest`,
`ChapterDescriptionBuilderTest`, `FfmpegCommandBuilderTest`,
`BatchScriptBuilderTest`.

`tests/Integration/` (real filesystem): `DirectoryScannerTest`,
`RenameManifestWriterTest`.

Run with:
```
vendor/bin/phpunit
```
Result at the time of this refactor: **43 tests, 56 assertions, 0
failures.**

Every "golden" expected value in these tests was obtained by actually
running the corresponding new class and/or the original global function
side-by-side — none were hand-guessed. Two guessed literals from an
earlier draft of `SluggerTest` were caught and corrected this way before
being committed (see §9).

## 8. Migration / Compatibility Notes

- `video_merge.php` still works exactly as before
  (`php video_merge.php`, same `VIDEO_SOURCE_DIR`/`VIDEO_DEST_DIR`/
  `VIDEO_FOLDERS`/`FFMPEG_BIN` env vars, same CLI-only guard) — it now
  just `require`s `bin/process.php`.
- `function.php` still defines every global function it used to
  (`post_slug`, `time_elapsed_A`, `getDirContents`, `file_listed`,
  `file_write`, `action_file`, `runProccess`) and the unused
  `$list_video_filename` global, each now a thin, documented
  `@deprecated` wrapper delegating to the real `src/` classes — nothing
  outside this repo is known to call these directly, but there is no
  reason to break `require 'function.php'; post_slug(...)` for anyone
  who does.
- **One intentional, disclosed behavior change**: `action_file()`'s
  legacy wrapper still `die()`s exactly as before (preserving its
  exit-code-0 quirk for any direct caller), but the **new** entry point
  (`bin/process.php`, and therefore `video_merge.php` since it now
  delegates there) exits with code `1` on a copy/rename failure instead
  of the original's `die()`-always-exits-`0`. Nothing in this repo or
  known to call it inspects that exit code, so this is a low-risk,
  disclosed usability fix, not a silent behavior change — flagged here
  explicitly per "preserve behavior first."
- `james-heinrich/getid3`'s un-namespaced class file still needs an
  explicit `require_once` (Composer generates no autoload map for it);
  both `bin/process.php` and the legacy `function.php` do this.

## 9. Old vs New Behavior Verification

Three independent layers of verification were run, all real executions —
nothing in this section is asserted without having actually been run.

**A. Pure-logic comparison harness** (`Slugger`/`DurationFormatter`/
`VideoExtension`/`OutputSegmentPlanner`/`ChapterDescriptionBuilder`/
`FfmpegCommandBuilder`/`BatchScriptBuilder` vs. hand-reproduced copies of
the original global-function logic, run side-by-side in one PHP process
against the same inputs): **60/60 checks passed**, including:
- 10 slug cases (Cyrillic, punctuation, whitespace, empty string)
- 13 duration-formatting cases (including the 24h wrap and sub-second
  input)
- 10 filename/extension cases
- 10 duration totals for the segment-budget ratio/count, including the
  exact-multiple edge and an ~18-day case exercising the 33-entry
  timestamp table
- 5 chapter-description cases (noise stripping, ordering, the
  hour-past-11 display quirk)
- 2 ffmpeg re-encode command cases (including the legacy-prefix path)
- 3 batch-script cases (single-file, exact-multiple edge, multi-segment)

One apparent mismatch was investigated rather than dismissed:
`OutputSegmentPlanner::budgetRatio()` returns `float` (e.g. `0.0`) where
the original's untyped `$twelve` is sometimes `int` (e.g. `0`) — an
artifact of PHP widening an evenly-divisible int to float across a typed
return. Confirmed by reading every call site that this value is only
ever used in `<`/`<=` numeric comparisons, never echoed or serialized —
so it's behaviorally inert, not a real divergence.

**B. PHPUnit suite**: 43 tests, 56 assertions, 0 failures (see §7).

**C. Real end-to-end run**, old code (restored from commit `11fbb96`,
the pre-refactor state) vs. new code (`bin/process.php`), against two
identical tiny synthetic video files (`ffmpeg -f lavfi -i testsrc`,
2s and 3s) in parallel source trees, using the same real ffmpeg binary
(chocolatey's `ffmpeg.exe` — see the caveat below) for both:
- Generated `bat-<slug>.bat`: **byte-identical** modulo the
  source/dest directory names themselves.
- Generated concat manifest (`<slug>.txt`): **byte-identical** modulo
  directory names.
- Generated YouTube chapter-description text: **byte-identical**.
- Final merged output `.mp4`: **MD5-identical**.
- Both intermediate re-encoded segment `.mp4` files: **MD5-identical**.
- Process exit code: `0` for both.

One real discrepancy surfaced during this comparison and was tracked
down rather than papered over: the first attempt fed the new run's
`VIDEO_SOURCE_DIR` with different filesystem-path letter-casing than the
old run's `__DIR__`-derived path, and `realpath()` (used by
`DirectoryScanner`/`getDirContents()` in both old and new code)
normalizes to the filesystem's actual on-disk casing — so the
chapter-description prefix-strip failed to match by a single letter's
case, and the full path leaked into that one output instead of being
stripped. Re-running with matching casing produced the byte-identical
result above. **This is a pre-existing fragility in the original code's
string-based prefix stripping (present identically in the port, not
introduced by it)**, not a new-vs-old behavior difference — documented
here rather than silently fixed, since "fixing" it would be a behavior
change outside this refactor's scope.

**Caveat, disclosed rather than hidden**: this session's environment has
ffmpeg at `C:\ProgramData\chocolatey\bin\ffmpeg.exe`, confirmed present
and used for all real-execution verification above. An earlier
assumption (recorded before this refactor session's later half) that
ffmpeg also existed at the original hardcoded default path
(`E:\DevOps\OpenServer\domains\videomerge.azp\old\ffmpeg\bin\ffmpeg.exe`)
was **re-checked and found to be false** on this machine at the time of
this verification — that directory does not exist here. This does not
affect the verification above (which used `FFMPEG_BIN` to point both old
and new runs at the same real binary, exactly as the config system is
designed to allow), but the claim from earlier in this refactor is
corrected here rather than left standing uncorrected.

**UNVERIFIED**: multi-course batches (`VIDEO_FOLDERS` with more than one
entry), the >33-segment table-overflow path in `OutputSegmentPlanner`,
and behavior against real (non-synthetic) course video files with actual
audio tracks and getID3-reported metadata beyond `playtime_seconds` —
none of these were exercised by the real end-to-end run above. Nothing
in the code paths for these cases differs in kind from what *was*
verified (same functions, same logic, just more iterations/rows), but
per this task's explicit instruction, they are marked UNVERIFIED rather
than assumed to work.

## 10. Remaining Technical Debt

Carried over from the original, deliberately not fixed in this refactor
(each also documented as a "PRESERVED QUIRK" at its new home in `src/`):

1. A missing source directory silently produces an empty batch instead
   of stopping with a clear message (the `is_array()` guard is dead code
   in PHP 8) — see §2.2.
2. The regex character-class range bug in `Slugger` — see §2.3.
3. Four Cyrillic letters (`ш/Ш`, `ч/Ч`, `ж/Ж`) vanish instead of
   transliterating, due to duplicate table keys — see §2.4.
4. The 24-hour modulo wrap in `DurationFormatter` — see §2.6.
5. The off-by-one segment count at exact budget multiples — see §2.7.
6. `RenameManifestWriter`'s hardcoded `D:\IDM\IDM2\tt\` legacy prefix —
   a different absolute path than the configured source/dest
   directories, looks like leftover state from a previous machine; the
   "correct" value can't be inferred, only guessed.
7. The string-based prefix-stripping fragility surfaced in §9.C: if the
   configured source directory's letter-casing doesn't match the
   filesystem's canonical casing, the chapter-description text leaks the
   full path instead of a clean filename. Pre-existing in the original;
   not introduced or fixed here.
8. `OutputSegmentPlanner`'s 33-entry start-timestamp table has a hard
   ceiling (~16 days); a longer course silently gets an empty-string
   timestamp for any segment past that, same as the original.
9. HTML (`<br>`, `<pre>`) in `CourseVideoMerger`'s stdout — cosmetic,
   left as-is since changing a CLI tool's incidental output format is
   out of scope for a behavior-preserving refactor.

None of these are fixed by this refactor. Each is now isolated in one
small, named, tested class instead of buried in a 60-line function —
which is what makes them each independently fixable later, on purpose,
one at a time, instead of a package deal with the next unrelated change.

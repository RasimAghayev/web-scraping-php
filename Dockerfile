# web-scraping-php — scraper + video-merge image (PHP CLI)
#
# This repo holds TWO independent CLI tools (see README): the scraper
# (index.php, this image's default CMD) and the video-merge utility
# (video_merge.php/function.php/bin/process.php).
#
# TASK-007 supersedes the earlier exclusion of the video-merge tool from
# this image. What changed and why it's now real, not just flagged
# differently:
#   - The tool's ONLY hard Windows dependency was two things: (1) an
#     ffmpeg.exe binary at a hardcoded Windows path, and (2) shelling out
#     to cmd.exe against a generated .bat script
#     (ProcessExecutor::runBatchFile()). (1) is solved below — ffmpeg is
#     now obtained FROM a dedicated ffmpeg image (mwader/static-ffmpeg),
#     copied in via multi-stage COPY --from=, exactly like vendor/ is
#     copied from the composer stage. (2) is solved by
#     LinuxShellProcessExecutor + ShellScriptBuilder, a POSIX-shell
#     execution path parallel to the Windows one, selected at runtime via
#     PlatformResolver (config/video.php's `platform` key, defaulted to
#     `linux` in THIS image via the ENV below — see docker-compose.yml's
#     `video-merge` service).
#   - What's still Windows-flavored and stays that way on purpose: the
#     Windows execution path (BatchScriptBuilder/ProcessExecutor,
#     cmd.exe) is unchanged and still the default OUTSIDE this image
#     (config/video.php's `platform` defaults to `auto`, which detects
#     Windows via PHP_OS_FAMILY when run directly on a Windows host,
#     matching the original tool's native environment) — this image
#     doesn't replace that, it adds a second, real way to run the same
#     tool.
#
# R91/R92/R102: multi-stage (composer-managed vendor/ is a real build
# artifact here, unlike rest-api-mvc-php's custom autoloader — see the
# vendor stage below), non-root USER, no secrets baked in, resource
# limits declared at the compose level (R60/R64).
#
# HEALTHCHECK (R91 checklist item) deliberately omitted, flagged rather
# than faked: the default CMD is index.php, a one-shot script that
# scrapes once and exits (see README "Running it"); the video-merge
# service (docker-compose.yml) is the same kind of one-shot batch job,
# not a long-running server. Docker's HEALTHCHECK polls a container on an
# interval, but both containers have already exited by the time any
# meaningful interval elapses — there is no long-running process for a
# healthcheck to observe. Adding one would either never fire (container
# gone) or require inventing a fake always-up process, which is worse
# than having none.

# syntax=docker/dockerfile:1

ARG PHP_IMAGE=php:8.1-cli-alpine3.19
# Tag-pinned, not digest-pinned, matching the same disclosed limitation as
# rest-api-mvc-php/Dockerfile: this build environment has no live registry
# access to resolve today's sha256. Pin explicitly before production use:
#   docker inspect --format='{{index .RepoDigests 0}}' php:8.1-cli-alpine3.19
# and set PHP_IMAGE=php:8.1-cli-alpine3.19@sha256:<digest>.
# Same PHP minor/Alpine version as rest-api-mvc-php's image, deliberately —
# one base version across the portfolio's PHP repos instead of a new one
# per repo.

# TASK-007: statically-built ffmpeg, obtained from a dedicated image
# rather than apk-installed or manually downloaded — mwader/static-ffmpeg
# is a widely-used, scratch-based image whose entire purpose is being
# COPY --from='d in a multi-stage build (verified for real: pulled,
# ran `/ffmpeg -version` standalone, confirmed libx264/aac support —
# both codecs config/video.php's default video_codec/audio_codec need).
# Same tag-pinning disclosure as PHP_IMAGE above (no digest available in
# this build environment).
ARG FFMPEG_IMAGE=mwader/static-ffmpeg:9.0

FROM ${FFMPEG_IMAGE} AS ffmpeg

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# --no-dev: phpunit (require-dev) has no reason to ship in the runtime
# image. --no-scripts: composer.json declares no scripts, but skipping
# script execution during a vendor-only install stage is the safer
# default regardless. --ignore-platform-reqs: the composer:2 image's own
# PHP version does not need to match the runtime stage's PHP_IMAGE for a
# pure-PHP dependency resolution step.
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader --ignore-platform-reqs

FROM ${PHP_IMAGE} AS runtime
LABEL org.opencontainers.image.title="web-scraping-php"
LABEL org.opencontainers.image.description="PHP scraper (downloadly.ir course listings) + video-merge utility (ffmpeg via mwader/static-ffmpeg)"

ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION}

# TASK-007: the video-merge tool's defaults for this image. FFMPEG_BIN
# points at the binary copied in below (not a host-installed path); the
# Windows-path VIDEO_SOURCE_DIR/VIDEO_DEST_DIR/LEGACY_PATH_PREFIX
# defaults from config/video.php make no sense inside a Linux container,
# so the video-merge compose service (docker-compose.yml) sets all three
# explicitly — these two ENV lines are the ones safe to bake into the
# image itself because they're about the image's own filesystem, not the
# operator's data layout.
ENV FFMPEG_BIN=/usr/local/bin/ffmpeg
ENV VIDEO_MERGE_PLATFORM=linux

RUN adduser -D -u 1000 appuser

WORKDIR /app

COPY --from=ffmpeg /ffmpeg /usr/local/bin/ffmpeg
COPY --from=vendor /app/vendor ./vendor
COPY composer.json composer.lock ./
COPY index.php ./index.php
COPY function.php video_merge.php ./
COPY bin ./bin
COPY config ./config
COPY src ./src

RUN chown -R appuser:appuser /app

USER appuser

# ini_set('max_execution_time', 3600) inside index.php already covers the
# script's own runtime; nothing extra needed at the container level.
ENTRYPOINT ["php"]
CMD ["index.php"]

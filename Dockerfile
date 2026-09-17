# web-scraping-php — scraper image (PHP CLI)
#
# Scope decision (flagged, not silently applied): this repo holds TWO
# independent CLI tools (see README). Only the scraper (index.php) is
# built into this image. The video-merge utility
# (video_merge.php/function.php/bin/process.php) is NOT included as a
# runnable target here — its execution path
# (ProcessExecutor::runBatchFile() -> system('cmd /c ' . $batchFilePath))
# shells out to Windows cmd.exe against a generated .bat script. That is
# not a portability nuance to paper over with a rewrite; it is the tool's
# actual documented design (README "Known limitations: Still
# Windows-shaped"). Adapting it to run for real on Linux would mean
# rewriting the batch-generation into a shell/ffmpeg-only pipeline — new
# business logic, which the containerization task explicitly excludes
# (adapt the working model to the new system; do not extend it). The
# source is still copied into the image (single codebase, nothing to
# gain from splitting it), so the code is available for reference/`exec`,
# but `cmd` does not exist in this image and that entry point will fail
# fast and loud (command-not-found) rather than pretend to work.
#
# R91/R92/R102: multi-stage (composer-managed vendor/ is a real build
# artifact here, unlike rest-api-mvc-php's custom autoloader — see the
# vendor stage below), non-root USER, no secrets baked in, resource
# limits declared at the compose level (R60/R64).
#
# HEALTHCHECK (R91 checklist item) deliberately omitted, flagged rather
# than faked: the default CMD is index.php, a one-shot script that
# scrapes once and exits (see README "Running it"). Docker's HEALTHCHECK
# polls a container on an interval (default first probe at
# --start-period, minimum realistic value ~1s) but this container has
# already exited by the time any meaningful interval elapses — there is
# no long-running process for a healthcheck to observe. Adding one would
# either never fire (container gone) or require inventing a fake
# always-up process, which is worse than having none.

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
LABEL org.opencontainers.image.description="PHP scraper (downloadly.ir course listings) — video-merge utility excluded, see Dockerfile header"

ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION}

RUN adduser -D -u 1000 appuser

WORKDIR /app

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

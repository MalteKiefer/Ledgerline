# syntax=docker/dockerfile:1
#
# Ledgerline production image: FrankenPHP + Laravel Octane (worker mode). The repo
# is split into frontend/ (standalone Vue SPA) and backend/ (Laravel API). This
# multi-stage build compiles the SPA with Node, then builds the Laravel backend
# and copies the built SPA into public/ so a single image serves both the static
# frontend (FrankenPHP/Caddy static files) and the /api/v1 backend (Octane worker).
# Runs as non-root (www-data, uid 82 — matches the existing app-storage volume),
# listens on :8080. TLS + routing are handled by Caddy on the host.
#
# Octane keeps the app in memory across requests (no per-request bootstrap → the
# throughput win). Per-request state that used to die with the FPM process is
# reset explicitly: see App\Support\RequestMemo + App\Listeners\FlushRequestMemo
# and the companyMailer/MailSender teardowns.

# Base images pinned by immutable digest (reproducible, tamper-evident builds).
ARG PHP_BASE=dunglas/frankenphp:1-php8.5-alpine@sha256:def035e964f46253cb5e46a1f9a4633370f658b8e410305e0730ce7247d0ab6a

# --- Composer binary (runtime stage has no composer of its own) --------------
FROM composer:2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 AS composer

# --- Front-end: standalone SPA (Vite build) --------------------------------
FROM node:24-bookworm-slim@sha256:6f7b03f7c2c8e2e784dcf9295400527b9b1270fd37b7e9a7285cf83b6951452d AS assets
ARG APP_VERSION=dev
WORKDIR /build
# The SPA compiles its i18n from the backend's PHP lang files (single source),
# so the assets stage needs both trees.
COPY frontend/package.json frontend/yarn.lock frontend/.npmrc ./frontend/
RUN cd frontend && yarn install --frozen-lockfile --non-interactive
COPY frontend ./frontend
COPY backend/lang ./backend/lang
# Build the standalone SPA. No frontend asset is fetched from a CDN at runtime.
RUN cd frontend \
 && VITE_APP_VERSION="${APP_VERSION}" yarn build

# --- Runtime: FrankenPHP + Octane serving the Laravel backend + built SPA ----
FROM ${PHP_BASE} AS runtime

USER root
# System deps: image/video/OCR/geo toolchain (imagick+heif, ffmpeg, exiftool,
# tesseract+poppler), the PG18 client for pg_dump backups, gnupg (PGP mail), and
# curl for the healthcheck. Then the PHP extensions the app needs, plus pcntl +
# opcache for the Octane worker.
RUN apk add --no-cache \
      curl ca-certificates gnupg gzip libcap \
      libheif libde265 x265-libs aom-libs imagemagick imagemagick-heic \
      ffmpeg \
      exiftool \
      postgresql18-client \
      tesseract-ocr tesseract-ocr-data-eng tesseract-ocr-data-deu poppler-utils \
 && install-php-extensions pdo_pgsql pgsql pdo_sqlite intl gd exif imagick bcmath zip pcntl opcache \
 # The dunglas image sets cap_net_bind_service+ep on the frankenphp binary so it
 # can bind :80/:443 as non-root. We bind :8080 (>1024) and run under
 # no-new-privileges:true + cap_drop:[ALL] — the kernel then REFUSES to exec a
 # file that carries file-capabilities ("Operation not permitted"). Strip them:
 # the binary needs no privileged port.
 && setcap -r /usr/local/bin/frankenphp 2>/dev/null || true

# Archive tools for the Files archiver/unarchiver — a SEPARATE apk layer so these
# packages never interfere with install-php-extensions' build-dep cleanup (mixing
# them into the RUN above silently left pdo_pgsql/pgsql/zip uninstalled). No unrar
# (not in Alpine's repos) — p7zip handles RAR4 fully and RAR5 best-effort.
RUN apk add --no-cache tar p7zip unzip xz zstd bzip2

COPY --from=composer /usr/bin/composer /usr/bin/composer

# App PHP settings (memory/upload/opcache) + the runtime entrypoint.
COPY docker/frankenphp/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/frankenphp/entrypoint.sh /usr/local/bin/ll-entrypoint
RUN chmod +x /usr/local/bin/ll-entrypoint

# Hardened ImageMagick coder/delegate policy (untrusted image decoding).
COPY docker/imagemagick/policy.xml /etc/ImageMagick-7/policy.xml

WORKDIR /var/www/html

# Composer deps first (better layer caching), then the backend + built SPA.
COPY --chown=www-data:www-data backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --chown=www-data:www-data backend ./
# The standalone SPA build (index.html + hashed assets + tesseract) is served as
# static files from public/ by FrankenPHP/Caddy; unknown routes fall through to
# Laravel's catch-all, which streams the same index.html (spa.blade.php).
COPY --from=assets --chown=www-data:www-data /build/frontend/dist ./public

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
 && php artisan package:discover --ansi \
 # FrankenPHP/Caddy state dirs + the app tree must be writable by www-data (82),
 # which owns the app-storage volume — so no root/su-exec at runtime. The php
 # conf.d dir too: the entrypoint drops a per-service memory_limit override there
 # (worker → 2048M), and www-data cannot write a root-owned conf.d otherwise.
 && mkdir -p /data /config \
 && chown -R www-data:www-data /data /config /usr/local/etc/php/conf.d /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data
EXPOSE 8080

ENTRYPOINT ["ll-entrypoint"]
# Web app: Octane on FrankenPHP. --max-requests recycles workers to bound any
# leak; worker/scheduler services override this command (queue:work/schedule:work).
#
# --workers is a fixed 16, not "auto" (which resolves to the host's CPU
# count — 4 on the production box, per APP_CPU_LIMIT below). This app is
# I/O-bound (DB/disk/external calls, plus the deliberately worker-blocking
# App\Http\Controllers\FilesChangesController SSE stream) far more than it is
# CPU-bound, and Docker's `cpus:` limit already throttles TOTAL CPU time
# across every worker regardless of process count — so tying worker COUNT to
# core count buys nothing there, it only limits how many requests can be
# concurrently in flight (mostly waiting, not computing) before they queue.
# A single background ledgerline-cli sync client chronically holding 1-2 of
# only 4 such workers via its SSE reconnect loop (see SseSlot's doc comment)
# was enough to make a handful of concurrent browser requests exhaust the
# rest and hang the whole app. 16 comfortably fits the 512M-per-worker
# PHP_MEMORY_LIMIT default under the 8g APP_MEMORY_LIMIT default with real
# headroom to spare (in-flight workers are rarely all near their memory
# ceiling at once in practice), and leaves most of the pool free even at
# SseSlot::CAP_GLOBAL's full reservation.
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8080", "--admin-port=2019", "--workers=16", "--max-requests=500"]

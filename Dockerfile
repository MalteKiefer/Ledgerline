# syntax=docker/dockerfile:1
#
# Ledgerline production image: nginx + PHP-FPM (serversideup base), with the
# extensions the app needs (pgsql, gd, imagick, intl, bcmath, exif, zip; sodium
# + opcache are built in) plus ffmpeg and libheif for gallery/HEIC processing.
# Assets are built with Node in a separate stage. Runs as non-root (www-data),
# listens on :8080. TLS + routing are handled by Caddy on the host.

# Base images pinned by immutable digest (reproducible, tamper-evident builds).
# Bump the tag + digest together, deliberately, after review. The runtime base is
# the ALPINE (musl) variant — a far smaller package set than Debian, which cuts
# the untrusted-media OS-CVE attack surface substantially.
ARG PHP_BASE=serversideup/php:8.5-fpm-nginx-alpine@sha256:1854d81da23fc5c174a26bf039bc7724aeccec5743524717bbc6c10c1c927ac2

# --- Front-end assets (Vite build) -----------------------------------------
FROM node:22-bookworm-slim@sha256:6c74791e557ce11fc957704f6d4fe134a7bc8d6f5ca4403205b2966bd488f6b3 AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build
# Self-host the OCR (tesseract.js) worker + WASM core + eng/deu language data so
# nothing is fetched from a CDN at runtime (our CSP is worker-src/connect-src
# 'self'). Downloads the language data over the build network.
RUN node scripts/stage-tesseract.mjs

# --- Runtime ----------------------------------------------------------------
FROM ${PHP_BASE} AS runtime

USER root
RUN apk add --no-cache \
      curl ca-certificates gnupg gzip \
      # HEIC/HEIF decode (libde265) AND encode (x265 for HEIC, aom for AVIF) so
      # edited exports can be re-saved in format; imagemagick-heic wires the HEIC
      # delegate into ImageMagick (the imagick extension reads/writes HEIC).
      libheif libde265 x265-libs aom-libs imagemagick imagemagick-heic \
      # video / Apple Motion + Live Photos (HEVC, MOV, ProRes) + thumbnails
      ffmpeg \
      # rich media metadata (EXIF/XMP, Motion-Photo + Live-Photo detection)
      exiftool \
      # database backups shell out to pg_dump + gzip; it must match the PG17
      # server (an older pg_dump refuses a newer server). Alpine ships pg17.
      # NOTE: no OCR/PDF toolchain (ghostscript/tesseract/ocrmypdf/qpdf/poppler)
      # — the app never shells out to it (OCR is external Paperless) — so that
      # large untrusted-decode CVE/RCE surface is omitted entirely.
      postgresql17-client \
 && install-php-extensions pdo_pgsql pgsql pdo_sqlite intl gd exif imagick bcmath zip

# Hardened ImageMagick coder/delegate policy (untrusted image decoding).
COPY docker/imagemagick/policy.xml /etc/ImageMagick-6/policy.xml
COPY docker/imagemagick/policy.xml /etc/ImageMagick-7/policy.xml

# Let WebDAV serve its own dotfiles (macOS AppleDouble/._*), bypassing the
# base image's dotfile deny so Finder does not retry-storm and crawl.
COPY --chown=www-data:www-data docker/nginx/00-assets.conf /etc/nginx/server-opts.d/00-assets.conf

# serversideup automations are driven per-service via env in compose; default off.
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=20000 \
    PHP_MEMORY_LIMIT=512M \
    PHP_MAX_EXECUTION_TIME=120 \
    PHP_POST_MAX_SIZE=560M \
    PHP_UPLOAD_MAX_FILE_SIZE=550M \
    AUTORUN_ENABLED=false

WORKDIR /var/www/html

# Composer deps first (better layer caching), then the app + built assets.
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --chown=www-data:www-data . .
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY --from=assets --chown=www-data:www-data /app/public/tesseract ./public/tesseract

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
 && php artisan package:discover --ansi

USER www-data

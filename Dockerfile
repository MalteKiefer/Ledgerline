# syntax=docker/dockerfile:1
#
# Ledgerline production image: nginx + PHP-FPM (serversideup base), with the
# extensions the app needs (pgsql, gd, imagick, intl, bcmath, exif, zip; sodium
# + opcache are built in) plus ffmpeg and libheif for gallery/HEIC processing.
# Assets are built with Node in a separate stage. Runs as non-root (www-data),
# listens on :8080. TLS + routing are handled by Caddy on the host.

ARG PHP_BASE=serversideup/php:8.4-fpm-nginx

# --- Front-end assets (Vite build) -----------------------------------------
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# --- Runtime ----------------------------------------------------------------
FROM ${PHP_BASE} AS runtime

USER root
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      curl \
      # HEIC/HEIF + Apple stills + HEVC still decode
      libheif1 libheif-examples libde265-0 imagemagick \
      # video / Apple Motion + Live Photos (HEVC, MOV, ProRes) + thumbnails
      ffmpeg \
      # rich media metadata (EXIF/XMP, Motion-Photo + Live-Photo detection)
      libimage-exiftool-perl \
      # document OCR (searchable PDFs) + text/image extraction
      ocrmypdf ghostscript qpdf poppler-utils \
      tesseract-ocr tesseract-ocr-eng tesseract-ocr-deu \
 && install-php-extensions pdo_pgsql pgsql pdo_sqlite intl gd exif imagick bcmath zip \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

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

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
 && php artisan package:discover --ansi

USER www-data

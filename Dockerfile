# syntax=docker/dockerfile:1
#
# Ledgerline production image: nginx + PHP-FPM (serversideup base), with the
# extensions the app needs (pgsql, gd, imagick, intl, bcmath, exif, zip; sodium
# + opcache are built in) plus ffmpeg and libheif for gallery/HEIC processing.
# Assets are built with Node in a separate stage. Runs as non-root (www-data),
# listens on :8080. TLS + routing are handled by Caddy on the host.

# Base images pinned by immutable digest (reproducible, tamper-evident builds).
# Bump the tag + digest together, deliberately, after review.
ARG PHP_BASE=serversideup/php:8.4-fpm-nginx@sha256:519720d9ff5d50aad9eb83fac290746460dfc1346faa8fdb25c75d28a3feb2ab

# --- Front-end assets (Vite build) -----------------------------------------
FROM node:22-bookworm-slim@sha256:53ada149d435c38b14476cb57e4a7da73c15595aba79bd6971b547ceb6d018bf AS assets
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
      curl ca-certificates gnupg gzip \
      # HEIC/HEIF + Apple stills + HEVC still: decode (libde265) AND encode
      # (x265 for HEIC, aom for AVIF) so edited exports can be re-saved in format
      libheif1 libheif-examples libde265-0 imagemagick \
      libheif-plugin-x265 libheif-plugin-aomenc libheif-plugin-libde265 \
      # video / Apple Motion + Live Photos (HEVC, MOV, ProRes) + thumbnails
      ffmpeg \
      # rich media metadata (EXIF/XMP, Motion-Photo + Live-Photo detection)
      libimage-exiftool-perl \
      # document OCR (searchable PDFs) + text/image extraction
      ocrmypdf ghostscript qpdf poppler-utils \
      tesseract-ocr tesseract-ocr-eng tesseract-ocr-deu \
      # database backups shell out to pg_dump + gzip. The app DB is Postgres 17,
      # so the client must be 17 too (an older pg_dump refuses a newer server).
      # Debian ships an older default, so pull postgresql-client-17 from PGDG.
 && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /usr/share/keyrings/pgdg.gpg \
 && echo "deb [signed-by=/usr/share/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt $(. /etc/os-release; echo $VERSION_CODENAME)-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
 && apt-get update \
 && apt-get install -y --no-install-recommends postgresql-client-17 \
 && install-php-extensions pdo_pgsql pgsql pdo_sqlite intl gd exif imagick bcmath zip \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Hardened ImageMagick coder/delegate policy (untrusted image decoding).
COPY docker/imagemagick/policy.xml /etc/ImageMagick-6/policy.xml
COPY docker/imagemagick/policy.xml /etc/ImageMagick-7/policy.xml

# Let WebDAV serve its own dotfiles (macOS AppleDouble/._*), bypassing the
# base image's dotfile deny so Finder does not retry-storm and crawl.
COPY --chown=www-data:www-data docker/nginx/00-dav.conf /etc/nginx/server-opts.d/00-dav.conf

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

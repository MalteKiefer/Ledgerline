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
ARG PHP_BASE=serversideup/php:8.5-fpm-nginx-alpine@sha256:13af81f6fb5fbb9e26c6a7cd9e8c8bf22e32dd21842ae3c587b9ac4f24da4c6e

# --- Front-end assets (Vite build) -----------------------------------------
FROM node:25-bookworm-slim@sha256:81db02c4b671288a03915da9534dbd54f96d0e7c24d80ccc54f5b36b2e684370 AS assets
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
      # database backups shell out to pg_dump + gzip; the client MAJOR must be
      # >= the server (pg_dump refuses a newer server). Prod runs PostgreSQL 18
      # (pgvector pg18) since v1.506.85, so pin the pg18 client (Alpine 3.24 ships it).
      postgresql18-client \
      # Server-side receipt OCR (POST /api/v1/invoices/ocr): tesseract (eng+deu)
      # OCRs raster receipts; poppler-utils' pdftotext extracts a PDF text layer
      # (fast path) and pdftoppm rasterises scanned PDFs. Deliberate scope: ONLY
      # these two — NOT ghostscript/ocrmypdf/qpdf — to keep the untrusted-decode
      # surface minimal. The app only ever shells out to them via array-argv
      # (BinaryProcess, no shell) on a transient temp file that is shredded after.
      tesseract-ocr tesseract-ocr-data-eng tesseract-ocr-data-deu poppler-utils \
 && install-php-extensions pdo_pgsql pgsql pdo_sqlite intl gd exif imagick bcmath zip

# Hardened ImageMagick coder/delegate policy (untrusted image decoding).
COPY docker/imagemagick/policy.xml /etc/ImageMagick-6/policy.xml
COPY docker/imagemagick/policy.xml /etc/ImageMagick-7/policy.xml

# Let WebDAV serve its own dotfiles (macOS AppleDouble/._*), bypassing the
# base image's dotfile deny so Finder does not retry-storm and crawl.
COPY --chown=www-data:www-data docker/nginx/00-assets.conf /etc/nginx/server-opts.d/00-assets.conf
# Override the base image's security.conf: drop its add_header security headers
# (our SecurityHeaders middleware is the single source — avoids the duplicate /
# conflicting X-Frame-Options), keep its file-access deny blocks.
COPY --chown=www-data:www-data docker/nginx/security.conf /etc/nginx/server-opts.d/security.conf

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

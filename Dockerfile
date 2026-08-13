# syntax=docker/dockerfile:1
#
# Ledgerline production image: nginx + PHP-FPM (serversideup base). The repo is
# split into frontend/ (standalone Vue SPA) and backend/ (Laravel API). This
# multi-stage build compiles the SPA with Node, then builds the Laravel backend
# and copies the built SPA into public/ so a single image serves both the static
# frontend and the /api/v1 backend. Runs as non-root (www-data), listens on
# :8080. TLS + routing are handled by Caddy on the host.

# Base images pinned by immutable digest (reproducible, tamper-evident builds).
ARG PHP_BASE=serversideup/php:8.5-fpm-nginx-alpine@sha256:13af81f6fb5fbb9e26c6a7cd9e8c8bf22e32dd21842ae3c587b9ac4f24da4c6e

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
# Build the SPA (dist/) and self-host the tesseract.js OCR worker + WASM core +
# eng/deu language data into dist/tesseract (CSP is worker-src/connect-src 'self',
# nothing from a CDN at runtime). The language data downloads over the build net.
RUN cd frontend \
 && VITE_APP_VERSION="${APP_VERSION}" yarn build \
 && node scripts/stage-tesseract.mjs

# --- Runtime: Laravel backend + built SPA ----------------------------------
FROM ${PHP_BASE} AS runtime

USER root
RUN apk add --no-cache \
      curl ca-certificates gnupg gzip \
      libheif libde265 x265-libs aom-libs imagemagick imagemagick-heic \
      ffmpeg \
      exiftool \
      postgresql18-client \
      tesseract-ocr tesseract-ocr-data-eng tesseract-ocr-data-deu poppler-utils \
 && install-php-extensions pdo_pgsql pgsql pdo_sqlite intl gd exif imagick bcmath zip

# Hardened ImageMagick coder/delegate policy (untrusted image decoding).
COPY docker/imagemagick/policy.xml /etc/ImageMagick-6/policy.xml
COPY docker/imagemagick/policy.xml /etc/ImageMagick-7/policy.xml

COPY --chown=www-data:www-data docker/nginx/00-assets.conf /etc/nginx/server-opts.d/00-assets.conf
COPY --chown=www-data:www-data docker/nginx/security.conf /etc/nginx/server-opts.d/security.conf

ENV PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=20000 \
    PHP_MEMORY_LIMIT=512M \
    PHP_MAX_EXECUTION_TIME=120 \
    PHP_POST_MAX_SIZE=560M \
    PHP_UPLOAD_MAX_FILE_SIZE=550M \
    AUTORUN_ENABLED=false

WORKDIR /var/www/html

# Composer deps first (better layer caching), then the backend + built SPA.
COPY --chown=www-data:www-data backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --chown=www-data:www-data backend ./
# The standalone SPA build (index.html + hashed assets + tesseract) is served as
# static files from public/; unknown routes fall through to Laravel's catch-all,
# which streams the same index.html (see resources/views/spa.blade.php).
COPY --from=assets --chown=www-data:www-data /build/frontend/dist ./public

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
 && php artisan package:discover --ansi

USER www-data

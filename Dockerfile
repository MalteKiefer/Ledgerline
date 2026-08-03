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
FROM node:24-bookworm-slim@sha256:6f7b03f7c2c8e2e784dcf9295400527b9b1270fd37b7e9a7285cf83b6951452d AS assets
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
      # database backups shell out to pg_dump + gzip; the client MAJOR must be >=
      # the server major (an older pg_dump refuses a newer server). DB is pg18
      # (pgvector:pg18) → pin the pg18 client. Bump this in lockstep with the DB.
      postgresql18-client \
      # Server-side receipt OCR (POST /api/v1/invoices/ocr): tesseract (eng+deu)
      # OCRs raster receipts; poppler-utils' pdftotext extracts a PDF text layer
      # (fast path) and pdftoppm rasterises scanned PDFs. Deliberate scope: ONLY
      # these two — NOT ghostscript/ocrmypdf/qpdf — to keep the untrusted-decode
      # surface minimal. The app only ever shells out to them via array-argv
      # (BinaryProcess, no shell) on a transient temp file that is shredded after.
      tesseract-ocr tesseract-ocr-data-eng tesseract-ocr-data-deu poppler-utils \
      # Mail-archive IMAP sync (mail module): isync/mbsync mirrors each account's
      # mailbox PULL-ONLY into a scratch Maildir (see App\Services\Mail\
      # MbsyncConfig — read-only origin, Sync Pull / Expunge None / Remove None).
      # App\Services\Mail\MbsyncRunner shells `mbsync` IN-PROCESS in the worker
      # via BinaryProcess (array-argv, no shell), so the binary must be ON PATH
      # in THIS runtime image — a separate mbsync sidecar container could not be
      # reached by that in-process call. Alpine 3.24 ships isync 1.5.1, whose
      # TLSType/TLSVersions directives MbsyncConfig emits (see that class).
      isync \
      # Server-side mail sealer (App\Support\Mail\MailSealer, mail-archive
      # ingest): shells `node resources/js/mail-sealer/seal.mjs` per fetched
      # message to seal it to the user's public identity keys — Node itself
      # only needs to be ON PATH here, the sealer's actual JS dependency
      # closure is copied in separately below (not via npm at runtime).
      nodejs \
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

# resources/js/mail-sealer/seal.mjs + the resources/js/shared/*.js it imports
# are already present via the `COPY . .` above. It additionally needs three
# npm packages at runtime (libsodium-wrappers-sumo + its native libsodium-sumo,
# and @noble/{hashes,curves,ciphers,post-quantum} — @noble/post-quantum pulls
# in curves+ciphers). Rather than ship the FULL `npm ci` node_modules (100s of
# MB, almost all dev/build-only tooling) into the runtime image, copy only this
# sealer's actual dependency closure — resolved from the `assets` stage's
# `npm ci` install, exactly what the tests run against — which is ~7 MiB total.
# If the sealer's imports ever grow beyond these packages, add them here too;
# a missing package fails loudly (MailSealer throws) rather than silently.
COPY --from=assets --chown=www-data:www-data /app/node_modules/libsodium-wrappers-sumo ./node_modules/libsodium-wrappers-sumo
COPY --from=assets --chown=www-data:www-data /app/node_modules/libsodium-sumo ./node_modules/libsodium-sumo
COPY --from=assets --chown=www-data:www-data /app/node_modules/@noble ./node_modules/@noble

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
 && php artisan package:discover --ansi

USER www-data

#!/bin/sh
# Runtime entrypoint for the FrankenPHP + Octane image (replaces the serversideup
# AUTORUN feature). The web app (octane) runs migrations + warms the caches once
# on start; the worker/scheduler containers override the command to
# `php artisan queue:work`/`schedule:work` and skip all of it.
set -e

# Per-service PHP memory override. app.ini defaults memory_limit to 512M; the
# backup queue worker raises it via PHP_MEMORY_LIMIT (PharData blob archives).
if [ -n "${PHP_MEMORY_LIMIT:-}" ]; then
    printf 'memory_limit=%s\n' "$PHP_MEMORY_LIMIT" > "${PHP_INI_DIR:-/usr/local/etc/php}/conf.d/zz-runtime.ini"
fi

# Only the octane web server runs schema migrations + cache warming, under an
# isolated container so there is no race with the worker/scheduler.
case " $* " in
    *octane*)
        php artisan migrate --force --no-interaction
        php artisan storage:link --no-interaction || true
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        php artisan event:cache
        ;;
esac

exec "$@"

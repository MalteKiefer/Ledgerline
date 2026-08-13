{{--
  The frontend is a standalone build (../frontend → dist, copied into public/ by
  the Docker image). Every SPA route, Fortify auth view and the invite landing
  resolves to view('spa'), which streams that built index.html so the Vue app
  boots and drives everything over /api/v1. In local dev run the Vite dev server
  in ../frontend (npm run dev) against `php artisan serve`; Laravel serves the
  API only and this fallback is not used.
--}}
@php
    $index = public_path('index.html');
@endphp
@if (is_file($index))
    {!! file_get_contents($index) !!}
@else
    {{-- No production build present (local dev / tests). Ship the mount point so
         SPA routes still resolve; run `cd frontend && npm run dev` for the app. --}}
    <!doctype html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ledgerline</title></head>
    <body>
      <div id="app"></div>
      <!-- Frontend build absent: run `cd frontend && npm run dev` (dev) or build it (prod copies frontend/dist into public/). -->
    </body>
    </html>
@endif

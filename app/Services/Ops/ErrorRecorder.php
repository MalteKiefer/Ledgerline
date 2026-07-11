<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Models\ErrorEvent;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Records unhandled exceptions into the in-app error log (no external service).
 * Expected/benign exceptions (validation, auth, 4xx, CSRF, 404) are ignored so
 * the log only surfaces genuine server faults. Everything is deduplicated by a
 * fingerprint and redacted of obvious secrets before storage.
 */
class ErrorRecorder
{
    /** Re-entrancy guard so a failure while recording never loops. */
    private static bool $recording = false;

    /** Exceptions that are normal request outcomes, not server faults. */
    private const IGNORE = [
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        ModelNotFoundException::class,
        TokenMismatchException::class,
    ];

    public function record(Throwable $e, array $context = []): void
    {
        if (self::$recording || ! $this->shouldRecord($e)) {
            return;
        }

        self::$recording = true;
        try {
            $this->store($e, $context);
        } catch (Throwable) {
            // Never let error recording break the request or recurse.
        } finally {
            self::$recording = false;
        }
    }

    private function shouldRecord(Throwable $e): bool
    {
        foreach (self::IGNORE as $class) {
            if ($e instanceof $class) {
                return false;
            }
        }

        // Only 5xx HTTP exceptions are real faults; 4xx are client errors.
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode() >= 500;
        }

        return true;
    }

    private function store(Throwable $e, array $context): void
    {
        $message = $this->redact($e->getMessage());
        $fingerprint = sha1(implode('|', [
            $e::class,
            $e->getFile(),
            $e->getLine(),
            // Normalise digits so the same fault with varying ids/counts folds together.
            preg_replace('/\d+/', '#', $message),
        ]));

        $now = Carbon::now();
        $context = array_merge($this->requestContext(), $context);

        $existing = ErrorEvent::where('fingerprint', $fingerprint)->first();
        if ($existing !== null) {
            $existing->update([
                'count' => $existing->count + 1,
                'last_seen_at' => $now,
                'message' => $message,
                'context' => $context,
                'resolved_at' => null,
            ]);

            return;
        }

        ErrorEvent::create([
            'fingerprint' => $fingerprint,
            'level' => 'error',
            'exception' => $e::class,
            'message' => Str::limit($message, 2000),
            'file' => $this->relative($e->getFile()),
            'line' => $e->getLine(),
            'context' => $context,
            'trace' => Str::limit($this->redact($e->getTraceAsString()), 8000),
            'count' => 1,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]);
    }

    /** Non-sensitive request context, when recording inside an HTTP request. */
    private function requestContext(): array
    {
        if (! app()->bound('request') || app()->runningInConsole()) {
            return [];
        }

        try {
            $request = request();

            return array_filter([
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'user_id' => Auth::id(),
            ], fn ($v) => $v !== null);
        } catch (Throwable) {
            return [];
        }
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    /** Strip obvious secrets a message/trace may echo (passwords, URIs, tokens). */
    private function redact(string $text): string
    {
        $patterns = [
            '/(password["\']?\s*[:=]\s*["\']?)[^"\'\s,&]+/i' => '$1***',
            '/(secret|token|key|passphrase)(["\']?\s*[:=]\s*["\']?)[^"\'\s,&]+/i' => '$1$2***',
            '/([a-z][a-z0-9+.\-]*:\/\/[^:\/\s@]+:)[^@\/\s]+@/i' => '$1***@',
            '/(Bearer\s+)[A-Za-z0-9._\-]+/i' => '$1***',
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $text);
    }
}

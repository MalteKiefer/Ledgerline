<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\Servers\LogReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read logs from a monitored host.
 *
 * These two endpoints open an SSH session inline, which the collector never
 * does. That is the same exception the explicit connection test already makes:
 * a user is waiting on the answer, so it runs with the interactive timeouts
 * (5s connect, 10s exec) and fails fast rather than holding a worker open. It is
 * also throttled, because unlike the test it is something a reader might click
 * repeatedly.
 */
class ServerLogController extends Controller
{
    public function __construct(private LogReader $reader) {}

    /**
     * What this host can offer. The UI renders only what comes back from here,
     * and a subsequent read is checked against it — so a selection originates
     * with the host, not with the browser.
     */
    public function sources(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);

        return response()->json($this->reader->sources($server));
    }

    /** Fetch the tail of one log. */
    public function read(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);

        $request->validate([
            'source' => ['required', Rule::in(LogReader::SOURCES)],
            'unit' => ['nullable', 'string', 'max:128'],
            'container' => ['nullable', 'string', 'max:128'],
            'path' => ['nullable', 'string', 'max:200'],
            'lines' => ['sometimes', 'integer', 'min:1', 'max:2000'],
            'errors_only' => ['sometimes', 'boolean'],
        ]);

        $source = $request->string('source')->value();
        $selection = [
            'source' => $source,
            'unit' => $this->pick($request, 'unit'),
            'container' => $this->pick($request, 'container'),
            'path' => $this->pick($request, 'path'),
            'lines' => $request->integer('lines', 200),
            'errors_only' => $request->boolean('errors_only'),
        ];

        $result = $this->reader->read($server, $selection);
        if (! $result['ok']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json(['text' => $result['text']])
            // A log tail is a point-in-time read of someone's server; it has no
            // business sitting in an intermediary's cache.
            ->header('Cache-Control', 'no-store');
    }

    /** A blank optional field means "not chosen", not an empty selection. */
    private function pick(Request $request, string $key): ?string
    {
        $value = $request->string($key)->value();

        return $value === '' ? null : $value;
    }
}

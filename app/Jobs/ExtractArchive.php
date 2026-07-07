<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StoredFile;
use App\Services\Files\ArchiveManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Unpacks an archive in the background and publishes live progress to the cache
 * so the browser can poll it — large archives no longer block the request or
 * leave the user staring at a frozen UI.
 */
class ExtractArchive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public string $token,
        public int $userId,
        public string $fileId,
        public string $name,
    ) {}

    public static function statusKey(string $token): string
    {
        return 'extract-status:'.$token;
    }

    public function handle(ArchiveManager $archives): void
    {
        $file = StoredFile::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('id', $this->fileId)->where('user_id', $this->userId)->first();

        if ($file === null) {
            $this->put(['state' => 'failed', 'error' => __('files.extract_failed')]);

            return;
        }

        // Throttle cache writes to at most ~2/sec while still landing the final one.
        $last = 0.0;
        try {
            $count = $archives->extract($this->userId, $file, function (int $done, int $total) use (&$last) {
                $now = microtime(true);
                if ($now - $last >= 0.5) {
                    $last = $now;
                    $this->put(['state' => 'running', 'done' => $done, 'total' => $total]);
                }
            });
            $this->put(['state' => 'done', 'done' => $count, 'total' => $count]);
        } catch (\Throwable $e) {
            $msg = $e instanceof HttpException
                ? ($e->getMessage() ?: __('files.extract_failed'))
                : __('files.extract_failed');
            $this->put(['state' => 'failed', 'error' => $msg]);
        }
    }

    /** @param array<string,mixed> $data */
    private function put(array $data): void
    {
        Cache::put(self::statusKey($this->token), $data + [
            'user' => $this->userId,
            'name' => $this->name,
        ], now()->addHour());
    }
}

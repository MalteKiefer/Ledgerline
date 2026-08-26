<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateGalleryThumbnail;
use App\Models\GalleryPhoto;
use Illuminate\Console\Command;

/**
 * Re-runs the thumbnail job for photos that have no width or height.
 *
 * PHP's getimagesize does not understand HEIC, so those uploads stored no size
 * at all — a real library had 1859 of them. The job reads the size from the
 * header now, so re-queueing it is all that is needed; nothing here decodes an
 * image, and the work stays in the worker where image handling belongs.
 *
 * Photos whose renditions already exist are the normal case here, and the job
 * reads their size in that branch rather than returning straight away — nothing
 * is re-rendered.
 */
class BackfillGalleryDimensions extends Command
{
    protected $signature = 'gallery:backfill-dimensions {--user= : Only this account} {--limit=0 : Stop after N}';

    protected $description = 'Queue a size read for gallery photos stored without width/height';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $query = GalleryPhoto::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('media_type', 'image')
            ->where(fn ($q) => $q->whereNull('width')->orWhereNull('height'))
            ->when($this->option('user') !== null, fn ($q) => $q->where('user_id', (int) $this->option('user')))
            ->orderBy('id');

        $queued = 0;
        foreach ($query->cursor() as $photo) {
            if ($limit > 0 && $queued >= $limit) {
                break;
            }
            // A size read, not a re-render — but still on the queue, because
            // reading a HEIC header is a binary call on an untrusted file.
            GenerateGalleryThumbnail::dispatch((int) $photo->id);
            $queued++;
        }

        $this->info($queued.' photo(s) queued for a size read.');

        return self::SUCCESS;
    }
}

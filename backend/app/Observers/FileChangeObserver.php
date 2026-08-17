<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Support\FileChangeSignal;

/**
 * Marks a user's Files as changed (see App\Support\FileChangeSignal) on
 * every create/update/delete/restore of a FileEntry or FileFolder — shared
 * between both models (see AppServiceProvider::boot()), and independent of
 * which code path touched them (the REST API, the WebDAV backend, an
 * archive extraction job, ...) since it hooks the model layer, not any one
 * controller. A sync client cares about either kind identically: "something
 * changed, go run a diff pass."
 */
class FileChangeObserver
{
    public function created(FileEntry|FileFolder $model): void
    {
        FileChangeSignal::touch((int) $model->user_id);
    }

    public function updated(FileEntry|FileFolder $model): void
    {
        FileChangeSignal::touch((int) $model->user_id);
    }

    public function deleted(FileEntry|FileFolder $model): void
    {
        FileChangeSignal::touch((int) $model->user_id);
    }

    public function restored(FileEntry|FileFolder $model): void
    {
        FileChangeSignal::touch((int) $model->user_id);
    }
}

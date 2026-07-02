<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A virtual, nestable folder for organising files. Folders are global and can
 * contain subfolders and files.
 */
#[Fillable(['name', 'enc_name', 'parent_id'])]
class Folder extends Model
{
    /**
     * @return BelongsTo<Folder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('name');
    }

    /**
     * @return HasMany<File, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * The chain of ancestor folders from the root down to (but excluding) this
     * folder, for breadcrumbs.
     *
     * @return Collection<int, Folder>
     */
    public function ancestors(): Collection
    {
        $chain = new Collection;
        $node = $this->parent;

        while ($node !== null) {
            $chain->prepend($node);
            $node = $node->parent;
        }

        return $chain;
    }
}

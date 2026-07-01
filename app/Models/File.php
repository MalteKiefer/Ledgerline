<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FileType;
use App\Models\Concerns\BelongsToTeam;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An uploaded file attached to a customer or a project.
 *
 * The bytes live on the object-storage disk; this record holds only metadata.
 * The file is owned by a team (denormalised team_id) and only visible to its
 * members. team_id and the polymorphic owner are set explicitly by the
 * controller, so they are not mass-assignable.
 */
#[Fillable([
    'name',
    'title',
    'description',
    'note',
    'disk_path',
    'mime_type',
    'type',
    'size',
    'checksum',
    'is_encrypted',
    'extracted_text',
])]
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FileType::class,
            'is_encrypted' => 'boolean',
            'size' => 'integer',
        ];
    }

    /**
     * The title to show, falling back to the original file name.
     */
    protected function displayTitle(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->title) ? $this->title : $this->name);
    }

    /**
     * Whether the file can be previewed inline in the browser.
     */
    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, [
            'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf',
        ], true);
    }

    /**
     * Whether the file is an image safe to render with <img>.
     */
    public function isImage(): bool
    {
        return $this->type === FileType::IMAGE && $this->mime_type !== 'image/svg+xml';
    }

    /**
     * The customer or project this file is attached to.
     *
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who uploaded the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The free tags attached to this file.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}

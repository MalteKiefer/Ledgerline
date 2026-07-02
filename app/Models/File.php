<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FileType;
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
 * The polymorphic owner is set explicitly by the controller, so it is not
 * mass-assignable.
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
    'enc_metadata',
    'enc_file_key',
    'folder_id',
])]
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

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
     * Whether this is a general file (not tied to a customer or project).
     */
    public function isGeneral(): bool
    {
        return $this->attachable_type === null;
    }

    /**
     * The folder this file lives in, if any.
     *
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
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

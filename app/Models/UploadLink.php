<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A public, tokenised link that only lets a visitor upload into one folder. */
#[Fillable(['token', 'file_folder_id', 'label', 'allowed_extensions', 'expires_at', 'password', 'max_file_mb'])]
class UploadLink extends Model
{
    use OwnsUserData;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'password' => 'hashed',
            'uploads' => 'integer',
            'max_file_mb' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isProtected(): bool
    {
        return $this->password !== null && $this->password !== '';
    }

    /** Lowercase allowed extensions (empty = any type allowed). */
    public function extensions(): array
    {
        if (! filled($this->allowed_extensions)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($e) => ltrim(strtolower(trim($e)), '.'),
            explode(',', $this->allowed_extensions)
        )));
    }

    public function allowsFilename(string $name): bool
    {
        $exts = $this->extensions();
        if ($exts === []) {
            return true;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return in_array($ext, $exts, true);
    }
}

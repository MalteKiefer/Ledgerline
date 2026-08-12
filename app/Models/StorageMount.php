<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * An external storage mount (S3 or SFTP) the user browses live inside Files.
 * The driver credentials live encrypted under APP_KEY and are never serialized
 * to the client. Owner-scoped via OwnsUserData.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property array<string, mixed>|null $config
 * @property bool $read_only
 */
#[Hidden(['config'])]
class StorageMount extends Model
{
    use OwnsUserData;

    protected $fillable = ['name', 'type', 'read_only'];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'read_only' => 'boolean',
        ];
    }
}

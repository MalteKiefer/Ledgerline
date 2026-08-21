<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A monitored server, reached over plain SSH (no agent installed on the target).
 *
 * `credentials` — the SSH password or private key — is encrypted under APP_KEY
 * and #[Hidden], so it is never serialized into a response even by a wholesale
 * toArray(); the controller whitelists its output on top of that. Rows are
 * private per user via OwnsUserData.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $host
 * @property int $port
 * @property string $username
 * @property string $auth_type
 * @property array<string, mixed>|null $credentials
 * @property string|null $host_fingerprint
 * @property bool $restricted_key
 * @property string|null $group
 * @property string|null $note
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['credentials'])]
class Server extends Model
{
    use OwnsUserData;

    /** Secrets, the host key pin and the owner are server-set — never mass-assigned. */
    protected $fillable = ['name', 'host', 'port', 'username', 'auth_type', 'group', 'note', 'enabled', 'restricted_key'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'port' => 'integer',
            'enabled' => 'boolean',
            'restricted_key' => 'boolean',
        ];
    }

    /** @return HasMany<ServerFact, $this> */
    public function facts(): HasMany
    {
        return $this->hasMany(ServerFact::class);
    }

    /** The newest collection run, successful or not — this is what the UI shows. */
    /** @return HasOne<ServerFact, $this> */
    public function latestFact(): HasOne
    {
        return $this->hasOne(ServerFact::class)->latestOfMany('collected_at');
    }
}

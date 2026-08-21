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
 * @property string|null $host_key
 * @property bool $restricted_key
 * @property bool|null $account_created
 * @property array<int, array{port:int,label:string|null}>|null $monitor_ports
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
    protected $fillable = ['name', 'host', 'port', 'username', 'auth_type', 'group', 'note', 'enabled', 'restricted_key', 'account_created', 'monitor_ports'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'port' => 'integer',
            'enabled' => 'boolean',
            'restricted_key' => 'boolean',
            'account_created' => 'boolean',
            'monitor_ports' => 'array',
        ];
    }

    /** @return HasMany<ServerFact, $this> */
    public function facts(): HasMany
    {
        return $this->hasMany(ServerFact::class);
    }

    /** @return HasMany<ServerCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(ServerCheck::class);
    }

    /**
     * The configured extra ports, normalised. The column is user-supplied JSON,
     * so it is validated on the way out as well as in: a hand-edited row must
     * not be able to feed a non-integer into a socket call.
     *
     * @return list<array{port:int,label:string|null}>
     */
    public function monitorPorts(): array
    {
        $out = [];
        foreach ($this->monitor_ports ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $port = is_numeric($entry['port'] ?? null) ? (int) $entry['port'] : 0;
            if ($port < 1 || $port > 65535) {
                continue;
            }
            $label = $entry['label'] ?? null;
            $out[] = ['port' => $port, 'label' => is_string($label) && $label !== '' ? $label : null];
        }

        return $out;
    }

    /** The newest collection run, successful or not — this is what the UI shows. */
    /** @return HasOne<ServerFact, $this> */
    public function latestFact(): HasOne
    {
        return $this->hasOne(ServerFact::class)->latestOfMany('collected_at');
    }
}

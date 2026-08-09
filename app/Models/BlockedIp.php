<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A blocked source IP or CIDR range. A request from a matching address is
 * refused early with 403 (see App\Http\Middleware\BlockGuard).
 *
 * @property int $id
 * @property string $cidr
 * @property string|null $reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BlockedIp extends Model
{
    protected $fillable = ['cidr', 'reason', 'created_by'];

    /**
     * Does $ip fall within this entry (exact match or CIDR containment, v4/v6)?
     */
    public function matches(string $ip): bool
    {
        return self::ipMatchesCidr($ip, $this->cidr);
    }

    /** Exact match, or CIDR containment for both IPv4 and IPv6. */
    public static function ipMatchesCidr(string $ip, string $cidr): bool
    {
        $ip = trim($ip);
        $cidr = trim($cidr);
        if ($ip === '' || $cidr === '') {
            return false;
        }
        if (! str_contains($cidr, '/')) {
            return inet_pton($ip) !== false && inet_pton($cidr) !== false && inet_pton($ip) === inet_pton($cidr);
        }

        [$subnet, $bitsRaw] = explode('/', $cidr, 2);
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false; // v4-vs-v6 mismatch or malformed
        }
        $bits = (int) $bitsRaw;
        $maxBits = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        $rem = $bits % 8;
        if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = chr(0xFF << (8 - $rem) & 0xFF);

        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in a partner's contact log: what was said, and when it happened.
 *
 * `occurred_at` is separate from `created_at` because a call is logged after it
 * ends — sometimes days after. Sorting by the typing time would put the history
 * in the order it was remembered rather than the order it happened.
 *
 * @property int $id
 * @property int $user_id
 * @property int $finance_partner_id
 * @property string $kind call|meeting|mail|note
 * @property string $body
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FinancePartnerNote extends Model
{
    use OwnsUserData;

    /** What kind of contact it was. Free text would not group or filter. */
    public const KINDS = ['call', 'meeting', 'mail', 'note'];

    protected $fillable = ['finance_partner_id', 'kind', 'body', 'occurred_at'];

    protected $casts = [
        'finance_partner_id' => 'integer',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<FinancePartner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(FinancePartner::class, 'finance_partner_id');
    }
}

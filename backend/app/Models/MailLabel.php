<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A user's colored mail label — mutable metadata attached to the otherwise
 * immutable archive via the mail_label_message pivot. Owner-scoped.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $color
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class MailLabel extends Model
{
    use AssignsOwner;

    protected $fillable = ['name', 'color'];

    /** @return BelongsToMany<MailMessage, $this> */
    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(MailMessage::class, 'mail_label_message', 'mail_label_id', 'mail_message_id');
    }
}

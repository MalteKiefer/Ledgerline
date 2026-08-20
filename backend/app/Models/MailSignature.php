<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsOwner;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'html'])]
class MailSignature extends Model
{
    use AssignsOwner;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<MailAccount, $this> */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(MailAccount::class, 'mail_account_signatures')
            ->withPivot('is_default');
    }
}

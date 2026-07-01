<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A team: the unit of data ownership and isolation.
 *
 * Teams mirror Pocket-ID groups (key "group:<id>"); a user without any group
 * gets a personal team (key "user:<id>"). Members only ever see records
 * belonging to their teams.
 */
#[Fillable(['key', 'name'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * Turn a Pocket-ID group slug into a readable name
     * ("kiefer_networks" -> "Kiefer Networks"). Idempotent.
     */
    public static function humanise(string $value): string
    {
        return Str::of($value)->replace(['_', '-'], ' ')->squish()->title()->toString();
    }

    /**
     * A readable display name, humanised regardless of what is stored, so old
     * slug-named teams still render nicely.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => self::humanise((string) $this->name));
    }

    /**
     * The members of this team.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}

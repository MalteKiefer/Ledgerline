<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Concerns\BelongsToTeam;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A project belonging to a customer.
 *
 * Type, priority and status are cast to enums, dates to date objects and the
 * budget to a fixed-precision decimal. The owning customer_id is not mass-
 * assignable; it is set explicitly from the form/route. Free tags are attached
 * many-to-many.
 */
#[Fillable([
    'name',
    'reference',
    'type',
    'priority',
    'status',
    'description',
    'start_date',
    'end_date',
    'budget',
    'estimated_hours',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'priority' => ProjectPriority::class,
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'decimal:2',
            'estimated_hours' => 'decimal:2',
        ];
    }

    /**
     * The customer this project belongs to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The free tags attached to this project.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * The files attached to this project.
     *
     * @return MorphMany<File, $this>
     */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'attachable');
    }
}

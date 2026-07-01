<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A multilingual unit type for line items (e.g. hour, day, piece). Carries a
 * UN/ECE code used for e-invoices (ZUGFeRD).
 */
#[Fillable(['code', 'name_de', 'name_en', 'zugferd_code'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /**
     * Cached map of code => unit, for cheap per-line resolution.
     *
     * @var Collection<string, Unit>|null
     */
    private static ?Collection $cache = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::$cache = null);
        static::deleted(fn () => self::$cache = null);
    }

    /**
     * The label in the given (or current) locale.
     */
    public function label(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'de' ? $this->name_de : $this->name_en;
    }

    /**
     * Resolve a stored unit code to its label, falling back to the code itself.
     */
    public static function labelFor(?string $code, ?string $locale = null): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        $unit = (self::$cache ??= self::query()->get()->keyBy('code'))->get($code);

        return $unit?->label($locale) ?? $code;
    }

    /**
     * The ZUGFeRD (UN/ECE) code for a stored unit code, defaulting to C62.
     */
    public static function zugferdCodeFor(?string $code): string
    {
        if ($code === null || $code === '') {
            return 'C62';
        }

        return (self::$cache ??= self::query()->get()->keyBy('code'))->get($code)?->zugferd_code ?? 'C62';
    }
}

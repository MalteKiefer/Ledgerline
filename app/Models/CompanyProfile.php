<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The single, global company profile: the sender identity used on invoices.
 *
 * There is only ever one row; use current() to fetch (or lazily create) it.
 */
#[Fillable([
    'legal_name',
    'address_line1',
    'address_line2',
    'postal_code',
    'city',
    'country',
    'vat_id',
    'tax_number',
    'register_court',
    'register_number',
    'managing_director',
    'email',
    'phone',
    'website',
    'iban',
    'bic',
    'bank_name',
    'logo_path',
    'small_business',
    'default_language',
    'default_currency',
    'default_tax_rate',
    'tax_display',
    'paper_size',
    'gallery_trip_gap_days',
    'gallery_trip_radius_km',
    'gallery_filename_template',
    'gallery_map_zoom',
    'invoice_number_prefix',
    'invoice_number_next',
    'invoice_number_pad',
    'payment_terms_days',
    'invoice_footer_text',
])]
class CompanyProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'small_business' => 'boolean',
            'default_tax_rate' => 'integer',
            'invoice_number_next' => 'integer',
            'invoice_number_pad' => 'integer',
            'payment_terms_days' => 'integer',
            'gallery_trip_gap_days' => 'integer',
            'gallery_trip_radius_km' => 'integer',
            'gallery_map_zoom' => 'integer',
        ];
    }

    /**
     * The company profile, creating an empty one on first use.
     */
    public static function current(): self
    {
        return static::query()->firstOr(fn (): self => static::create(['legal_name' => 'My Company']));
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's personal preferences (Paperless, gallery, files, theme).
 * One row per user; use for() to fetch (or lazily create) the current user's
 * row. Infra/workspace settings live on AppSettings instead.
 *
 * @property ?string $company_name
 * @property ?string $company_address
 * @property ?string $company_email
 * @property ?string $company_phone
 * @property ?string $company_tax_id
 * @property ?string $company_vat_id
 * @property ?string $company_iban
 * @property ?string $company_bic
 * @property ?string $company_bank_name
 * @property ?string $company_logo_path
 * @property ?string $invoice_number_prefix
 * @property int $invoice_number_padding
 * @property ?string $invoice_number_format
 * @property int $invoice_next_number
 * @property ?string $invoice_default_vat_rate
 * @property bool $small_business
 * @property int $invoice_payment_terms_days
 * @property ?string $invoice_footer_text
 * @property ?string $invoice_accent_color
 * @property ?string $invoice_heading_color
 * @property ?string $invoice_template
 * @property ?string $invoice_payment_methods
 * @property ?string $invoice_payment_terms_text
 * @property bool $company_smtp_enabled
 * @property ?string $company_smtp_host
 * @property ?int $company_smtp_port
 * @property ?string $company_smtp_encryption
 * @property ?string $company_smtp_username
 * @property ?string $company_smtp_password
 * @property ?string $company_smtp_from_address
 * @property ?string $company_smtp_from_name
 */
#[Fillable([
    'user_id',
    'paperless_enabled',
    'paperless_url',
    'paperless_token',
    'paperless_synced_at',
    'gallery_columns',
    'file_max_versions',
    'theme',
    'unit_distance',
    'unit_elevation',
    'unit_weight',
    'unit_temp',
    'unit_glucose',
    'time_format',
    // Per-user company identity + invoice numbering (formerly workspace-global).
    'company_name', 'company_address', 'company_email', 'company_phone', 'company_tax_id',
    'company_vat_id', 'company_iban', 'company_bic', 'company_bank_name', 'company_logo_path',
    'invoice_number_prefix', 'invoice_number_padding', 'invoice_number_format', 'invoice_next_number',
    'invoice_default_vat_rate', 'small_business', 'invoice_payment_terms_days', 'invoice_footer_text',
    'invoice_accent_color', 'invoice_heading_color', 'invoice_template',
    'invoice_payment_methods', 'invoice_payment_terms_text',
    // Per-user COMPANY SMTP — a dedicated transport for sending invoices,
    // separate from the workspace notification SMTP (AppSettings). Password is
    // an operational secret (encrypted cast); never a fillable plaintext leak.
    'company_smtp_enabled', 'company_smtp_host', 'company_smtp_port', 'company_smtp_encryption',
    'company_smtp_username', 'company_smtp_password', 'company_smtp_from_address', 'company_smtp_from_name',
])]
class UserSetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    /** In-memory defaults so a freshly-created row reads correctly without a reload. */
    protected $attributes = [
        'paperless_enabled' => false,
        'small_business' => false,
        'gallery_columns' => 6,
        'file_max_versions' => 10,
        'theme' => 'system',
        'unit_distance' => 'km',
        'unit_elevation' => 'm',
        'unit_weight' => 'kg',
        'unit_temp' => 'c',
        'unit_glucose' => 'mgdl',
        'time_format' => '24h',
    ];

    /**
     * The non-secret display preferences as a flat map for injection into the page
     * and the API (window.LLPrefs / GET /me). Presentation only — never data.
     *
     * @return array{distance:string, elevation:string, weight:string, temp:string, glucose:string, time_format:string}
     */
    public function displayPrefs(): array
    {
        return [
            'distance' => (string) ($this->unit_distance ?? 'km'),
            'elevation' => (string) ($this->unit_elevation ?? 'm'),
            'weight' => (string) ($this->unit_weight ?? 'kg'),
            'temp' => (string) ($this->unit_temp ?? 'c'),
            'glucose' => (string) ($this->unit_glucose ?? 'mgdl'),
            'time_format' => (string) ($this->time_format ?? '24h'),
        ];
    }

    protected function casts(): array
    {
        return [
            'paperless_enabled' => 'boolean',
            'paperless_url' => 'encrypted',
            'paperless_token' => 'encrypted',
            'paperless_synced_at' => 'datetime',
            'company_smtp_enabled' => 'boolean',
            'company_smtp_port' => 'integer',
            'company_smtp_password' => 'encrypted',
            'gallery_columns' => 'integer',
            'file_max_versions' => 'integer',
            'invoice_number_padding' => 'integer',
            'invoice_next_number' => 'integer',
            'invoice_payment_terms_days' => 'integer',
            'invoice_default_vat_rate' => 'decimal:2',
            'small_business' => 'boolean',
        ];
    }

    /** The settings row for a user, creating defaults on first use. Memoised in
     *  the container (per-request in prod, reset between tests) since the layout
     *  and nav read the same row several times per page; update() mutates the
     *  cached instance in place. */
    public static function for(int $userId): self
    {
        $key = 'memo.user_setting.'.$userId;
        if (! app()->bound($key)) {
            app()->instance($key, static::query()->firstOrCreate(['user_id' => $userId]));
        }

        $setting = app($key);

        return $setting instanceof self ? $setting : static::query()->firstOrCreate(['user_id' => $userId]);
    }
}

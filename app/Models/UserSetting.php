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
 * @property ?string $company_website
 * @property ?array<int,array<string,string>> $company_contacts
 * @property ?string $invoice_number_prefix
 * @property int $invoice_number_padding
 * @property ?string $invoice_number_format
 * @property int $invoice_next_number
 * @property ?string $invoice_default_vat_rate
 * @property int $invoice_payment_terms_days
 * @property ?string $invoice_footer_text
 * @property ?string $invoice_accent_color
 * @property ?string $invoice_heading_color
 * @property ?string $invoice_template
 * @property ?string $invoice_font
 * @property ?string $invoice_payment_methods
 * @property ?string $invoice_payment_terms_text
 * @property bool $invoice_mail_enabled
 * @property bool $invoice_vat_ist
 * @property ?string $invoice_smtp_host
 * @property ?int $invoice_smtp_port
 * @property ?string $invoice_smtp_encryption
 * @property ?string $invoice_smtp_username
 * @property ?string $invoice_smtp_password
 * @property ?string $invoice_from_email
 * @property ?string $invoice_from_name
 * @property ?string $invoice_mail_subject
 * @property ?string $invoice_mail_body
 * @property ?string $invoice_mail_signature
 */
#[Fillable([
    'user_id',
    'paperless_enabled',
    'paperless_url',
    'paperless_token',
    'paperless_synced_at',
    'file_max_versions',
    'theme',
    'contact_birthday_channels',
    'contact_anniversary_channels',
    'unit_distance',
    'unit_elevation',
    'unit_weight',
    'unit_temp',
    'unit_glucose',
    'time_format',
    'mail_load_remote',
    'mail_allow_scripts',
    'cal_week_numbers',
    'cal_week_start',
    'cal_default_view',
    'cal_day_start',
    'cal_day_end',
    // Per-user company identity + invoice numbering (formerly workspace-global).
    'company_name', 'company_address', 'company_email', 'company_phone', 'company_tax_id',
    'company_vat_id', 'company_iban', 'company_bic', 'company_bank_name', 'company_logo_path',
    'company_website', 'company_contacts',
    'invoice_number_prefix', 'invoice_number_padding', 'invoice_number_format', 'invoice_next_number',
    'invoice_default_vat_rate', 'invoice_payment_terms_days', 'invoice_footer_text',
    'invoice_accent_color', 'invoice_heading_color', 'invoice_template',
    'invoice_payment_methods', 'invoice_payment_terms_text',
    'invoice_font', 'invoice_vat_ist',
    'invoice_mail_enabled', 'invoice_smtp_host', 'invoice_smtp_port', 'invoice_smtp_encryption',
    'invoice_smtp_username', 'invoice_smtp_password', 'invoice_from_email', 'invoice_from_name',
    'invoice_mail_subject', 'invoice_mail_body', 'invoice_mail_signature',
])]
class UserSetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    /** In-memory defaults so a freshly-created row reads correctly without a reload. */
    protected $attributes = [
        'paperless_enabled' => false,
        'file_max_versions' => 10,
        'theme' => 'system',
        'unit_distance' => 'km',
        'unit_elevation' => 'm',
        'unit_weight' => 'kg',
        'unit_temp' => 'c',
        'unit_glucose' => 'mgdl',
        'time_format' => '24h',
        'cal_week_numbers' => false,
        'cal_week_start' => 'mon',
        'cal_default_view' => 'month',
        'cal_day_start' => 8,
        'cal_day_end' => 17,
    ];

    /**
     * The non-secret display preferences as a flat map for injection into the page
     * and the API (window.LLPrefs / GET /me). Presentation only — never data.
     *
     * @return array{distance:string, elevation:string, weight:string, temp:string, glucose:string, time_format:string, mail_remote:bool, mail_scripts:bool, cal_week_numbers:bool, cal_week_start:string, cal_default_view:string, cal_day_start:int, cal_day_end:int}
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
            'mail_remote' => (bool) ($this->mail_load_remote ?? false),
            'mail_scripts' => (bool) ($this->mail_allow_scripts ?? false),
            'cal_week_numbers' => (bool) ($this->cal_week_numbers ?? false),
            'cal_week_start' => (string) ($this->cal_week_start ?? 'mon'),
            'cal_default_view' => (string) ($this->cal_default_view ?? 'month'),
            'cal_day_start' => (int) ($this->cal_day_start ?? 8),
            'cal_day_end' => (int) ($this->cal_day_end ?? 17),
        ];
    }

    protected function casts(): array
    {
        return [
            'paperless_enabled' => 'boolean',
            'paperless_url' => 'encrypted',
            'paperless_token' => 'encrypted',
            'paperless_synced_at' => 'datetime',
            'file_max_versions' => 'integer',
            'contact_birthday_channels' => 'array',
            'contact_anniversary_channels' => 'array',
            'invoice_number_padding' => 'integer',
            'invoice_next_number' => 'integer',
            'invoice_payment_terms_days' => 'integer',
            'invoice_default_vat_rate' => 'decimal:2',
            'company_contacts' => 'array',
            'mail_load_remote' => 'boolean',
            'mail_allow_scripts' => 'boolean',
            'cal_week_numbers' => 'boolean',
            'cal_day_start' => 'integer',
            'cal_day_end' => 'integer',
            'invoice_mail_enabled' => 'boolean',
            'invoice_vat_ist' => 'boolean',
            'invoice_smtp_port' => 'integer',
            'invoice_smtp_host' => 'encrypted',
            'invoice_smtp_username' => 'encrypted',
            'invoice_smtp_password' => 'encrypted',
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

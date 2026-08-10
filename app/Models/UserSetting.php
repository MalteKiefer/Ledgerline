<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's personal preferences (Paperless, files, theme).
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
 * @property bool $invoice_vat_ist
 * @property ?string $invoice_font
 * @property ?string $company_website
 * @property array<int, array<string, string>>|null $company_contacts
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
 * @property ?string $mail_signature
 * @property array<string, mixed>|null $notification_prefs
 */
#[Fillable([
    'user_id',
    'paperless_enabled',
    'paperless_url',
    'paperless_token',
    'paperless_synced_at',
    'file_max_versions',
    'theme',
    'unit_distance',
    'unit_elevation',
    'unit_weight',
    'unit_temp',
    'unit_glucose',
    'time_format',
    'calendar_default_view',
    'calendar_week_start',
    // Mail archive reader display prefs (both default OFF): load remote content
    // (tracking-pixel protection) + allow scripts in the sandboxed body iframe.
    'mail_load_remote',
    'mail_allow_scripts',
    // Per-user plaintext mail signature appended to composed/reply/forward
    // bodies (non-secret presentation, not encrypted/hidden).
    'mail_signature',
    // Per-user, per-category push preferences: {"<category>": {"push": bool}}.
    'notification_prefs',
    // Per-user company identity + invoice numbering (formerly workspace-global).
    'company_name', 'company_address', 'company_email', 'company_phone', 'company_tax_id',
    'company_vat_id', 'company_iban', 'company_bic', 'company_bank_name', 'company_logo_path',
    'company_website', 'company_contacts',
    'invoice_number_prefix', 'invoice_number_padding', 'invoice_number_format', 'invoice_next_number',
    'invoice_default_vat_rate', 'small_business', 'invoice_vat_ist', 'invoice_font', 'invoice_payment_terms_days', 'invoice_footer_text',
    'invoice_accent_color', 'invoice_heading_color', 'invoice_template',
    'invoice_payment_methods', 'invoice_payment_terms_text',
    // Per-user COMPANY SMTP — a dedicated transport for sending invoices,
    // separate from the workspace notification SMTP (AppSettings). Password is
    // an operational secret (encrypted cast); never a fillable plaintext leak.
    'company_smtp_enabled', 'company_smtp_host', 'company_smtp_port', 'company_smtp_encryption',
    'company_smtp_username', 'company_smtp_password', 'company_smtp_from_address', 'company_smtp_from_name',
])]
// Defense-in-depth: the operative-secret columns (Paperless + company SMTP creds) are
// encrypted-cast, but that only protects them at rest. $hidden keeps them out of any
// wholesale toArray()/toJson() serialization too, so a future generic response can never
// leak them (the CompanyController API already whitelists its output — this backstops it).
#[Hidden([
    'paperless_url', 'paperless_token',
    'company_smtp_host', 'company_smtp_username', 'company_smtp_password',
    'company_smtp_from_address', 'company_smtp_from_name',
])]
class UserSetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    /** In-memory defaults so a freshly-created row reads correctly without a reload. */
    protected $attributes = [
        'paperless_enabled' => false,
        'small_business' => false,
        'invoice_vat_ist' => true,
        'file_max_versions' => 10,
        'theme' => 'system',
        'unit_distance' => 'km',
        'unit_elevation' => 'm',
        'unit_weight' => 'kg',
        'unit_temp' => 'c',
        'unit_glucose' => 'mgdl',
        'time_format' => '24h',
        'calendar_default_view' => 'month',
        'calendar_week_start' => 1,
        'mail_load_remote' => false,
        'mail_allow_scripts' => false,
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
            'mail_load_remote' => (bool) ($this->mail_load_remote ?? false),
            'notifications' => is_array($this->notification_prefs) ? $this->notification_prefs : [],
            'mail_signature' => $this->mail_signature !== null ? (string) $this->mail_signature : null,
        ];
    }

    /**
     * Whether push is enabled for a notification category. Default true — a user
     * only ever opts a category OUT; unknown/absent categories always push.
     */
    public function pushEnabled(string $category): bool
    {
        $prefs = is_array($this->notification_prefs) ? $this->notification_prefs : [];
        $cat = $prefs[$category] ?? null;

        return ! (is_array($cat) && array_key_exists('push', $cat) && $cat['push'] === false);
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
            // Encrypted at rest (parity with the workspace SMTP fields on AppSettings)
            // so an unencrypted DB dump does not leak the company mail endpoint/identity.
            'company_smtp_host' => 'encrypted',
            'company_smtp_username' => 'encrypted',
            'company_smtp_password' => 'encrypted',
            'company_smtp_from_address' => 'encrypted',
            'file_max_versions' => 'integer',
            'calendar_week_start' => 'integer',
            'mail_load_remote' => 'boolean',
            'mail_allow_scripts' => 'boolean',
            'notification_prefs' => 'array',
            'invoice_number_padding' => 'integer',
            'invoice_next_number' => 'integer',
            'invoice_payment_terms_days' => 'integer',
            'invoice_default_vat_rate' => 'decimal:2',
            'small_business' => 'boolean',
            'invoice_vat_ist' => 'boolean',
            'company_contacts' => 'array',
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

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The fixed set of functions (roles) a contact person can hold.
 *
 * This is a firmly integrated, reusable list: the backing string value is what
 * is stored in the database and referenced elsewhere (filtering, notification
 * routing, reporting). The human-readable label is for display only and is
 * never persisted.
 */
enum ContactFunction: string
{
    case CEO = 'CEO';
    case CTO = 'CTO';
    case CFO = 'CFO';
    case COO = 'COO';
    case MANAGING_DIRECTOR = 'MANAGING_DIRECTOR';
    case TECHNICAL_CONTACT = 'TECHNICAL_CONTACT';
    case FINANCE_CONTACT = 'FINANCE_CONTACT';
    case PROCUREMENT_CONTACT = 'PROCUREMENT_CONTACT';
    case PROJECT_MANAGER = 'PROJECT_MANAGER';
    case HELPDESK = 'HELPDESK';
    case SECURITY_CONTACT = 'SECURITY_CONTACT';
    case DATA_PROTECTION_OFFICER = 'DATA_PROTECTION_OFFICER';
    case SALES_CONTACT = 'SALES_CONTACT';
    case OTHER = 'OTHER';

    /**
     * Human-readable, English label for display in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::CEO => 'Chief Executive Officer',
            self::CTO => 'Chief Technology Officer',
            self::CFO => 'Chief Financial Officer',
            self::COO => 'Chief Operating Officer',
            self::MANAGING_DIRECTOR => 'Managing Director',
            self::TECHNICAL_CONTACT => 'Technical Contact',
            self::FINANCE_CONTACT => 'Finance / Billing Contact',
            self::PROCUREMENT_CONTACT => 'Procurement / Purchasing Contact',
            self::PROJECT_MANAGER => 'Project Manager',
            self::HELPDESK => 'Helpdesk / Support Contact',
            self::SECURITY_CONTACT => 'IT Security Contact',
            self::DATA_PROTECTION_OFFICER => 'Data Protection Officer',
            self::SALES_CONTACT => 'Sales Contact',
            self::OTHER => 'Other',
        };
    }

    /**
     * All cases as value/label pairs, suitable for selects and comboboxes.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}

<?php

declare(strict_types=1);

return [
    'invoice_status' => [
        'DRAFT' => 'Entwurf',
        'SENT' => 'Versendet',
        'PAID' => 'Bezahlt',
        'OVERDUE' => 'Überfällig',
        'CANCELLED' => 'Storniert',
    ],
    'invoice_type' => [
        'INVOICE' => 'Rechnung',
        'CREDIT_NOTE' => 'Gutschrift',
    ],
    'tax_mode' => [
        'STANDARD' => 'Regelbesteuerung',
        'REVERSE_CHARGE' => 'Reverse-Charge (EU)',
        'SMALL_BUSINESS' => 'Kleinunternehmer (§19 UStG)',
    ],
    'payment_status' => [
        'OPEN' => 'Offen',
        'PAID' => 'Bezahlt',
    ],
    'expense_category' => [
        'HARDWARE' => 'Hardware',
        'SOFTWARE' => 'Software',
        'SUBSCRIPTION' => 'Abonnement',
        'HOSTING' => 'Hosting',
        'TRAVEL' => 'Reisekosten',
        'OFFICE' => 'Büro',
        'MARKETING' => 'Marketing',
        'FEES' => 'Gebühren',
        'OTHER' => 'Sonstiges',
    ],
    'file_type' => [
        'IMAGE' => 'Bild',
        'PDF' => 'PDF',
        'DOCUMENT' => 'Dokument',
        'SPREADSHEET' => 'Tabelle',
        'ARCHIVE' => 'Archiv',
        'OTHER' => 'Sonstiges',
    ],
    'contact_function' => [
        'CEO' => 'Geschäftsführer (CEO)',
        'CTO' => 'Technischer Leiter (CTO)',
        'CFO' => 'Finanzleiter (CFO)',
        'COO' => 'Operativer Leiter (COO)',
        'MANAGING_DIRECTOR' => 'Geschäftsführer',
        'TECHNICAL_CONTACT' => 'Technischer Ansprechpartner',
        'FINANCE_CONTACT' => 'Ansprechpartner Finanzen / Rechnung',
        'PROCUREMENT_CONTACT' => 'Ansprechpartner Einkauf / Beschaffung',
        'PROJECT_MANAGER' => 'Projektleiter',
        'HELPDESK' => 'Helpdesk / Support',
        'SECURITY_CONTACT' => 'IT-Sicherheitskontakt',
        'DATA_PROTECTION_OFFICER' => 'Datenschutzbeauftragter',
        'SALES_CONTACT' => 'Vertriebskontakt',
        'OTHER' => 'Sonstiges',
    ],
    'project_type' => [
        'CONSULTING' => 'Beratung',
        'DEVELOPMENT' => 'Entwicklung',
        'NETWORK' => 'Netzwerk',
        'MAINTENANCE' => 'Wartung',
        'SUPPORT' => 'Support',
        'OTHER' => 'Sonstiges',
    ],
    'project_status' => [
        'PLANNED' => 'Geplant',
        'ACTIVE' => 'Aktiv',
        'ON_HOLD' => 'Pausiert',
        'COMPLETED' => 'Abgeschlossen',
        'CANCELLED' => 'Storniert',
    ],
    'project_priority' => [
        'LOW' => 'Niedrig',
        'NORMAL' => 'Normal',
        'HIGH' => 'Hoch',
        'URGENT' => 'Dringend',
    ],
];

<?php

declare(strict_types=1);

return [
    'invoice_status' => [
        'DRAFT' => 'Draft',
        'SENT' => 'Sent',
        'PAID' => 'Paid',
        'OVERDUE' => 'Overdue',
        'CANCELLED' => 'Cancelled',
    ],
    'invoice_type' => [
        'INVOICE' => 'Invoice',
        'CREDIT_NOTE' => 'Credit note',
    ],
    'tax_mode' => [
        'STANDARD' => 'Standard VAT',
        'REVERSE_CHARGE' => 'Reverse charge (EU)',
        'SMALL_BUSINESS' => 'Small business (§19 UStG)',
    ],
    'payment_status' => [
        'OPEN' => 'Open',
        'PAID' => 'Paid',
    ],
    'expense_category' => [
        'HARDWARE' => 'Hardware',
        'SOFTWARE' => 'Software',
        'SUBSCRIPTION' => 'Subscription',
        'HOSTING' => 'Hosting',
        'TRAVEL' => 'Travel',
        'OFFICE' => 'Office',
        'MARKETING' => 'Marketing',
        'FEES' => 'Fees',
        'OTHER' => 'Other',
    ],
    'file_type' => [
        'IMAGE' => 'Image',
        'PDF' => 'PDF',
        'DOCUMENT' => 'Document',
        'SPREADSHEET' => 'Spreadsheet',
        'ARCHIVE' => 'Archive',
        'OTHER' => 'Other',
    ],
    'contact_function' => [
        'CEO' => 'Chief Executive Officer',
        'CTO' => 'Chief Technology Officer',
        'CFO' => 'Chief Financial Officer',
        'COO' => 'Chief Operating Officer',
        'MANAGING_DIRECTOR' => 'Managing Director',
        'TECHNICAL_CONTACT' => 'Technical Contact',
        'FINANCE_CONTACT' => 'Finance / Billing Contact',
        'PROCUREMENT_CONTACT' => 'Procurement / Purchasing Contact',
        'PROJECT_MANAGER' => 'Project Manager',
        'HELPDESK' => 'Helpdesk / Support Contact',
        'SECURITY_CONTACT' => 'IT Security Contact',
        'DATA_PROTECTION_OFFICER' => 'Data Protection Officer',
        'SALES_CONTACT' => 'Sales Contact',
        'OTHER' => 'Other',
    ],
    'project_type' => [
        'CONSULTING' => 'Consulting',
        'DEVELOPMENT' => 'Development',
        'NETWORK' => 'Network',
        'MAINTENANCE' => 'Maintenance',
        'SUPPORT' => 'Support',
        'OTHER' => 'Other',
    ],
    'project_status' => [
        'PLANNED' => 'Planned',
        'ACTIVE' => 'Active',
        'ON_HOLD' => 'On hold',
        'COMPLETED' => 'Completed',
        'CANCELLED' => 'Cancelled',
    ],
    'project_priority' => [
        'LOW' => 'Low',
        'NORMAL' => 'Normal',
        'HIGH' => 'High',
        'URGENT' => 'Urgent',
    ],
];

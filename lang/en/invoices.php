<?php

declare(strict_types=1);

return [
    'title' => 'Invoices',
    'new' => 'New invoice',
    'search' => 'Search invoices',
    'filter_all' => 'All',
    'status_draft' => 'Draft',
    'status_sent' => 'Sent',
    'status_paid' => 'Paid',

    'empty_title' => 'No invoices yet',
    'empty_hint' => 'Create your first invoice to get started.',
    'save_failed' => 'Could not load invoices.',

    'col_number' => 'Number',
    'col_customer' => 'Customer',
    'col_date' => 'Date',
    'col_total' => 'Total',
    'col_status' => 'Status',
    'col_actions' => 'Actions',
    'draft_label' => 'Draft (no number)',

    'back' => 'Back',
    'customer' => 'Customer',
    'choose_customer' => 'Choose from contacts',
    'no_customer' => 'No customer selected',
    'clear_customer' => 'Clear',
    'customer_name' => 'Name',
    'customer_address' => 'Address',
    'customer_email' => 'Email',
    'customer_vat' => 'VAT ID',

    'issue_date' => 'Issue date',
    'due_date' => 'Due date',

    'lines' => 'Line items',
    'line_desc' => 'Description',
    'line_qty' => 'Qty',
    'line_unit' => 'Unit',
    'line_price' => 'Unit price',
    'line_vat' => 'VAT %',
    'add_line' => 'Add line',
    'remove' => 'Remove',
    'csv_import' => 'Import CSV',
    'csv_hint' => 'Clockify detailed report — fills Start Date, Description and hours.',
    'csv_imported' => ':n lines imported.',
    'csv_bad_format' => 'Could not read the CSV (need Description and Duration (decimal) columns).',

    'net' => 'Net',
    'vat' => 'VAT',
    'vat_at' => 'VAT :rate%',
    'gross' => 'Total',

    'note' => 'Note',
    'footer' => 'Footer',

    'finalize' => 'Finalize & assign number',
    'mark_sent' => 'Mark as sent',
    'mark_paid' => 'Mark as paid',
    'print' => 'Print / PDF',
    'trash' => 'Move to trash',
    'restore' => 'Restore',
    'delete' => 'Delete permanently',
    'delete_confirm' => 'Delete this invoice permanently?',

    'company_missing' => 'Set up your company profile in Settings to number and brand invoices.',

    'picker_title' => 'Pick a customer',
    'picker_search' => 'Search contacts',
    'picker_empty' => 'No contacts found.',

    'language' => 'Language',
    'currency' => 'Currency',
    'attn' => 'Attn (contact person)',

    // Print / PDF sheet
    'print_title' => 'Invoice',
    'bill_to' => 'Bill to',
    'invoice_number' => 'Invoice no.',
    'invoice_date' => 'Date',
    'due' => 'Due',
    'amount' => 'Amount',
    'subtotal' => 'Subtotal',
    'tax_heading' => 'Tax',
    'taxable' => 'Taxable',
    'tax_amount' => 'Tax amount',
    'notes_heading' => 'Notes',
    'payment_terms_heading' => 'Payment terms',
    'payment_methods_heading' => 'Payment methods',
    'bank_details' => 'Bank details',
    'vat_id_label' => 'VAT ID',
];

<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Validates an invoice update (drafts only). Mirrors StoreInvoiceRequest.
 */
class UpdateInvoiceRequest extends StoreInvoiceRequest {}

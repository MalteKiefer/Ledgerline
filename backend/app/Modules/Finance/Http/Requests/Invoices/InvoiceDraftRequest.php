<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Invoices;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use Illuminate\Foundation\Http\FormRequest;

final class InvoiceDraftRequest extends FormRequest
{
    use BuildsInvoiceDraftData;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = $this->draftRules();

        if ($this->routeIs('api.finance-v2.invoices.update')) {
            $rules['version'] = ['required', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function draft(): InvoiceDraftData
    {
        return $this->buildDraft($this->validated());
    }

    public function expectedVersion(): int
    {
        return $this->integer('version');
    }
}

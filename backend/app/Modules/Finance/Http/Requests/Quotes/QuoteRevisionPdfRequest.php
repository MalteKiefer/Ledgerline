<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Quotes;

use Illuminate\Foundation\Http\FormRequest;

final class QuoteRevisionPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'download' => ['sometimes', 'boolean'],
        ];
    }

    public function wantsDownload(): bool
    {
        return $this->boolean('download');
    }
}

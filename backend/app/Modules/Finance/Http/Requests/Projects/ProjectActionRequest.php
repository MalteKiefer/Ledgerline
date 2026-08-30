<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

final class ProjectActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['version' => ['required', 'integer', 'min:0']];
        if ($this->routeIs('api.finance-v2.projects.status')) {
            $rules['status'] = ['required', 'string', 'in:planned,active,on_hold,done,cancelled'];
        }
        if ($this->routeIs('api.finance-v2.projects.move')) {
            $rules['parent_id'] = ['nullable', 'uuid'];
        }

        return $rules;
    }

    public function expectedVersion(): int
    {
        $value = $this->validated('version');
        if (! is_int($value) && ! is_string($value)) {
            throw new \LogicException('Validated project version is not an integer.');
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (! is_int($parsed)) {
            throw new \LogicException('Validated project version is outside the supported integer range.');
        }

        return $parsed;
    }

    public function status(): string
    {
        $value = $this->validated('status');
        if (! is_string($value)) {
            throw new \LogicException('Validated project status is not a string.');
        }

        return $value;
    }

    public function parentUuid(): ?string
    {
        $value = $this->validated('parent_id');
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new \LogicException('Validated parent UUID is not a string.');
        }

        return $value;
    }
}

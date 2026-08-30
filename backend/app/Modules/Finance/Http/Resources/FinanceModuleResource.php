<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Domain\Shared\FinanceModule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FinanceModuleResource extends JsonResource
{
    /** @var null */
    public static $wrap = null;

    /**
     * @return array{module: 'finance', schemaVersion: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'module' => 'finance',
            'schemaVersion' => FinanceModule::SCHEMA_VERSION,
        ];
    }
}

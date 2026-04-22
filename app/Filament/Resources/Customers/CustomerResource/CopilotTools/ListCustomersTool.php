<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\CustomerResource\CopilotTools;

use App\Filament\Resources\Customers\CustomerResource;
use EslamRedaDiv\FilamentCopilot\Tools\BaseTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListCustomersTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'List Customers with pagination.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->description('Page number (default: 1)'),
            'per_page' => $schema->integer()->description('Items per page (default: 15, max: 50)'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $page = max(1, (int) ($request['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($request['per_page'] ?? 15)));

        $model = CustomerResource::getModel();
        $records = $model::query()->paginate($perPage, ['*'], 'page', $page);

        if ($records->isEmpty()) {
            return 'No Customers found.';
        }

        $lines = [
            'Customers — Page '.$records->currentPage().' of '.$records->lastPage().' ('.$records->total().' total)',
            '',
        ];

        foreach ($records as $record) {
            $attrs = collect($record->toArray())
                ->reject(fn ($v) => is_array($v) || is_null($v))
                ->map(fn ($v, $k) => "{$k}: ".(is_string($v) ? mb_substr($v, 0, 80) : $v))
                ->implode(', ');
            $lines[] = "- #{$record->getKey()}: {$attrs}";
        }

        return implode("\n", $lines);
    }
}

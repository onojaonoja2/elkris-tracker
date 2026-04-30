<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\CustomerResource\CopilotTools;

use App\Filament\Resources\Customers\CustomerResource;
use EslamRedaDiv\FilamentCopilot\Tools\BaseTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ViewCustomerTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'View a single Customer by its ID.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The ID of the Customer to view')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $model = CustomerResource::getModel();
        $record = $model::find($request['id']);

        if (! $record) {
            return 'Customer #'.$request['id'].' not found.';
        }

        $lines = ['Customer #'.$record->getKey().':', ''];

        foreach ($record->toArray() as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $lines[] = "  {$key}: {$value}";
        }

        return implode("\n", $lines);
    }
}

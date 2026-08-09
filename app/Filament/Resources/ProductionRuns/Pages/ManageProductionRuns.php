<?php

namespace App\Filament\Resources\ProductionRuns\Pages;

use App\Filament\Resources\ProductionRuns\ProductionRunResource;
use App\Models\ProductionRun;
use App\Services\ProductionRunService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProductionRuns extends ManageRecords
{
    protected static string $resource = ProductionRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['created_by'] = auth()->id();

                    return $data;
                })
                ->using(fn (array $data): ProductionRun => ProductionRunService::create($data)),
        ];
    }
}

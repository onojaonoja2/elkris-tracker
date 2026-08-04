<?php

namespace App\Filament\Resources\ProductionRuns\Actions;

use App\Models\ProductionRun;
use App\Services\ProductionRunService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

class ReviewProductionRunAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->name('reviewProductionRun')
            ->label('Review')
            ->icon('heroicon-o-check-badge')
            ->color('warning')
            ->visible(fn (ProductionRun $record): bool => $this->canReview($record))
            ->schema([
                Select::make('status')
                    ->label('Review Decision')
                    ->options([
                        'reviewed' => 'Reviewed / Approved',
                        'flagged' => 'Flagged',
                    ])
                    ->required(),

                Textarea::make('accountant_notes')
                    ->label('Accountant Notes')
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (array $data, ProductionRun $record): void {
                ProductionRunService::review($record, $data, auth()->id());
            });
    }

    protected function canReview(ProductionRun $record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($record->isLocked()) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'general_accountant', 'accountant']);
    }
}

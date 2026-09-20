<?php

namespace App\Filament\Widgets;

use App\Models\ProductionRun;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ProductionRunsWidget extends TableWidget
{
    protected static ?string $heading = 'Items Produced';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole([
            'admin',
            'production_management',
            'manager',
            'general_manager',
            'accountant',
            'general_accountant',
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ProductionRun::query()
                ->with(['creator', 'accountantReviewer', 'productType'])
                ->latest('production_date'))
            ->columns([
                TextColumn::make('id')
                    ->label('Run #')
                    ->sortable(),
                TextColumn::make('production_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('output_name')
                    ->label('Output')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('productType.name')
                    ->label('Product')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('finished_quantity')
                    ->label('Finished Qty')
                    ->numeric()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'reviewed' => 'success',
                        'flagged' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),
                TextColumn::make('creator.name')
                    ->label('Recorded By')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending_review' => 'Pending Review',
                        'reviewed' => 'Reviewed',
                        'flagged' => 'Flagged',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (ProductionRun $record): string => "Production Run #{$record->id}")
                    ->infolist([
                        TextEntry::make('production_date')
                            ->label('Production Date')
                            ->date(),
                        TextEntry::make('output_name')
                            ->label('Output'),
                        TextEntry::make('output_quantity')
                            ->label('Output Quantity'),
                        TextEntry::make('output_unit')
                            ->label('Output Unit'),
                        TextEntry::make('productType.name')
                            ->label('Finished Product')
                            ->placeholder('-'),
                        TextEntry::make('finished_quantity')
                            ->label('Finished Quantity (pieces)')
                            ->placeholder('-'),
                        TextEntry::make('materials')
                            ->label('Raw Materials Used')
                            ->getStateUsing(fn (ProductionRun $record): string => collect($record->getMaterialsSummary())
                                ->map(fn (array $material): string => "{$material['name']}: {$material['quantity_used']} {$material['unit']}")
                                ->implode(', ') ?: '-'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'reviewed' => 'success',
                                'flagged' => 'danger',
                                default => 'warning',
                            }),
                        TextEntry::make('creator.name')
                            ->label('Recorded By')
                            ->placeholder('-'),
                        TextEntry::make('accountantReviewer.name')
                            ->label('Reviewed By')
                            ->placeholder('Pending'),
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $records = $this->getFilteredTableQuery()->with(['creator', 'productType'])->get();

                        return response()->streamDownload(function () use ($records) {
                            $file = fopen('php://output', 'w');
                            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                            fputcsv($file, ['Run #', 'Date', 'Output', 'Product', 'Finished Qty', 'Status', 'Recorded By']);

                            foreach ($records as $record) {
                                fputcsv($file, [
                                    $record->id,
                                    $record->production_date?->format('d/m/Y'),
                                    $record->output_name,
                                    $record->productType?->name ?? '-',
                                    $record->finished_quantity ?? '-',
                                    str_replace('_', ' ', ucfirst($record->status)),
                                    $record->creator?->name ?? '-',
                                ]);
                            }

                            fclose($file);
                        }, 'production_runs_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->defaultSort('production_date', 'desc');
    }
}

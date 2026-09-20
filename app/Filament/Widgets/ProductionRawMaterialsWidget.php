<?php

namespace App\Filament\Widgets;

use App\Models\RawMaterial;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ProductionRawMaterialsWidget extends TableWidget
{
    protected static ?string $heading = 'Raw Materials';

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
            ->query(fn (): Builder => RawMaterial::query()->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('unit_of_measure')
                    ->label('Unit'),
                TextColumn::make('quantity')
                    ->label('Available')
                    ->numeric(4)
                    ->sortable(),
                TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (RawMaterial $record): string => $record->reorder_level !== null && $record->quantity <= $record->reorder_level ? 'Low Stock' : 'OK')
                    ->color(fn (string $state): string => $state === 'Low Stock' ? 'danger' : 'success'),
                TextColumn::make('reorder_level')
                    ->label('Reorder Level')
                    ->placeholder('-'),
            ])
            ->filters([
                Filter::make('low_stock')
                    ->label('Low Stock Only')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('reorder_level')
                        ->whereColumn('quantity', '<=', 'reorder_level')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (RawMaterial $record): string => $record->name)
                    ->infolist([
                        TextEntry::make('name'),
                        TextEntry::make('unit_of_measure')
                            ->label('Unit'),
                        TextEntry::make('quantity')
                            ->label('Available Quantity'),
                        TextEntry::make('reorder_level')
                            ->label('Reorder Level')
                            ->placeholder('-'),
                        TextEntry::make('is_active')
                            ->label('Active')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                    ]),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $records = $this->getFilteredTableQuery()->orderBy('name')->get();

                        return response()->streamDownload(function () use ($records) {
                            $file = fopen('php://output', 'w');
                            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                            fputcsv($file, ['Material', 'Unit', 'Available', 'Reorder Level', 'Status']);

                            foreach ($records as $record) {
                                $low = $record->reorder_level !== null && $record->quantity <= $record->reorder_level;

                                fputcsv($file, [
                                    $record->name,
                                    $record->unit_of_measure,
                                    $record->quantity,
                                    $record->reorder_level ?? '-',
                                    $low ? 'Low Stock' : 'OK',
                                ]);
                            }

                            fclose($file);
                        }, 'raw_materials_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}

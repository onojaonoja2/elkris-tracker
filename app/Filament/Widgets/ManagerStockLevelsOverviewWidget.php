<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ManagerStockLevelsOverviewWidget extends TableWidget
{
    protected static ?string $heading = 'Stock Levels Overview';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }

    private function cartonsDisplay(Inventory $record): string
    {
        $perCarton = $record->productType?->cartonQuantityFor($record->grammage) ?? 1;

        return number_format(intdiv($record->quantity, $perCarton)).' ctns + '.number_format($record->quantity % $perCarton).' pcs';
    }

    private function warehouseType(Inventory $record): string
    {
        return $record->warehouse?->type === 'central' ? 'Central Warehouse' : 'State Warehouse';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Inventory::query()
                ->where('quantity', '>', 0)
                ->with(['warehouse', 'productType'])
                ->orderByDesc('quantity'))
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Unknown'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->getStateUsing(fn (Inventory $record): string => $this->warehouseType($record))
                    ->color(fn (string $state): string => $state === 'Central Warehouse' ? 'warning' : 'info'),
                TextColumn::make('productType.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Unknown'),
                TextColumn::make('grammage')
                    ->label('Grammage')
                    ->formatStateUsing(fn ($state): string => number_format($state).'g')
                    ->sortable(),
                TextColumn::make('cartons')
                    ->label('Cartons')
                    ->getStateUsing(fn (Inventory $record): string => $this->cartonsDisplay($record)),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (Inventory $record): string => "Stock: {$record->productType?->name} at {$record->warehouse?->name}")
                    ->infolist([
                        TextEntry::make('warehouse.name')
                            ->label('Warehouse')
                            ->placeholder('Unknown'),
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->getStateUsing(fn (Inventory $record): string => $this->warehouseType($record))
                            ->color(fn (string $state): string => $state === 'Central Warehouse' ? 'warning' : 'info'),
                        TextEntry::make('warehouse.phone')
                            ->label('Warehouse Phone')
                            ->placeholder('-'),
                        TextEntry::make('productType.name')
                            ->label('Product')
                            ->placeholder('Unknown'),
                        TextEntry::make('grammage')
                            ->label('Grammage')
                            ->formatStateUsing(fn ($state): string => number_format($state).'g'),
                        TextEntry::make('cartons')
                            ->label('Cartons')
                            ->getStateUsing(fn (Inventory $record): string => $this->cartonsDisplay($record)),
                        TextEntry::make('quantity')
                            ->label('Quantity'),
                    ]),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $records = $this->getFilteredTableQuery()->with(['warehouse', 'productType'])->orderByDesc('quantity')->get();

                        return response()->streamDownload(function () use ($records) {
                            $file = fopen('php://output', 'w');
                            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                            fputcsv($file, ['Warehouse', 'Type', 'Product', 'Grammage (g)', 'Cartons', 'Quantity']);

                            foreach ($records as $record) {
                                fputcsv($file, [
                                    $record->warehouse?->name ?? 'Unknown',
                                    $this->warehouseType($record),
                                    $record->productType?->name ?? 'Unknown',
                                    $record->grammage,
                                    $this->cartonsDisplay($record),
                                    $record->quantity,
                                ]);
                            }

                            fclose($file);
                        }, 'stock_levels_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}

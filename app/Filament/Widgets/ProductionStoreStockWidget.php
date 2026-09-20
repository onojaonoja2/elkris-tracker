<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use App\Models\Warehouse;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ProductionStoreStockWidget extends TableWidget
{
    protected static ?string $heading = 'Production Store Stock';

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

    private function cartonsDisplay(Inventory $record): string
    {
        $perCarton = $record->productType?->cartonQuantityFor($record->grammage) ?? 1;

        return number_format(intdiv($record->quantity, $perCarton)).' ctns + '.number_format($record->quantity % $perCarton).' pcs';
    }

    public function table(Table $table): Table
    {
        $storeId = Warehouse::productionStore()?->id ?? 0;

        return $table
            ->query(fn (): Builder => Inventory::query()
                ->where('warehouse_id', $storeId)
                ->where('quantity', '>', 0)
                ->with(['warehouse', 'productType'])
                ->orderByDesc('quantity'))
            ->columns([
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
                    ->modalHeading(fn (Inventory $record): string => "Stock: {$record->productType?->name} ({$record->grammage}g)")
                    ->infolist([
                        TextEntry::make('warehouse.name')
                            ->label('Warehouse')
                            ->placeholder('Unknown'),
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
                            fputcsv($file, ['Warehouse', 'Product', 'Grammage (g)', 'Cartons', 'Quantity']);

                            foreach ($records as $record) {
                                fputcsv($file, [
                                    $record->warehouse?->name ?? 'Unknown',
                                    $record->productType?->name ?? 'Unknown',
                                    $record->grammage,
                                    $this->cartonsDisplay($record),
                                    $record->quantity,
                                ]);
                            }

                            fclose($file);
                        }, 'production_store_stock_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No stock in the production store');
    }
}

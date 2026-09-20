<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasBreakdownViewAction;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ProductionOutgoingTransfersWidget extends TableWidget
{
    use HasBreakdownViewAction;

    protected static ?string $heading = 'Production Dispatches';

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
        $storeId = Warehouse::productionStore()?->id ?? 0;

        return $table
            ->query(fn (): Builder => StockTransfer::query()
                ->where('from_warehouse_id', $storeId)
                ->with(['toWarehouse', 'toAgent', 'items.productType'])
                ->latest('created_at'))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('destination')
                    ->label('To')
                    ->getStateUsing(fn (StockTransfer $record): string => $record->toWarehouse?->name
                        ?? $record->toAgent?->name
                        ?? '-')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('toWarehouse', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('toAgent', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))),
                TextColumn::make('items')
                    ->label('Items')
                    ->getStateUsing(fn (StockTransfer $record): string => $record->items
                        ->map(fn ($item) => "{$item->quantity}x {$item->productType?->name} ({$item->grammage}g)")
                        ->implode(', '))
                    ->limit(50),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Dispatched')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                $this->breakdownViewAction(),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $records = $this->getFilteredTableQuery()->with(['toWarehouse', 'toAgent', 'items.productType'])->latest('created_at')->get();

                        return response()->streamDownload(function () use ($records) {
                            $file = fopen('php://output', 'w');
                            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                            fputcsv($file, ['Transfer #', 'To', 'Items', 'Status', 'Dispatched']);

                            foreach ($records as $record) {
                                $items = $record->items
                                    ->map(fn ($item) => "{$item->quantity}x {$item->productType?->name} ({$item->grammage}g)")
                                    ->implode('; ');

                                fputcsv($file, [
                                    $record->id,
                                    $record->toWarehouse?->name ?? $record->toAgent?->name ?? '-',
                                    $items,
                                    $record->status->value ?? $record->status,
                                    $record->created_at?->format('d/m/Y H:i'),
                                ]);
                            }

                            fclose($file);
                        }, 'production_dispatches_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->defaultSort('created_at', 'desc');
    }
}

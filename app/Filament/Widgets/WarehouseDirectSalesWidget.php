<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\On;

class WarehouseDirectSalesWidget extends BaseWidget
{
    protected static ?string $heading = 'My Direct Sales';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()?->canManageWarehouses() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => SalesRecord::query()
                ->where('agent_id', auth()->id())
                ->where('agent_type', 'warehouse_manager')
                ->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')
                    ->label('Sale #')
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('products')
                    ->label('Items')
                    ->formatStateUsing(fn ($products) => collect($products ?? [])->map(fn ($p) => "{$p['quantity']}x {$p['product_name']}")->implode(', '))
                    ->limit(50),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->placeholder('-'),
                TextColumn::make('total_value')
                    ->label('Total (₦)')
                    ->money('NGN'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('viewReceipt')
                    ->label('View Receipt')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->visible(fn (SalesRecord $record) => $record->receipt_path)
                    ->modalContent(fn (SalesRecord $record) => view('filament.sales-record-receipt', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->paginated([10, 25]);
    }
}

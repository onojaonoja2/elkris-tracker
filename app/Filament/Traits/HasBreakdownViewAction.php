<?php

namespace App\Filament\Traits;

use App\Models\DamagedStockReturn;
use App\Models\SalesRecord;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

trait HasBreakdownViewAction
{
    public function breakdownViewAction(): ViewAction
    {
        return ViewAction::make()
            ->label('View')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (?Model $record): string => $this->breakdownHeading($record))
            ->modalWidth('5xl')
            ->infolist(fn (?Model $record): array => $this->breakdownInfolist($record));
    }

    protected function breakdownHeading(?Model $record): string
    {
        return match (true) {
            $record instanceof StockTransfer => "Stock Request #{$record->id}",
            $record instanceof StockCount => "Stock Count #{$record->id}",
            $record instanceof SalesRecord => "Sales Record #{$record->id}",
            $record instanceof DamagedStockReturn => "Damaged Return #{$record->id}",
            $record instanceof User => 'Agent Breakdown',
            default => $record ? 'View '.class_basename($record) : 'View',
        };
    }

    /**
     * @return array<int, Fieldset|Section>
     */
    protected function breakdownInfolist(?Model $record): array
    {
        return match (true) {
            $record instanceof StockTransfer => $this->stockTransferBreakdown($record),
            $record instanceof StockCount => $this->stockCountBreakdown($record),
            $record instanceof SalesRecord => $this->salesRecordBreakdown($record),
            $record instanceof DamagedStockReturn => $this->damagedReturnBreakdown($record),
            $record instanceof User => $this->userBreakdown($record),
            default => [],
        };
    }

    /**
     * @return array<int, Fieldset|Section>
     */
    protected function stockTransferBreakdown(StockTransfer $record): array
    {
        $record->loadMissing(['requester', 'fromWarehouse', 'toAgent', 'dispatcher', 'items.productType']);

        return [
            Fieldset::make('Request Details')
                ->columns(4)
                ->schema([
                    TextEntry::make('id')->label('Transfer #')->state(fn (): string => "#{$record->id}"),
                    TextEntry::make('requester.name')->label('Requested By')->default('N/A'),
                    TextEntry::make('fromWarehouse.name')->label('From Warehouse')->default('N/A'),
                    TextEntry::make('toAgent.name')->label('To Agent')->default('N/A'),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('notes')->label('Notes')->placeholder('N/A'),
                    TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    TextEntry::make('supervisor_approved_at')->label('Supervisor Approved')->dateTime()->placeholder('N/A'),
                    TextEntry::make('approved_at')->label('Approved')->dateTime()->placeholder('N/A'),
                ]),
            Section::make('Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->schema([
                            TextEntry::make('productType.name')->label('Product')->default('N/A'),
                            TextEntry::make('grammage')->label('Grammage')->formatStateUsing(fn ($state): string => $state.'g'),
                            TextEntry::make('quantity')->label('Qty'),
                        ])
                        ->columns(3),
                ]),
        ];
    }

    /**
     * @return array<int, Fieldset|Section>
     */
    protected function stockCountBreakdown(StockCount $record): array
    {
        $record->loadMissing(['user', 'items']);

        return [
            Fieldset::make('Count Details')
                ->columns(4)
                ->schema([
                    TextEntry::make('user.name')->label('Agent'),
                    TextEntry::make('is_additional_count')
                        ->label('Type')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Additional' : 'Initial')
                        ->color(fn (bool $state): string => $state ? 'warning' : 'info'),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('notes')->label('Notes')->placeholder('N/A'),
                    TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    TextEntry::make('supervisor_verified_at')->label('Verified')->dateTime()->placeholder('N/A'),
                ]),
            Section::make('Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->schema([
                            TextEntry::make('product_name')->label('Product')->default('N/A'),
                            TextEntry::make('grammage')->label('Grammage')->formatStateUsing(fn ($state): string => $state.'g'),
                            TextEntry::make('quantity')->label('Qty'),
                        ])
                        ->columns(3),
                ]),
        ];
    }

    /**
     * @return array<int, Fieldset|Section>
     */
    protected function salesRecordBreakdown(SalesRecord $record): array
    {
        $record->loadMissing(['agent', 'collections.collector']);

        $sections = [
            Fieldset::make('Sale Details')
                ->columns(4)
                ->schema([
                    TextEntry::make('agent.name')->label('Agent')->default('N/A'),
                    TextEntry::make('agent_type')
                        ->label('Agent Type')
                        ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                    TextEntry::make('customer_name')->label('Customer')->placeholder('N/A'),
                    TextEntry::make('total_value')->label('Value')->money('NGN'),
                    TextEntry::make('is_credit')
                        ->label('Payment')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Credit' : 'Cash')
                        ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                    TextEntry::make('credit_status')->label('Credit Status')->placeholder('N/A'),
                    TextEntry::make('stock_source')
                        ->label('Stock Source')
                        ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                            'held' => 'At Hand',
                            'warehouse' => 'Warehouse',
                            default => null,
                        })
                        ->placeholder('N/A'),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('created_at')->label('Date')->dateTime(),
                    TextEntry::make('expected_collection_date')->label('Expected Collection')->date()->placeholder('N/A'),
                ]),
            Fieldset::make('Products')
                ->columns(1)
                ->schema([
                    TextEntry::make('products')
                        ->label('Products Sold')
                        ->formatStateUsing(function (mixed $state): string {
                            return collect($state ?? [])
                                ->filter(fn (mixed $product): bool => is_array($product))
                                ->map(fn (array $product): string => "{$product['quantity']}x {$product['product_name']} ({$product['grammage']}g) @ ₦{$product['price']}")
                                ->implode("\n");
                        })
                        ->listWithLineBreaks(),
                ]),
        ];

        if ($record->is_credit) {
            $sections[] = Fieldset::make('Credit Details')
                ->columns(3)
                ->schema([
                    TextEntry::make('outstanding_amount')
                        ->label('Outstanding (₦)')
                        ->state(fn (): string => number_format($record->outstandingAmount(), 2)),
                    TextEntry::make('collections_count')
                        ->label('Payments')
                        ->state(fn (): string => $record->collections->count().' payment(s)'),
                    TextEntry::make('collected_amount')
                        ->label('Collected (₦)')
                        ->state(fn (): string => number_format((float) $record->collections->sum('collected_amount'), 2)),
                    TextEntry::make('collected_at')->label('Collected On')->dateTime()->placeholder('N/A'),
                    TextEntry::make('credit_notes')->label('Credit Notes')->placeholder('N/A'),
                ]);

            $sections[] = Section::make('Collections')
                ->schema([
                    RepeatableEntry::make('collections')
                        ->schema([
                            TextEntry::make('collector.name')->label('Collected By')->default('N/A'),
                            TextEntry::make('collected_amount')->label('Amount')->money('NGN'),
                            TextEntry::make('collected_at')->label('Date')->dateTime(),
                            TextEntry::make('notes')->label('Notes')->placeholder('N/A'),
                        ])
                        ->columns(4),
                ]);
        }

        return $sections;
    }

    /**
     * @return array<int, Fieldset>
     */
    protected function damagedReturnBreakdown(DamagedStockReturn $record): array
    {
        $record->loadMissing(['user', 'warehouse', 'productType']);

        return [
            Fieldset::make('Return Details')
                ->columns(4)
                ->schema([
                    TextEntry::make('user.name')->label('Returned By'),
                    TextEntry::make('warehouse.name')->label('Warehouse')->default('N/A'),
                    TextEntry::make('productType.name')->label('Product')->default('N/A'),
                    TextEntry::make('grammage')->label('Weight')->formatStateUsing(fn ($state): string => $state.'g'),
                    TextEntry::make('quantity')->label('Qty'),
                    TextEntry::make('reason')->label('Reason')->placeholder('N/A'),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('created_at')->label('Date')->dateTime(),
                ]),
        ];
    }

    /**
     * @return array<int, Fieldset>
     */
    protected function userBreakdown(User $record): array
    {
        $stockUnits = $record->agentStocks()->sum('quantity');

        return [
            Fieldset::make('Agent Details')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Name'),
                    TextEntry::make('phone')->label('Phone')->placeholder('N/A'),
                    TextEntry::make('lga.name')->label('LGA')->default('N/A'),
                    TextEntry::make('state.name')->label('State')->default('N/A'),
                    TextEntry::make('is_active')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Suspended')
                        ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                    TextEntry::make('stock_units')
                        ->label('Stock Units')
                        ->state(fn (): int => $stockUnits),
                    TextEntry::make('created_at')->label('Joined')->dateTime(),
                ]),
        ];
    }
}

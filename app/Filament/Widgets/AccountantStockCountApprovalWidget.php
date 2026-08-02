<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\StockCount;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AccountantStockCountApprovalWidget extends BaseWidget
{
    protected static ?string $heading = 'Stock Count Final Approvals';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockCount::where('status', 'pending')
                    ->where('supervisor_status', 'verified')
                    ->with('user', 'items.productType')
            )
            ->columns([
                TextColumn::make('user.name')->label('Agent'),
                TextColumn::make('items_count')->label('Items')->counts('items'),
                TextColumn::make('supervisor_verified_at')->label('Supervisor Verified')->dateTime(),
                TextColumn::make('is_additional_count')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Additional' : 'Initial')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'info'),
            ])
            ->actions([
                Action::make('accountantApprove')
                    ->label('Final Approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->action(function (StockCount $record) {
                        \DB::transaction(function () use ($record) {
                            $record->update([
                                'status' => 'approved',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);

                            if ($record->is_additional_count) {
                                if ($record->warehouse_id) {
                                    foreach ($record->items as $item) {
                                        Inventory::firstOrCreate(
                                            [
                                                'warehouse_id' => $record->warehouse_id,
                                                'product_type_id' => $item->product_type_id,
                                                'grammage' => $item->grammage,
                                            ],
                                            ['quantity' => 0]
                                        )->increment('quantity', $item->quantity);
                                    }
                                } else {
                                    foreach ($record->items as $item) {
                                        AgentStock::firstOrCreate(
                                            [
                                                'user_id' => $record->user_id,
                                                'product_type_id' => $item->product_type_id,
                                                'product_name' => $item->product_name ?? $item->productType?->name ?? 'Unknown',
                                                'grammage' => $item->grammage,
                                            ],
                                            ['quantity' => 0]
                                        )->increment('quantity', $item->quantity);
                                    }
                                }
                            } else {
                                if ($record->warehouse_id) {
                                    foreach ($record->items as $item) {
                                        Inventory::updateOrCreate(
                                            [
                                                'warehouse_id' => $record->warehouse_id,
                                                'product_type_id' => $item->product_type_id,
                                                'grammage' => $item->grammage,
                                            ],
                                            ['quantity' => $item->quantity]
                                        );
                                    }
                                } else {
                                    foreach ($record->items as $item) {
                                        AgentStock::updateOrCreate(
                                            [
                                                'user_id' => $record->user_id,
                                                'product_type_id' => $item->product_type_id,
                                                'product_name' => $item->product_name ?? $item->productType?->name ?? 'Unknown',
                                                'grammage' => $item->grammage,
                                            ],
                                            ['quantity' => $item->quantity]
                                        );
                                    }
                                }
                            }
                        });

                        Notification::make()
                            ->title('Stock count approved')
                            ->success()
                            ->send();
                    }),
                Action::make('accountantReject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('rejection_reason')->required(),
                    ])
                    ->action(function (StockCount $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        Notification::make()
                            ->title('Stock count rejected')
                            ->danger()
                            ->send();
                    }),
            ]);
    }
}

<?php

namespace App\Filament\Resources\StockTransfers\Tables;

use App\Models\Inventory;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use App\Models\StockTransfer;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Transfer #')
                    ->sortable(),

                TextColumn::make('fromWarehouse.name')
                    ->label('From'),

                TextColumn::make('toWarehouse.name')
                    ->label('To Warehouse'),

                TextColumn::make('toStockist.name')
                    ->label('To Stockist'),

                TextColumn::make('items')
                    ->label('Items')
                    ->formatStateUsing(fn (StockTransfer $record): string => $record->items->map(
                        fn ($item) => ($item->productType?->name ?? '').' x'.$item->quantity
                    )->implode(', '))
                    ->limit(60),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'gray',
                        'dispatched' => 'warning',
                        'received' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('stockist_accepted_at')
                    ->label('Stockist Accepted')
                    ->dateTime()
                    ->placeholder('Pending'),

                TextColumn::make('dispatcher.name')
                    ->label('Dispatched By'),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->date(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->visible(fn (StockTransfer $record) => $record->status === 'draft'),

                Action::make('printDispatch')
                    ->label('Print Dispatch Note')
                    ->icon('o-printer')
                    ->color('primary')
                    ->action(function (StockTransfer $record) {
                        $pdf = Pdf::loadView('pdf.dispatch-note', ['transfer' => $record]);

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            "dispatch-note-{$record->id}.pdf"
                        );
                    }),

                Action::make('dispatch')
                    ->label('Dispatch')
                    ->icon('o-truck')
                    ->color('warning')
                    ->visible(fn (StockTransfer $record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->action(function (StockTransfer $record) {
                        $record->update([
                            'status' => 'dispatched',
                            'dispatched_by' => auth()->id(),
                        ]);
                        Notification::make()->title('Stock dispatched successfully')->success()->send();
                    }),

                Action::make('receive')
                    ->label('Mark Received')
                    ->icon('o-check-circle')
                    ->color('success')
                    ->visible(fn (StockTransfer $record) => $record->status === 'dispatched')
                    ->requiresConfirmation()
                    ->action(function (StockTransfer $record) {
                        $items = $record->items;

                        if ($record->to_warehouse_id) {
                            foreach ($items as $item) {
                                $inv = Inventory::firstOrCreate(
                                    [
                                        'warehouse_id' => $record->to_warehouse_id,
                                        'product_type_id' => $item->product_type_id,
                                        'grammage' => $item->grammage,
                                    ],
                                    ['quantity' => 0]
                                );
                                $inv->increment('quantity', $item->quantity);
                            }
                        }

                        if ($record->to_stockist_id) {
                            foreach ($items as $item) {
                                $stock = StockistStock::firstOrCreate(
                                    [
                                        'stockist_id' => $record->to_stockist_id,
                                        'product_name' => $item->productType?->name ?? 'Unknown',
                                        'grammage' => $item->grammage,
                                    ],
                                    ['quantity' => 0]
                                );
                                $stock->increment('quantity', $item->quantity);
                            }
                        }

                        if ($record->from_warehouse_id) {
                            foreach ($items as $item) {
                                $inv = Inventory::where([
                                    'warehouse_id' => $record->from_warehouse_id,
                                    'product_type_id' => $item->product_type_id,
                                    'grammage' => $item->grammage,
                                ])->first();
                                if ($inv) {
                                    $inv->decrement('quantity', $item->quantity);
                                }
                            }
                        }

                        $record->update([
                            'status' => 'received',
                            'received_by' => auth()->id(),
                            'received_at' => now(),
                        ]);

                        Notification::make()->title('Stock received successfully')->success()->send();
                    }),

                Action::make('stockistAccept')
                    ->label('Stockist Accept')
                    ->icon('o-hand-thumb-up')
                    ->color('success')
                    ->visible(fn (StockTransfer $record) => $record->status === 'received' && $record->to_stockist_id && ! $record->stockist_accepted_at)
                    ->requiresConfirmation()
                    ->modalHeading('Stockist Stock Acceptance')
                    ->modalDescription('Confirm that the stockist has received and accepted the stock. This will record the acceptance and update the stockist records.')
                    ->action(function (StockTransfer $record) {
                        $record->update([
                            'stockist_accepted_at' => now(),
                        ]);

                        // Create a stockist transaction record for acceptance
                        StockistTransaction::create([
                            'stockist_id' => $record->to_stockist_id,
                            'user_id' => auth()->id(),
                            'type' => 'received',
                            'amount' => 0,
                            'description' => "Stock transfer #{$record->id} accepted by stockist",
                            'transaction_date' => now()->toDateString(),
                        ]);

                        Notification::make()
                            ->title('Stockist acceptance recorded')
                            ->success()
                            ->send();
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->visible(fn (StockTransfer $record) => in_array($record->status, ['draft', 'dispatched']))
                    ->requiresConfirmation()
                    ->action(fn (StockTransfer $record) => $record->update(['status' => 'cancelled'])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

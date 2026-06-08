<?php

namespace App\Filament\Resources\StockTransfers\Tables;

use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->label('From')
                    ->state(fn (StockTransfer $record): ?string => $record->fromWarehouse?->name ?? $record->fromStockist?->name),

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

                TextColumn::make('rejected')
                    ->label('Rejections')
                    ->state(fn (StockTransfer $record): string => $record->items->where('rejected_quantity', '>', 0)
                        ->map(fn ($item) => ($item->productType?->name ?? '').' x'.$item->rejected_quantity
                            .($item->rejection_resolved_at ? ' (resolved)' : ''))
                        ->implode(', '))
                    ->placeholder('None')
                    ->limit(40),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'gray',
                        'requested' => 'info',
                        'approved' => 'primary',
                        'dispatched' => 'warning',
                        'received' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('requester.name')
                    ->label('Requested By')
                    ->placeholder('-'),

                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->placeholder('-'),

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
                SelectFilter::make('status')
                    ->options([
                        'requested' => 'Requested',
                        'approved' => 'Approved',
                        'dispatched' => 'Dispatched',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                        'draft' => 'Draft',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([

                // === REQUEST FLOW ACTIONS ===

                Action::make('requestFromStockist')
                    ->label('Create Stock Request')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('info')
                    ->visible(fn () => in_array(auth()->user()->role, ['supervisor', 'admin']))
                    ->form([
                        Select::make('stockist_id')
                            ->label('Stockist')
                            ->options(fn () => Stockist::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('from_warehouse_id', null)),
                        Select::make('from_warehouse_id')
                            ->label('From Warehouse')
                            ->options(function (callable $get) {
                                $stockistId = $get('stockist_id');
                                if (! $stockistId) {
                                    return Warehouse::pluck('name', 'id');
                                }
                                $stockist = Stockist::find($stockistId);

                                return $stockist?->state_id
                                    ? Warehouse::where('state_id', $stockist->state_id)->pluck('name', 'id')
                                    : Warehouse::pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->live(),
                        Repeater::make('items')
                            ->label('Stock Items')
                            ->schema([
                                Select::make('product_type_id')
                                    ->label('Product')
                                    ->options(fn () => ProductType::where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($set) => $set('grammage', null)),
                                Select::make('grammage')
                                    ->label('Weight (g)')
                                    ->options(function (callable $get) {
                                        $ptId = $get('product_type_id');
                                        if (! $ptId) {
                                            return [];
                                        }
                                        $pt = ProductType::find($ptId);
                                        if (! $pt) {
                                            return [];
                                        }

                                        return collect($pt->available_grammages)
                                            ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                            ->mapWithKeys(fn ($g) => [(string) $g => $g.'g'])
                                            ->toArray();
                                    })
                                    ->required()
                                    ->live(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->required(),
                            ])
                            ->addActionLabel('Add Item')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Request Notes'),
                    ])
                    ->action(function (array $data) {
                        $transfer = StockTransfer::create([
                            'from_warehouse_id' => $data['from_warehouse_id'],
                            'to_stockist_id' => $data['stockist_id'],
                            'requested_by' => auth()->id(),
                            'status' => 'requested',
                            'notes' => $data['notes'] ?? null,
                        ]);

                        foreach ($data['items'] as $item) {
                            $transfer->items()->create($item);
                        }

                        Notification::make()->title('Stock request created')->success()->send();
                    }),

                // === SUPERVISOR APPROVAL ===

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (StockTransfer $record) => $record->status === 'requested' && auth()->user()->role === 'supervisor')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Stock Request')
                    ->modalDescription('Confirm approval of this stock request. Once approved, it can be dispatched.')
                    ->action(function (StockTransfer $record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);
                        Notification::make()->title('Stock request approved')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (StockTransfer $record) => $record->status === 'requested' && auth()->user()->role === 'supervisor')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (StockTransfer $record, array $data) {
                        $record->update([
                            'status' => 'cancelled',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Stock request rejected')->danger()->send();
                    }),

                // === SALES APPROVAL (for their assigned warehouse) ===

                Action::make('salesApprove')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (StockTransfer $record) => $record->status === 'requested' && auth()->user()->role === 'sales')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Stock Request')
                    ->modalDescription('Confirm approval of this stock request. Available stock will be verified before approval.')
                    ->action(function (StockTransfer $record) {
                        $insufficientItems = [];

                        foreach ($record->items as $item) {
                            $inventory = Inventory::where([
                                'warehouse_id' => $record->from_warehouse_id,
                                'product_type_id' => $item->product_type_id,
                                'grammage' => $item->grammage,
                            ])->first();

                            $available = $inventory?->quantity ?? 0;

                            if ($available < $item->quantity) {
                                $productName = $item->productType?->name ?? 'Unknown';
                                $insufficientItems[] = "{$productName} {$item->grammage}g (requested: {$item->quantity}, available: {$available})";
                            }
                        }

                        if (! empty($insufficientItems)) {
                            Notification::make()
                                ->title('Insufficient stock in warehouse')
                                ->body('The following items have insufficient stock:'.PHP_EOL.implode(PHP_EOL, $insufficientItems))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'status' => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);

                        Notification::make()->title('Stock request approved')->success()->send();
                    }),

                Action::make('salesReject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (StockTransfer $record) => $record->status === 'requested' && auth()->user()->role === 'sales')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (StockTransfer $record, array $data) {
                        $record->update([
                            'status' => 'cancelled',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        Notification::make()->title('Stock request rejected')->danger()->send();
                    }),

                // === EXISTING ACTIONS (adjusted visibility) ===

                EditAction::make()
                    ->visible(fn (StockTransfer $record) => $record->status === 'draft'),

                Action::make('printDispatch')
                    ->label('Print Dispatch Note')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->visible(fn (StockTransfer $record) => in_array($record->status, ['approved', 'dispatched', 'received']))
                    ->action(function (StockTransfer $record) {
                        $pdf = Pdf::loadView('pdf.dispatch-note', ['transfer' => $record]);

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            "dispatch-note-{$record->id}.pdf"
                        );
                    }),

                Action::make('dispatch')
                    ->label('Dispatch')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->visible(fn (StockTransfer $record) => $record->status === 'approved')
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
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (StockTransfer $record) => $record->status === 'dispatched'
                        && $record->dispatched_by !== auth()->id()
                        && (
                            ($record->to_warehouse_id && in_array($record->to_warehouse_id, auth()->user()->managedWarehouses()->pluck('id')->toArray()))
                            || $record->to_stockist_id
                            || $record->requested_by === auth()->id()
                        ))
                    ->form(fn (StockTransfer $record) => [
                        Repeater::make('items')
                            ->label('Received Items')
                            ->schema([
                                TextInput::make('product_label')
                                    ->label('Product')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(fn ($state) => ''),
                                TextInput::make('accepted_quantity')
                                    ->label('Accepted Qty')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('rejected_quantity')
                                    ->label('Rejected Qty')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->live(),
                                TextInput::make('rejection_reason')
                                    ->label('Rejection Reason')
                                    ->visible(fn (callable $get) => ($get('rejected_quantity') ?? 0) > 0),
                            ])
                            ->defaultItems(0)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->default(
                                $record->items->map(fn ($item) => [
                                    'item_id' => $item->id,
                                    'product_label' => ($item->productType?->name ?? 'Unknown')." {$item->grammage}g",
                                    'accepted_quantity' => $item->quantity,
                                    'rejected_quantity' => 0,
                                    'rejection_reason' => null,
                                ])->toArray()
                            ),
                    ])
                    ->action(function (StockTransfer $record, array $data) {
                        foreach ($data['items'] as $itemData) {
                            $item = $record->items()->find($itemData['item_id']);
                            if (! $item) {
                                continue;
                            }

                            $accepted = (int) ($itemData['accepted_quantity'] ?? $item->quantity);
                            $rejected = (int) ($itemData['rejected_quantity'] ?? 0);

                            $item->update([
                                'rejected_quantity' => $rejected,
                                'rejection_reason' => $rejected > 0 ? ($itemData['rejection_reason'] ?? null) : null,
                            ]);

                            $accepted = min($accepted, $item->quantity);

                            if ($accepted > 0) {
                                if ($record->to_warehouse_id) {
                                    $inv = Inventory::firstOrCreate(
                                        [
                                            'warehouse_id' => $record->to_warehouse_id,
                                            'product_type_id' => $item->product_type_id,
                                            'grammage' => $item->grammage,
                                        ],
                                        ['quantity' => 0]
                                    );
                                    $inv->increment('quantity', $accepted);
                                }

                                if ($record->to_stockist_id) {
                                    $stock = StockistStock::firstOrCreate(
                                        [
                                            'stockist_id' => $record->to_stockist_id,
                                            'product_name' => $item->productType?->name ?? 'Unknown',
                                            'grammage' => $item->grammage,
                                        ],
                                        ['quantity' => 0]
                                    );
                                    $stock->increment('quantity', $accepted);
                                }

                                if ($record->requested_by) {
                                    $agentStock = AgentStock::firstOrCreate(
                                        [
                                            'user_id' => $record->requested_by,
                                            'product_type_id' => $item->product_type_id,
                                            'product_name' => $item->productType?->name ?? 'Unknown',
                                            'grammage' => $item->grammage,
                                        ],
                                        ['quantity' => 0]
                                    );
                                    $agentStock->increment('quantity', $accepted);
                                }
                            }
                        }

                        if ($record->from_warehouse_id) {
                            foreach ($record->items as $item) {
                                $inv = Inventory::where([
                                    'warehouse_id' => $record->from_warehouse_id,
                                    'product_type_id' => $item->product_type_id,
                                    'grammage' => $item->grammage,
                                ])->first();
                                if ($inv) {
                                    $inv->decrement('quantity', $item->quantity - $item->rejected_quantity);
                                }
                            }
                        }

                        if ($record->from_stockist_id) {
                            foreach ($record->items as $item) {
                                $stock = StockistStock::where([
                                    'stockist_id' => $record->from_stockist_id,
                                    'product_name' => $item->productType?->name ?? 'Unknown',
                                    'grammage' => $item->grammage,
                                ])->first();
                                if ($stock) {
                                    $stock->decrement('quantity', $item->quantity - $item->rejected_quantity);
                                }
                            }
                        }

                        $record->update([
                            'status' => 'received',
                            'received_by' => auth()->id(),
                            'received_at' => now(),
                            'stockist_accepted_at' => $record->to_stockist_id ? now() : $record->stockist_accepted_at,
                        ]);

                        $hasRejections = $record->items()->where('rejected_quantity', '>', 0)->exists();

                        Notification::make()
                            ->title($hasRejections
                                ? 'Stock received with some rejected items'
                                : 'Stock received successfully')
                            ->success()
                            ->send();
                    }),

                Action::make('resolveRejections')
                    ->label('Resolve Rejections')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->visible(fn (StockTransfer $record) => $record->status === 'received' && $record->unresolvedRejectedItems()->exists() && auth()->user()->role === 'admin')
                    ->form([
                        Textarea::make('resolution_notes')
                            ->label('Resolution Notes')
                            ->required(),
                    ])
                    ->action(function (StockTransfer $record, array $data) {
                        $record->unresolvedRejectedItems()->update([
                            'rejection_resolved_at' => now(),
                        ]);

                        $record->update([
                            'notes' => ($record->notes ? $record->notes."\n" : '').'Rejections resolved: '.$data['resolution_notes'],
                        ]);

                        Notification::make()
                            ->title('Rejections resolved')
                            ->success()
                            ->send();
                    }),

                Action::make('stockistAccept')
                    ->label('Stockist Accept')
                    ->icon('heroicon-o-hand-thumb-up')
                    ->color('success')
                    ->visible(fn (StockTransfer $record) => $record->status === 'received' && $record->to_stockist_id && ! $record->stockist_accepted_at)
                    ->requiresConfirmation()
                    ->modalHeading('Stockist Stock Acceptance')
                    ->modalDescription('Confirm that the stockist has received and accepted the stock. This will record the acceptance and update the stockist records.')
                    ->action(function (StockTransfer $record) {
                        $record->update([
                            'stockist_accepted_at' => now(),
                        ]);

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
                    ->visible(fn (StockTransfer $record) => in_array($record->status, ['draft', 'requested', 'approved', 'dispatched']))
                    ->requiresConfirmation()
                    ->form(fn (StockTransfer $record) => $record->status === 'requested'
                        ? [Textarea::make('rejection_reason')->label('Reason for Cancellation')->required()]
                        : [])
                    ->action(function (StockTransfer $record, array $data) {
                        $record->update([
                            'status' => 'cancelled',
                            'rejection_reason' => $data['rejection_reason'] ?? $record->rejection_reason,
                        ]);
                        Notification::make()->title('Stock transfer cancelled')->danger()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

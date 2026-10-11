<?php

namespace App\Filament\Widgets;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Enums\StockTransferStatus;
use App\Filament\Exports\OrderExporter;
use App\Models\Order;
use App\Models\ProductType;
use App\Models\User;
use App\Services\OrderAssignmentService;
use App\Support\WarehouseOptions;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class SalesPendingOrdersWidget extends TableWidget
{
    protected static ?int $sort = 1;

    protected static ?string $heading = 'Pending Orders';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn () => Order::where('status', OrderStatus::Pending)
                    ->where('assignment_status', AssignmentStatus::None)
                    ->whereHas('user', fn ($q) => $q->whereNotIn('role', ['open_market', 'retail_market']))
                    ->with(['customer', 'user', 'products', 'warehouse', 'fulfillmentTransfer'])
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Order #')
                    ->sortable(),

                TextColumn::make('customer.customer_name')
                    ->label('Customer')
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Initiated By')
                    ->placeholder('-'),

                TextColumn::make('products')
                    ->label('Products')
                    ->formatStateUsing(fn ($record): string => $record->products->map(
                        fn ($p) => "{$p->quantity}x {$p->product_name} (".ProductType::formatGrammage(ProductType::where('name', $p->product_name)->first(), (int) $p->grammage).')'
                    )->implode(', '))
                    ->limit(50),

                TextColumn::make('total_price')
                    ->label('Value')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('preferred_payment_option')
                    ->label('Payment')
                    ->formatStateUsing(fn ($state): string => $state ? ucfirst(str_replace('_', ' ', $state)) : '-'),

                TextColumn::make('payment_proof_status')
                    ->label('Payment Proof')
                    ->badge()
                    ->state(fn (Order $record): bool => $record->hasPaymentProof())
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Uploaded' : 'Missing')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),

                TextColumn::make('stock_source')
                    ->label('Stock Source')
                    ->badge()
                    ->state(fn (Order $record): ?string => $record->stock_source)
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'held' => 'At Hand',
                        'warehouse' => 'Warehouse',
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'held' => 'info',
                        'warehouse' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->placeholder('-'),

                TextColumn::make('dispatch_status')
                    ->label('Dispatch')
                    ->badge()
                    ->state(fn (Order $record): string => $record->fulfillmentTransfer?->status?->getLabel() ?? '—')
                    ->color(fn (Order $record): string => $record->fulfillmentTransfer?->status?->color() ?? 'gray'),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('uploadPaymentProof')
                    ->label('Upload Payment Proof')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('warning')
                    ->size('sm')
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending && ! $record->hasPaymentProof())
                    ->form([
                        FileUpload::make('payment_proof_path')
                            ->label('Payment Proof')
                            ->image()
                            ->maxSize(2048)
                            ->disk('s3')
                            ->directory('receipts/payment-proofs')
                            ->visibility('private')
                            ->imageEditor()
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        OrderAssignmentService::attachPaymentProof($record, $data['payment_proof_path'], auth()->id());

                        Notification::make()
                            ->title('Payment proof uploaded')
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    })
                    ->modalHeading('Upload Payment Proof')
                    ->modalDescription('Attach proof of payment before processing this order.'),

                Action::make('viewPaymentProof')
                    ->label('View Payment Proof')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->size('sm')
                    ->visible(fn (Order $record): bool => $record->hasPaymentProof())
                    ->modalContent(fn (Order $record) => view('filament.payment-proof', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalFooterActions(fn (Order $record): array => [
                        ...($this->canReplacePaymentProof($record) ? [$this->makeReplacePaymentProofAction('replaceProofFromPreview')->cancelParentActions()] : []),
                        Action::make('closePreview')
                            ->label('Close')
                            ->color('gray')
                            ->close(),
                    ]),

                $this->makeReplacePaymentProofAction(),

                Action::make('processOrder')
                    ->label('Process')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->size('sm')
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending && $record->hasPaymentProof())
                    ->requiresConfirmation()
                    ->modalHeading('Process Order')
                    ->modalDescription(fn (Order $record): string => $record->stock_source === 'warehouse'
                        ? 'Mark this order as delivered. Stock dispatched from the warehouse will be deducted from your stock at hand and the value attributed to the original initiator.'
                        : 'Mark this order as delivered. Stock will be deducted from your inventory and the value will be attributed to the original initiator.')
                    ->modalButton('Confirm Delivery')
                    ->action(function (Order $record) {
                        $user = auth()->user();

                        if (! OrderAssignmentService::hasSufficientStock($user, $record)) {
                            $dispatchStatus = $record->fulfillmentTransfer?->status;

                            Notification::make()
                                ->title('Insufficient stock')
                                ->body($dispatchStatus === StockTransferStatus::Requested || $dispatchStatus === StockTransferStatus::Approved
                                    ? "Dispatch #{$record->fulfillmentTransfer->id} ({$dispatchStatus->getLabel()}) is still with the warehouse manager. You can process this order once it is dispatched."
                                    : 'You do not have enough stock to process this order. Please fulfill it from a warehouse or request stock first.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'assigned_to' => $user->id,
                            'assigned_by' => $user->id,
                            'assigned_at' => now(),
                            'assignment_status' => AssignmentStatus::Accepted,
                        ]);

                        try {
                            OrderAssignmentService::markOrderDelivered($record->fresh(), null, $user->id);
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Processing failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Order processed')
                            ->body("Order #{$record->id} has been delivered. Stock deducted from your inventory.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('fulfillFromWarehouse')
                    ->label('Fulfill from Warehouse')
                    ->icon('heroicon-o-building-storefront')
                    ->color('warning')
                    ->size('sm')
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending && $record->fulfillmentTransfer === null)
                    ->form([
                        Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->options(fn () => WarehouseOptions::forSales(auth()->user()))
                            ->searchable()
                            ->required()
                            ->helperText(fn () => WarehouseOptions::hasAssignedWarehouses(auth()->user())
                                ? null
                                : 'No warehouse is assigned to you yet, showing all active warehouses.'),
                    ])
                    ->action(function (Order $record, array $data) {
                        try {
                            $transfer = OrderAssignmentService::routeOrderToWarehouse($record, (int) $data['warehouse_id'], auth()->id(), auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot fulfill from warehouse')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Sent to warehouse')
                            ->body("Order #{$record->id} routed to {$transfer->fromWarehouse?->name}. Dispatch #{$transfer->id} is awaiting the warehouse manager.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    })
                    ->modalHeading('Fulfill Order from Warehouse')
                    ->modalDescription('Choose the warehouse that should dispatch stock for this order. You will be able to process it once the warehouse manager dispatches.')
                    ->modalButton('Send to Warehouse'),

                Action::make('assignToCsr')
                    ->label('Assign to CSR')
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->size('sm')
                    ->form([
                        Select::make('csr_id')
                            ->label('Select CSR')
                            ->options(fn () => User::where('role', 'community_sales_representative')
                                ->where('is_active', true)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (Order $record, array $data) {
                        $csr = User::find($data['csr_id']);

                        if (! $csr) {
                            return;
                        }

                        OrderAssignmentService::assignToCsr($record, $csr, $data['notes'] ?? null);

                        Notification::make()
                            ->title('Order assigned')
                            ->body("Order #{$record->id} has been assigned to {$csr->name}.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(OrderExporter::class),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private function canReplacePaymentProof(Order $record): bool
    {
        return $record->status === OrderStatus::Pending && $record->hasPaymentProof();
    }

    private function makeReplacePaymentProofAction(string $name = 'replacePaymentProof'): Action
    {
        return Action::make($name)
            ->label('Replace Proof')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->size('sm')
            ->visible(fn (Order $record): bool => $this->canReplacePaymentProof($record))
            ->form([
                FileUpload::make('payment_proof_path')
                    ->label('Payment Proof')
                    ->image()
                    ->maxSize(2048)
                    ->disk('s3')
                    ->directory('receipts/payment-proofs')
                    ->visibility('private')
                    ->imageEditor()
                    ->required(),
            ])
            ->action(function (Order $record, array $data) {
                try {
                    OrderAssignmentService::replacePaymentProof($record, $data['payment_proof_path'], auth()->id());
                } catch (ValidationException $e) {
                    Notification::make()
                        ->danger()
                        ->title('Replacement failed')
                        ->body($e->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Payment proof replaced')
                    ->success()
                    ->send();

                $this->dispatch('refresh-dashboard');
            })
            ->modalHeading('Replace Payment Proof')
            ->modalDescription('Upload a corrected proof. The previous file will be removed.');
    }
}

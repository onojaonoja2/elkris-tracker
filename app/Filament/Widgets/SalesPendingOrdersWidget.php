<?php

namespace App\Filament\Widgets;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Filament\Exports\OrderExporter;
use App\Models\AgentStock;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderAssignmentService;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
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
                    ->with(['customer', 'user', 'products'])
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
                        fn ($p) => "{$p->quantity}x {$p->product_name} ({$p->grammage}g)"
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
                    ->modalCancelActionLabel('Close'),

                Action::make('processOrder')
                    ->label('Process')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->size('sm')
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending && $record->hasPaymentProof())
                    ->requiresConfirmation()
                    ->modalHeading('Process Order')
                    ->modalDescription('Mark this order as delivered. Stock will be deducted from your inventory and the value will be attributed to the original initiator.')
                    ->modalButton('Confirm Delivery')
                    ->action(function (Order $record) {
                        $user = auth()->user();

                        $hasStock = true;
                        foreach ($record->products as $product) {
                            $stock = AgentStock::where([
                                'user_id' => $user->id,
                                'product_name' => $product->product_name,
                                'grammage' => $product->grammage,
                            ])->first();

                            if (! $stock || $stock->quantity < $product->quantity) {
                                $hasStock = false;
                                break;
                            }
                        }

                        if (! $hasStock) {
                            Notification::make()
                                ->title('Insufficient stock')
                                ->body('You do not have enough stock to process this order. Please request stock from the warehouse first.')
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

                        OrderAssignmentService::confirmDeliveryBySales($record);

                        Notification::make()
                            ->title('Order processed')
                            ->body("Order #{$record->id} has been delivered. Stock deducted from your inventory.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),

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
}

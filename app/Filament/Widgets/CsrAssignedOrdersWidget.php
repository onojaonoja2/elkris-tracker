<?php

namespace App\Filament\Widgets;

use App\Enums\AssignmentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderAssignmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class CsrAssignedOrdersWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Orders Assigned to Me';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        $userId = auth()->id();

        return $table
            ->query(
                fn () => Order::where('assigned_to', $userId)
                    ->with(['customer', 'user', 'assignedBy', 'products'])
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

                TextColumn::make('payment_proof_status')
                    ->label('Payment Proof')
                    ->badge()
                    ->state(fn (Order $record): bool => $record->hasPaymentProof())
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Uploaded' : 'Missing')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),

                TextColumn::make('assignment_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => $state->color()),

                TextColumn::make('assigned_at')
                    ->label('Assigned At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('viewOrder')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->size('sm')
                    ->modalHeading(fn (Order $record): string => "Order #{$record->id} Details")
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist(function (Order $record): array {
                        return [
                            Section::make('Customer Information')
                                ->schema([
                                    TextEntry::make('customer.customer_name')
                                        ->label('Name'),
                                    TextEntry::make('customer.phone_number')
                                        ->label('Phone'),
                                    TextEntry::make('customer.address')
                                        ->label('Address'),
                                    TextEntry::make('customer.city')
                                        ->label('City'),
                                    TextEntry::make('customer.state')
                                        ->label('State'),
                                ])
                                ->columns(2),

                            Section::make('Items Ordered')
                                ->schema([
                                    RepeatableEntry::make('products')
                                        ->schema([
                                            TextEntry::make('product_name')
                                                ->label('Product'),
                                            TextEntry::make('grammage')
                                                ->label('Weight')
                                                ->formatStateUsing(fn ($state): string => $state.'g'),
                                            TextEntry::make('quantity')
                                                ->label('Qty'),
                                            TextEntry::make('price')
                                                ->label('Unit Price')
                                                ->money('NGN'),
                                            TextEntry::make('amount')
                                                ->label('Amount')
                                                ->state(fn (Product $item): float => $item->quantity * $item->price)
                                                ->money('NGN'),
                                        ])
                                        ->columns(5),
                                ])
                                ->columns(1),

                            Section::make('Order Summary')
                                ->schema([
                                    TextEntry::make('total_price')
                                        ->label('Total Value')
                                        ->money('NGN'),
                                    TextEntry::make('preferred_payment_option')
                                        ->label('Payment Option')
                                        ->placeholder('N/A'),
                                    TextEntry::make('expected_delivery_date')
                                        ->label('Expected Delivery')
                                        ->date('d/m/Y'),
                                    TextEntry::make('assignment_status')
                                        ->label('Status')
                                        ->state(fn (Order $item): string => $item->assignment_status->getLabel())
                                        ->badge()
                                        ->color(fn (Order $item): string => $item->assignment_status->color()),
                                ])
                                ->columns(2),
                        ];
                    }),

                Action::make('acceptAssignment')
                    ->label('Accept')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->size('sm')
                    ->visible(fn (Order $record): bool => $record->assignment_status === AssignmentStatus::Assigned)
                    ->requiresConfirmation()
                    ->modalHeading('Accept Assignment')
                    ->modalDescription('Accept this order assignment? You will then need to confirm delivery.')
                    ->action(function (Order $record) {
                        OrderAssignmentService::acceptAssignment($record);

                        Notification::make()
                            ->title('Assignment accepted')
                            ->body("You have accepted Order #{$record->id}.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('rejectAssignment')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->size('sm')
                    ->visible(fn (Order $record): bool => $record->assignment_status === AssignmentStatus::Assigned)
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        OrderAssignmentService::rejectAssignment($record, $data['reason']);

                        Notification::make()
                            ->title('Assignment rejected')
                            ->body("Order #{$record->id} has been returned to the pending pool.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('uploadPaymentProof')
                    ->label('Upload Payment Proof')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('warning')
                    ->size('sm')
                    ->visible(fn (Order $record): bool => $record->assignment_status === AssignmentStatus::Accepted && ! $record->hasPaymentProof())
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
                    ->modalDescription('Attach proof of payment before confirming delivery.'),

                Action::make('viewPaymentProof')
                    ->label('View Payment Proof')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->size('sm')
                    ->visible(fn (Order $record): bool => $record->hasPaymentProof())
                    ->modalContent(fn (Order $record) => view('filament.payment-proof', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('confirmDelivery')
                    ->label('Confirm Delivery')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->size('sm')
                    ->visible(fn (Order $record): bool => $record->assignment_status === AssignmentStatus::Accepted && $record->hasPaymentProof())
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Delivery')
                    ->modalDescription('Confirm that this order has been delivered. Stock will be deducted from your inventory and the value attributed to you.')
                    ->modalButton('Confirm Delivery')
                    ->action(function (Order $record) {
                        $user = auth()->user();

                        if (! OrderAssignmentService::hasSufficientStock($user, $record)) {
                            Notification::make()
                                ->title('Insufficient stock')
                                ->body('You do not have enough stock to deliver this order.')
                                ->danger()
                                ->send();

                            return;
                        }

                        OrderAssignmentService::confirmDeliveryByCsr($record);

                        Notification::make()
                            ->title('Delivery confirmed')
                            ->body("Order #{$record->id} has been marked as delivered. Stock deducted from your inventory.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

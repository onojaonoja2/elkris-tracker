<?php

namespace App\Filament\Widgets;

use App\Enums\AssignmentStatus;
use App\Models\AgentStock;
use App\Models\Order;
use App\Services\OrderAssignmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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

                Action::make('confirmDelivery')
                    ->label('Confirm Delivery')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->size('sm')
                    ->visible(fn (Order $record): bool => $record->assignment_status === AssignmentStatus::Accepted)
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Delivery')
                    ->modalDescription('Confirm that this order has been delivered. Stock will be deducted from your inventory and the value attributed to you.')
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

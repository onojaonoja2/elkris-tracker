<?php

namespace App\Filament\Widgets;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Models\AgentStock;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderAssignmentService;
use Filament\Actions\Action;
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

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('processOrder')
                    ->label('Process')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->size('sm')
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
            ->defaultSort('created_at', 'desc');
    }
}

<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderAssignmentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class LeadAssignedCsrOrdersWidget extends TableWidget
{
    protected static ?string $heading = 'Orders Assigned to CSR';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasRole('lead');
    }

    public function table(Table $table): Table
    {
        $leadId = auth()->id();
        $repIds = User::where('lead_id', $leadId)
            ->where('role', 'rep')
            ->pluck('id')
            ->toArray();
        $teamUserIds = array_merge([$leadId], $repIds);

        return $table
            ->query(
                fn () => Order::whereIn('user_id', $teamUserIds)
                    ->where('is_migrated_order', false)
                    ->whereNotNull('assigned_to')
                    ->where('status', '!=', OrderStatus::Delivered)
                    ->with(['customer', 'assignedTo', 'products'])
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Order #')
                    ->sortable(),

                TextColumn::make('customer.customer_name')
                    ->label('Customer')
                    ->searchable(),

                TextColumn::make('assignedTo.name')
                    ->label('CSR Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assignedTo.phone')
                    ->label('CSR Phone')
                    ->placeholder('-'),

                TextColumn::make('assignedTo.email')
                    ->label('CSR Email')
                    ->placeholder('-'),

                TextColumn::make('products')
                    ->label('Items')
                    ->formatStateUsing(fn ($record): string => $record->products->map(
                        fn ($p) => "{$p->quantity}x {$p->product_name} ({$p->grammage}g)"
                    )->implode(', '))
                    ->limit(40),

                TextColumn::make('total_price')
                    ->label('Value')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('unassign')
                    ->label('Unassign')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Unassign CSR')
                    ->modalDescription('This will return the order to the pending assignment queue.')
                    ->action(function (Order $record) {
                        OrderAssignmentService::rejectAssignment($record, 'Unassigned by lead');

                        Notification::make()
                            ->title('Order unassigned')
                            ->body("Order #{$record->id} is back in the assignment queue.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    })
                    ->visible(fn (Order $record): bool => $record->status !== OrderStatus::Delivered),
            ])
            ->paginated([10, 25, 50]);
    }
}

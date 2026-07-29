<?php

namespace App\Filament\Widgets;

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

class RepOrderAssignmentWidget extends TableWidget
{
    protected static ?string $heading = 'Assign Orders to CSR';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasRole('rep');
    }

    public function table(Table $table): Table
    {
        $repId = auth()->id();

        return $table
            ->query(
                fn () => Order::whereNull('assigned_to')
                    ->where('status', 'pending')
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
                    ->label('Items')
                    ->formatStateUsing(fn ($record): string => $record->products->map(
                        fn ($p) => "{$p->quantity}x {$p->product_name} ({$p->grammage}g)"
                    )->implode(', '))
                    ->limit(40),

                TextColumn::make('total_price')
                    ->label('Value')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('assignToCsr')
                    ->label('Assign to CSR')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->form([
                        Select::make('csr_id')
                            ->label('Select CSR')
                            ->options(
                                fn () => User::where('role', 'community_sales_representative')
                                    ->where('is_active', true)
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->required(),
                        Textarea::make('notes')
                            ->label('Delivery Notes')
                            ->rows(2),
                    ])
                    ->action(function (Order $record, array $data) {
                        $csr = User::find($data['csr_id']);
                        if (! $csr) {
                            Notification::make()
                                ->title('CSR not found')
                                ->danger()
                                ->send();

                            return;
                        }

                        OrderAssignmentService::assignToCsr(
                            $record,
                            $csr,
                            $data['notes'] ?? null
                        );

                        Notification::make()
                            ->title('Order assigned')
                            ->body("Order #{$record->id} assigned to {$csr->name}.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
            ])
            ->paginated([10, 25, 50]);
    }
}

<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Filament\Traits\HasBreakdownViewAction;
use App\Models\DamagedStockReturn;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class SupervisorDamagedReturnsWidget extends TableWidget
{
    use HasBreakdownViewAction;

    protected static ?string $heading = 'Damaged Stock Returns Awaiting Supervisor';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasRole('supervisor');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DamagedStockReturn::where('status', 'pending')
                ->whereNull('supervisor_approved_by')
                ->whereHas('user', fn ($query) => $query->where('role', UserRole::CommunitySalesRepresentative->value))
                ->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('user.name')->label('Returned By'),
                TextColumn::make('warehouse.name')->label('Warehouse'),
                TextColumn::make('productType.name')->label('Product'),
                TextColumn::make('grammage')->label('Weight')->formatStateUsing(fn ($state) => $state.'g'),
                TextColumn::make('quantity')->label('Qty'),
                TextColumn::make('reason')->label('Reason')->limit(40),
                TextColumn::make('created_at')->label('Date')->dateTime(),
            ])
            ->recordActions([
                $this->breakdownViewAction(),
                Action::make('supervisorApprove')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Damaged Stock Return')
                    ->modalDescription('This will forward the request to the accountant for final approval.')
                    ->action(function (DamagedStockReturn $record) {
                        $record->update([
                            'supervisor_approved_by' => auth()->id(),
                            'supervisor_approved_at' => now(),
                        ]);

                        Notification::make()->title('Damaged stock return approved — forwarded to accountant')->success()->send();
                    }),

                Action::make('supervisorReject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('rejection_reason')->label('Reason for Rejection')->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Reject Damaged Stock Return')
                    ->action(function (DamagedStockReturn $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'supervisor_approved_by' => auth()->id(),
                            'supervisor_approved_at' => now(),
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        Notification::make()->title('Damaged stock return rejected')->danger()->send();
                    }),
            ])
            ->paginated(10);
    }
}

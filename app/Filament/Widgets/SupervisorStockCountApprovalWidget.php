<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\StockCount;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\On;

class SupervisorStockCountApprovalWidget extends BaseWidget
{
    protected static ?string $heading = 'Pending Stock Count Approvals';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockCount::where('status', 'pending')
                    ->whereNull('supervisor_status')
                    ->whereHas('user', fn ($query) => $query->where('role', UserRole::CommunitySalesRepresentative->value))
                    ->with('user', 'items.productType')
            )
            ->columns([
                TextColumn::make('user.name')->label('Agent'),
                TextColumn::make('items_count')->label('Items')->counts('items'),
                TextColumn::make('created_at')->label('Submitted')->dateTime(),
                TextColumn::make('is_additional_count')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Additional' : 'Initial')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'info'),
            ])
            ->actions([
                Action::make('supervisorVerify')
                    ->label('Verify & Approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->action(function (StockCount $record) {
                        $record->update([
                            'supervisor_status' => 'verified',
                            'supervisor_verified_by' => auth()->id(),
                            'supervisor_verified_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Stock count verified')
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
                Action::make('supervisorReject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('rejection_reason')->required(),
                    ])
                    ->action(function (StockCount $record, array $data) {
                        $record->update([
                            'supervisor_status' => 'rejected',
                            'supervisor_verified_by' => auth()->id(),
                            'supervisor_verified_at' => now(),
                            'rejection_reason' => $data['rejection_reason'],
                            'status' => 'rejected',
                        ]);

                        Notification::make()
                            ->title('Stock count rejected')
                            ->danger()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
            ]);
    }
}

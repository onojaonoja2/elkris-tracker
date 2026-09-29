<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Filament\Traits\HasBreakdownViewAction;
use App\Models\StockCount;
use App\Services\StockCountService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class AccountantStockCountApprovalWidget extends BaseWidget
{
    use HasBreakdownViewAction;

    protected static ?string $heading = 'Stock Count Final Approvals';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockCount::where('status', 'pending')
                    ->where(function ($query) {
                        $query->where('supervisor_status', 'verified')
                            ->orWhereNotNull('warehouse_id')
                            ->orWhereHas('user', fn ($query) => $query->where('role', '!=', UserRole::CommunitySalesRepresentative->value));
                    })
                    ->with('user', 'items.productType')
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('user.name')->label('Agent'),
                TextColumn::make('items_count')->label('Items')->counts('items'),
                TextColumn::make('supervisor_verified_at')->label('Supervisor Verified')->dateTime(),
                TextColumn::make('is_additional_count')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Additional' : 'Initial')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'info'),
            ])
            ->actions([
                $this->breakdownViewAction(),
                Action::make('accountantApprove')
                    ->label('Final Approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->action(function (StockCount $record) {
                        try {
                            StockCountService::finalApprove($record, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Approval failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Stock count approved')
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
                Action::make('accountantReject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('rejection_reason')->required(),
                    ])
                    ->action(function (StockCount $record, array $data) {
                        StockCountService::reject($record, $data['rejection_reason']);

                        Notification::make()
                            ->title('Stock count rejected')
                            ->danger()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
            ]);
    }
}

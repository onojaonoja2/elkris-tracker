<?php

namespace App\Filament\Widgets;

use App\Enums\StockTransferStatus;
use App\Enums\UserRole;
use App\Filament\Traits\HasBreakdownViewAction;
use App\Models\StockTransfer;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\On;

class SupervisorStockTransferApprovalWidget extends BaseWidget
{
    use HasBreakdownViewAction;

    protected static ?string $heading = 'Pending Stock Transfer Approvals';

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
            ->query(fn () => StockTransfer::where('status', StockTransferStatus::Requested)
                ->whereNull('supervisor_approved_by')
                ->where('requires_approval', true)
                ->whereHas('requester', fn ($query) => $query->where('role', UserRole::CommunitySalesRepresentative->value))
                ->with(['requester', 'fromWarehouse', 'toAgent', 'items.productType'])
                ->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('requester.name')
                    ->label('Requested By')
                    ->searchable(),
                TextColumn::make('fromWarehouse.name')
                    ->label('From Warehouse')
                    ->placeholder('N/A'),
                TextColumn::make('toAgent.name')
                    ->label('To Agent')
                    ->placeholder('N/A'),
                TextColumn::make('items')
                    ->label('Items')
                    ->getStateUsing(fn (StockTransfer $record): string => $record->items
                        ->map(fn ($item) => "{$item->quantity}x {$item->productType?->name} ({$item->grammage}g)")
                        ->implode(', '))
                    ->limit(50),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime(),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->recordActions([
                $this->breakdownViewAction(),
                Action::make('supervisorApprove')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Stock Request')
                    ->modalDescription('Approve this stock request so the warehouse can dispatch the items.')
                    ->action(function (StockTransfer $record) {
                        $record->update([
                            'supervisor_approved_by' => auth()->id(),
                            'supervisor_approved_at' => now(),
                            'status' => StockTransferStatus::Approved,
                        ]);

                        Notification::make()
                            ->title('Stock request approved')
                            ->body('The warehouse can now dispatch the items.')
                            ->success()
                            ->send();
                    }),
                Action::make('supervisorReject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Reject Stock Request')
                    ->action(function (StockTransfer $record, array $data) {
                        $record->update([
                            'status' => StockTransferStatus::Cancelled,
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        Notification::make()
                            ->title('Stock request rejected')
                            ->danger()
                            ->send();
                    }),
            ])
            ->paginated([10, 25, 50]);
    }
}

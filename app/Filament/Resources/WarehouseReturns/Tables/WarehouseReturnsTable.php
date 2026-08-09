<?php

namespace App\Filament\Resources\WarehouseReturns\Tables;

use App\Models\WarehouseReturn;
use App\Services\WarehouseReturnService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class WarehouseReturnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('user.name')->label('Returned By')->searchable()->sortable(),
                TextColumn::make('warehouse.name')->label('Warehouse')->searchable()->sortable(),
                TextColumn::make('productType.name')->label('Product')->searchable()->sortable(),
                TextColumn::make('grammage')->label('Weight')->formatStateUsing(fn ($state) => $state.'g'),
                TextColumn::make('quantity')->label('Qty')->sortable(),
                TextColumn::make('reason')->label('Reason')->limit(40)->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('approver.name')->label('Processed By')->placeholder('-'),
                TextColumn::make('approved_at')->label('Processed At')->dateTime()->placeholder('-'),
                TextColumn::make('created_at')->label('Requested At')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (WarehouseReturn $record): bool => $record->isPending() && auth()->user()->hasAnyRole(['admin', 'manager', 'warehouse_manager']))
                    ->requiresConfirmation()
                    ->modalHeading('Approve Warehouse Return')
                    ->modalDescription('Stock will be deducted from the agent and added to the warehouse inventory.')
                    ->action(function (WarehouseReturn $record) {
                        try {
                            WarehouseReturnService::approve($record, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Approval failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()->title('Warehouse return approved')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (WarehouseReturn $record): bool => $record->isPending() && auth()->user()->hasAnyRole(['admin', 'manager', 'warehouse_manager']))
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (WarehouseReturn $record, array $data) {
                        try {
                            WarehouseReturnService::reject($record, $data['rejection_reason'], auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Rejection failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()->title('Warehouse return rejected')->danger()->send();
                    })
                    ->modalHeading('Reject Warehouse Return')
                    ->modalDescription('Provide a reason for rejecting this return request.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn (): bool => auth()->user()->hasRole('admin')),
                ]),
            ]);
    }
}

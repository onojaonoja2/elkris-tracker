<?php

namespace App\Filament\Widgets;

use App\Models\Warehouse;
use App\Models\WarehouseReturn;
use App\Services\WarehouseReturnService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseReturnApprovalsWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Warehouse Returns';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'warehouse_manager']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $user = auth()->user();
                $warehouseIds = $user->hasRole('warehouse_manager')
                    ? Warehouse::where('manager_id', $user->id)->pluck('id')
                    : Warehouse::pluck('id');

                return WarehouseReturn::whereIn('warehouse_id', $warehouseIds)
                    ->orderBy('created_at', 'desc');
            })
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('user.name')->label('Returned By')->searchable(),
                TextColumn::make('warehouse.name')->label('Warehouse')->searchable(),
                TextColumn::make('productType.name')->label('Product')->searchable(),
                TextColumn::make('grammage')->label('Weight')->formatStateUsing(fn ($state) => $state.'g'),
                TextColumn::make('quantity')->label('Qty'),
                TextColumn::make('reason')->label('Reason')->limit(40)->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Requested At')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (WarehouseReturn $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Approve Warehouse Return')
                    ->modalDescription('Stock will be deducted from the agent and added to warehouse inventory.')
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
                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (WarehouseReturn $record): bool => $record->isPending())
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
                        $this->dispatch('refresh-dashboard');
                    })
                    ->modalHeading('Reject Warehouse Return')
                    ->modalDescription('Provide a reason for rejecting this return request.'),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export Returns')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $user = auth()->user();
                        $warehouseIds = $user->hasRole('warehouse_manager')
                            ? Warehouse::where('manager_id', $user->id)->pluck('id')
                            : Warehouse::pluck('id');

                        return new StreamedResponse(function () use ($warehouseIds) {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['ID', 'Returned By', 'Warehouse', 'Product', 'Weight', 'Qty', 'Reason', 'Status', 'Requested At']);

                            WarehouseReturn::whereIn('warehouse_id', $warehouseIds)
                                ->with(['user', 'warehouse', 'productType'])
                                ->orderBy('created_at', 'desc')
                                ->chunk(100, function ($returns) use ($file) {
                                    foreach ($returns as $return) {
                                        fputcsv($file, [
                                            $return->id,
                                            $return->user?->name ?? 'N/A',
                                            $return->warehouse?->name ?? 'N/A',
                                            $return->productType?->name ?? 'N/A',
                                            $return->grammage.'g',
                                            $return->quantity,
                                            $return->reason ?? '-',
                                            $return->status,
                                            $return->created_at->format('d/m/Y H:i'),
                                        ]);
                                    }
                                });

                            fclose($file);
                        }, 200, [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment; filename="warehouse_returns_'.now()->format('Y_m_d_H_i_s').'.csv"',
                        ]);
                    }),
            ])
            ->paginated(10);
    }
}

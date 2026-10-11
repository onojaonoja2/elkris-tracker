<?php

namespace App\Filament\Widgets;

use App\Models\RawMaterial;
use App\Models\RawMaterialReceipt;
use App\Models\RawMaterialTransfer;
use App\Models\Warehouse;
use App\Services\RawMaterialReceiptService;
use App\Support\DashboardDateScope;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class RawMaterialReceiptApprovalsWidget extends BaseWidget
{
    protected static ?string $heading = 'Raw Material Receipts & Movements';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['accountant', 'general_accountant', 'manager', 'admin', 'general_manager']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => RawMaterialReceipt::query()
                ->with(['warehouse', 'requester', 'approver'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')
                    ->label('Receipt #')
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse'),
                TextColumn::make('source_type')
                    ->label('Source Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'other' ? 'Other' : 'Supplier')
                    ->color(fn (string $state): string => $state === 'other' ? 'info' : 'warning'),
                TextColumn::make('supplier_name')
                    ->label('Source')
                    ->searchable(),
                TextColumn::make('items')
                    ->label('Items')
                    ->formatStateUsing(fn ($record) => collect($record->lines())
                        ->map(function (array $line): string {
                            $material = RawMaterial::find($line['raw_material_id']);

                            return "{$line['quantity']} ".($material?->unit_of_measure ?? '').' of '.($material?->name ?? 'Unknown');
                        })
                        ->implode(', '))
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('requester.name')
                    ->label('Submitted By')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime(),
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
                    ->options(fn () => Warehouse::orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([
                Action::make('viewDispatchPapers')
                    ->label('View Papers')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn (RawMaterialReceipt $record) => $record->dispatch_papers_path)
                    ->action(function (RawMaterialReceipt $record) {
                        $html = '<div class="space-y-4">';
                        foreach ($record->dispatchPapers() as $path) {
                            $url = asset('storage/'.$path);
                            if (str_ends_with(strtolower($path), '.pdf')) {
                                $html .= '<iframe src="'.$url.'" class="w-full h-96 rounded-lg border"></iframe>';
                            } else {
                                $html .= '<img src="'.$url.'" class="max-w-full rounded-lg border" />';
                            }
                        }
                        $html .= '</div>';

                        return $html;
                    })
                    ->modalHeading('Dispatch Papers')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('approveReceipt')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (RawMaterialReceipt $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Approve Raw Material Receipt')
                    ->modalDescription('This will add the received stock to the warehouse inventory.')
                    ->action(function (RawMaterialReceipt $record) {
                        try {
                            RawMaterialReceiptService::approve($record, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Approval failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()->title('Receipt approved and inventory updated')->success()->send();

                        $this->dispatch('refresh-dashboard');
                    }),
                Action::make('rejectReceipt')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (RawMaterialReceipt $record): bool => $record->isPending())
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (RawMaterialReceipt $record, array $data) {
                        try {
                            RawMaterialReceiptService::reject($record, $data['rejection_reason'], auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Rejection failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()->title('Receipt rejected')->danger()->send();

                        $this->dispatch('refresh-dashboard');
                    })
                    ->requiresConfirmation(),
            ])
            ->headerActions([
                Action::make('exportMovements')
                    ->label('Export Movement Report')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->form([
                        DatePicker::make('from')
                            ->label('From Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('until')
                            ->label('Until Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        Select::make('warehouse_id')
                            ->label('Warehouse (optional)')
                            ->options(fn () => Warehouse::orderBy('name')->pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->action(function (array $data) {
                        [$defaultFrom, $defaultTo] = DashboardDateScope::fromSession();
                        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : $defaultFrom;
                        $to = isset($data['until']) ? Carbon::parse($data['until'])->endOfDay() : $defaultTo;
                        $warehouseId = $data['warehouse_id'] ?? null;

                        $filename = 'raw_material_movements_'.$from->format('Y_m_d').'_'.$to->format('Y_m_d').'.csv';

                        return response()->streamDownload(function () use ($from, $to, $warehouseId) {
                            $handle = fopen('php://output', 'w');
                            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

                            fputcsv($handle, ['Date', 'Movement', 'Reference', 'Warehouse', 'Counterparty', 'Material', 'Quantity', 'Unit', 'Status', 'Handled By']);

                            $receipts = RawMaterialReceipt::query()
                                ->whereBetween('created_at', [$from, $to])
                                ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
                                ->with(['warehouse', 'requester', 'approver'])
                                ->orderBy('created_at')
                                ->get();

                            foreach ($receipts as $receipt) {
                                $materialNames = collect($receipt->lines())
                                    ->map(function (array $line): string {
                                        $material = RawMaterial::find($line['raw_material_id']);

                                        return $material?->name ?? 'Unknown';
                                    })
                                    ->implode(', ');
                                $totalQty = collect($receipt->lines())->sum('quantity');

                                fputcsv($handle, [
                                    $receipt->created_at->format('d/m/Y H:i'),
                                    $receipt->isSupplierSource() ? 'Procurement Receipt' : 'Raw Material Receipt (Other)',
                                    'Receipt #'.$receipt->id,
                                    $receipt->warehouse?->name ?? '-',
                                    $receipt->supplier_name,
                                    $materialNames,
                                    $totalQty,
                                    '',
                                    ucfirst($receipt->status),
                                    $receipt->status === 'pending'
                                        ? ($receipt->requester?->name ?? '-')
                                        : ($receipt->approver?->name ?? '-'),
                                ]);
                            }

                            $transfers = RawMaterialTransfer::query()
                                ->whereBetween('created_at', [$from, $to])
                                ->when($warehouseId, fn ($query) => $query->where(
                                    fn ($q) => $q->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId)
                                ))
                                ->with(['fromWarehouse', 'toWarehouse', 'rawMaterial', 'requester', 'dispatcher', 'receiver'])
                                ->orderBy('created_at')
                                ->get();

                            foreach ($transfers as $transfer) {
                                $handler = match ($transfer->status) {
                                    'requested' => $transfer->requester?->name ?? '-',
                                    'dispatched' => $transfer->dispatcher?->name ?? '-',
                                    'received' => $transfer->receiver?->name ?? '-',
                                    default => '-',
                                };

                                fputcsv($handle, [
                                    $transfer->created_at->format('d/m/Y H:i'),
                                    'Warehouse Transfer',
                                    'Transfer #'.$transfer->id,
                                    $transfer->toWarehouse?->name ?? '-',
                                    $transfer->fromWarehouse?->name ?? '-',
                                    $transfer->rawMaterial?->name ?? '-',
                                    (float) $transfer->quantity,
                                    $transfer->rawMaterial?->unit_of_measure ?? '',
                                    ucfirst($transfer->status),
                                    $handler,
                                ]);
                            }

                            fclose($handle);
                        }, $filename, ['Content-Type' => 'text/csv']);
                    }),
            ])
            ->paginated([10, 25]);
    }
}

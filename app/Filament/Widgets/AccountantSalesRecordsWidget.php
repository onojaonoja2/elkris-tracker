<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Filament\Exports\SalesRecordExporter;
use App\Filament\Traits\HasBreakdownViewAction;
use App\Models\SalesRecord;
use App\Services\SalesRecordService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class AccountantSalesRecordsWidget extends TableWidget
{
    use HasBreakdownViewAction;

    protected static ?string $heading = 'Pending Sales Record Verifications';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['accountant', 'general_accountant']);
    }

    protected function getFilteredQuery()
    {
        $query = SalesRecord::where(function ($query) {
            $query->where(function ($query) {
                $query->whereHas('agent', fn ($query) => $query->where('role', UserRole::CommunitySalesRepresentative->value))
                    ->whereNotNull('supervisor_verified_at');
            })
                ->orWhereDoesntHave('agent', fn ($query) => $query->where('role', UserRole::CommunitySalesRepresentative->value));
        })
            ->whereIn('status', ['pending', 'receipt_uploaded'])
            ->orderBy('created_at', 'desc');

        $filters = $this->tableFilters['date_range'] ?? [];

        if ($filters['from'] ?? null) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['until'] ?? null) {
            $query->whereDate('created_at', '<=', $filters['until']);
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getFilteredQuery())
            ->columns([
                TextColumn::make('agent.name')
                    ->label('Agent'),
                TextColumn::make('agent_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open_market' => 'Open Market',
                        'retail_market' => 'Retail Market',
                        default => $state,
                    }),
                TextColumn::make('stock_source')
                    ->label('Stock Source')
                    ->badge()
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                        'held' => 'At Hand',
                        'warehouse' => 'Warehouse',
                        default => null,
                    })
                    ->color(fn (?string $state): string => $state === 'held' ? 'info' : 'warning'),
                TextColumn::make('total_value')
                    ->label('Total (₦)')
                    ->money('NGN'),
                TextColumn::make('vendor_name')
                    ->label('Vendor')
                    ->placeholder('-'),
                TextColumn::make('business_name')
                    ->label('Business')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime(),
            ])
            ->filters([
                Filter::make('date_range')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from')
                            ->label('From Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('until')
                            ->label('Until Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'From '.Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until '.Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                $this->breakdownViewAction(),
                Action::make('approveByAccountant')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form(function () {
                        return [
                            Textarea::make('accountant_notes')
                                ->label('Approval Notes'),
                        ];
                    })
                    ->action(function (SalesRecord $record, array $data) {
                        try {
                            SalesRecordService::approve($record, $data, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Approval failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()->title('Sales record approved')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Sales Record')
                    ->modalDescription(fn (SalesRecord $record) => $record->requiresWarehouseAllocation()
                        ? 'Confirm approval. Stock will be allocated from the warehouse on approval.'
                        : 'Confirm approval. Stock was already deducted when the sale was submitted.'),

                Action::make('rejectByAccountant')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (SalesRecord $record, array $data) {
                        try {
                            SalesRecordService::reject($record, $data['rejection_reason'], auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Rejection failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()->title('Sales record rejected and stock restored')->danger()->send();
                    })
                    ->requiresConfirmation(),

                Action::make('viewReceipt')
                    ->label('View Receipt')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->visible(fn (SalesRecord $record) => $record->receipt_path)
                    ->modalContent(fn (SalesRecord $record) => view('filament.sales-record-receipt', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(SalesRecordExporter::class),
            ])
            ->paginated(20);
    }
}

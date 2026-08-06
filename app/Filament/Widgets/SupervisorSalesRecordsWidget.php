<?php

namespace App\Filament\Widgets;

use App\Filament\Exports\SalesRecordExporter;
use App\Models\SalesRecord;
use App\Models\User;
use App\Services\SalesRecordService;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class SupervisorSalesRecordsWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Recent Sales Records';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        $from = Session::get('supervisor_date_from', now()->startOfDay()->toDateTimeString());
        $to = Session::get('supervisor_date_to', now()->endOfDay()->toDateTimeString());

        $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');

        return $table
            ->query(
                fn () => SalesRecord::whereIn('agent_id', $csrIds)
                    ->whereBetween('created_at', [$from, $to])
                    ->with('agent')
            )
            ->columns([
                TextColumn::make('agent.name')
                    ->label('Agent')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('products')
                    ->label('Products')
                    ->formatStateUsing(fn ($products) => collect($products)->map(fn ($p) => "{$p['quantity']}x {$p['product_name']}")->implode(', '))
                    ->limit(40),

                TextColumn::make('total_value')
                    ->label('Value')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('agent_id')
                    ->label('CSR')
                    ->options(fn () => User::where('role', 'community_sales_representative')
                        ->active()
                        ->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $agentId) => $query->where('agent_id', $agentId),
                    )),
            ])
            ->recordActions([
                Action::make('supervisorApprove')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Textarea::make('supervisor_notes')
                            ->label('Approval Notes'),
                    ])
                    ->action(function (SalesRecord $record, array $data) {
                        try {
                            SalesRecordService::supervisorApprove($record, $data['supervisor_notes'] ?? null, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Approval failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()->title('Sales record approved — forwarded to accountant')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Sales Record')
                    ->modalDescription('Confirm supervisor approval. The record will be forwarded to the accountant for final approval.')
                    ->visible(fn (SalesRecord $record): bool => in_array($record->status, ['pending', 'receipt_uploaded'], true)
                        && $record->supervisor_verified_at === null),

                Action::make('supervisorReject')
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
                            SalesRecordService::supervisorReject($record, $data['rejection_reason'], auth()->id());
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
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(SalesRecordExporter::class),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}

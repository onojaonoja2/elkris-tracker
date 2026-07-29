<?php

namespace App\Filament\Widgets;

use App\Filament\Exports\SalesRecordExporter;
use App\Models\SalesRecord;
use App\Models\User;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;
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
            ->headerActions([
                ExportAction::make()
                    ->exporter(SalesRecordExporter::class),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}

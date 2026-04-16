<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingFollowUps extends TableWidget
{
    public static function canView(): bool
    {
        return auth()->user()->role !== 'field_agent';
    }
    protected static ?string $heading = 'Upcoming Follow Ups 7 Days';

    protected int | string | array $columnSpan = 'full'; // Make it wide

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn(): Builder => Customer::query()
                    ->whereBetween('follow_up_date', [
                        now()->startOfDay(),
                        now()->addDays(7)->endOfDay()
                    ])
                    // Logic: If NOT Admin/Lead, filter by current User ID
                    ->when(
                        !in_array(auth()->user()->role, ['admin', 'lead']),
                        fn($query) => $query->where('rep_id', auth()->id())
                    )
                    ->orderBy('follow_up_date', 'asc')
                    ->limit(20)
            )
            ->columns([
                TextColumn::make('customer_name')->searchable()->toggleable(),
                TextColumn::make('call_date')->date()->sortable()->toggleable(),
                TextColumn::make('follow_up_date')->date()->sortable()->toggleable(),
                TextColumn::make('phone_number')->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // The "Link to another table"
                Action::make('view_all')
                    ->label('View All Follow-ups')
                    ->url(fn(): string => route('filament.admin.resources.customers.index', [
                        'tableFilters[call_date][from]' => now()->format('Y-m-d'),
                    ]))
                    ->button(),
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}

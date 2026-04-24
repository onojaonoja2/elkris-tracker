<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UpcomingFollowUps extends TableWidget
{
    public static function canView(): bool
    {
        $role = auth()->user()->role;

        return $role !== 'field_agent' && $role !== 'supervisor';
    }

    protected static ?string $heading = 'Upcoming Follow Ups 7 Days';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $today = now()->startOfDay();
        $nextWeek = now()->addDays(7)->endOfDay();

        return $table
            ->query(
                fn (): Builder => Customer::query()
                    ->where('rep_acceptance_status', 'accepted')
                    ->where(function ($q) use ($today, $nextWeek) {
                        $q->whereBetween('follow_up_date', [$today, $nextWeek])
                            ->orWhereExists(function ($sub) use ($today, $nextWeek) {
                                $sub->select(DB::raw(1))
                                    ->from('customer_rep')
                                    ->whereColumn('customer_rep.customer_id', 'customers.id')
                                    ->whereRaw('DATE_ADD(customer_rep.created_at, INTERVAL 3 DAY) BETWEEN ? AND ?', [$today, $nextWeek]);
                            })
                            ->orWhereExists(function ($sub) use ($today, $nextWeek) {
                                $sub->select(DB::raw(1))
                                    ->from('customer_rep')
                                    ->whereColumn('customer_rep.customer_id', 'customers.id')
                                    ->whereRaw('DATE_ADD(customer_rep.created_at, INTERVAL 7 DAY) BETWEEN ? AND ?', [$today, $nextWeek]);
                            });
                    })
                    ->when(
                        ! in_array($user->role, ['admin', 'lead']),
                        fn ($query) => $query->where('rep_id', $user->id)
                    )
                    ->orderBy('follow_up_date', 'asc')
                    ->limit(20)
            )
            ->columns([
                TextColumn::make('customer_name')->searchable()->toggleable(),
                TextColumn::make('call_date')->date()->sortable()->toggleable(),
                TextColumn::make('follow_up_date')
                    ->date()
                    ->label('Manual Follow-up')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('phone_number')->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('view_all')
                    ->label('View All Follow-ups')
                    ->url(fn (): string => route('filament.admin.resources.customers.index'))
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

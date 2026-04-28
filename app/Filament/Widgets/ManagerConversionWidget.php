<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ManagerConversionWidget extends TableWidget
{
    protected static ?string $heading = 'Conversion Rates';

    protected int|string|array $columnSpan = 'full';

    protected function getTimeRange(): array
    {
        $now = Carbon::now('Africa/Lagos');
        $workStart = $now->copy()->setHour(8)->setMinute(0)->setSecond(0);
        $workEnd = $now->copy()->setHour(17)->setMinute(0)->setSecond(0);

        if ($now->lt($workStart)) {
            return [
                'from' => $now->copy()->startOfDay(),
                'to' => $now->copy()->startOfDay()->addDay(),
            ];
        }

        if ($now->gte($workEnd)) {
            return [
                'from' => $workStart,
                'to' => $workEnd,
            ];
        }

        return [
            'from' => $workStart,
            'to' => $now,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query()->where('role', 'lead'))
            ->columns([
                TextColumn::make('name')
                    ->label('Team Lead')
                    ->searchable(),
                TextColumn::make('total_customers')
                    ->label('Total Customers')
                    ->getStateUsing(function (User $record): int {
                        return Customer::whereIn('rep_id', $record->reps()->pluck('id'))->count();
                    })
                    ->numeric(),
                TextColumn::make('converted')
                    ->label('Converted')
                    ->getStateUsing(function (User $record): int {
                        return Customer::whereIn('rep_id', $record->reps()->pluck('id'))->whereHas('orders')->count();
                    })
                    ->numeric(),
                BadgeColumn::make('conversion_rate')
                    ->label('Conversion Rate')
                    ->getStateUsing(function (User $record): string {
                        $total = Customer::whereIn('rep_id', $record->reps()->pluck('id'))->count();
                        $converted = Customer::whereIn('rep_id', $record->reps()->pluck('id'))->whereHas('orders')->count();

                        return $total > 0 ? round(($converted / $total) * 100, 1).'%' : '0%';
                    })
                    ->colors([
                        'success' => fn ($state) => floatval($state) >= 50,
                        'warning' => fn ($state) => floatval($state) >= 30 && floatval($state) < 50,
                        'danger' => fn ($state) => floatval($state) < 30,
                    ]),
            ])
            ->paginated(false);
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'manager' || auth()->user()->role === 'admin';
    }
}

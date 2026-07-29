<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ManagerConversionWidget extends TableWidget
{
    protected static ?string $heading = 'Conversion Rates';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

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
                TextColumn::make('conversion_rate')
                    ->label('Conversion Rate')
                    ->badge()
                    ->getStateUsing(function (User $record): string {
                        $total = Customer::whereIn('rep_id', $record->reps()->pluck('id'))->count();
                        $converted = Customer::whereIn('rep_id', $record->reps()->pluck('id'))->whereHas('orders')->count();

                        return $total > 0 ? round(($converted / $total) * 100, 1).'%' : '0%';
                    })
                    ->color(fn (string $state): string => match (true) {
                        (float) $state >= 50 => 'success',
                        (float) $state >= 30 => 'warning',
                        default => 'danger',
                    }),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $records = $this->getFilteredQuery()->get();

                        return response()->streamDownload(function () use ($records) {
                            $file = fopen('php://output', 'w');
                            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                            fputcsv($file, ['Team Lead', 'Total Customers', 'Converted', 'Conversion Rate']);

                            foreach ($records as $record) {
                                $total = Customer::whereIn('rep_id', $record->reps()->pluck('id'))->count();
                                $converted = Customer::whereIn('rep_id', $record->reps()->pluck('id'))->whereHas('orders')->count();
                                $rate = $total > 0 ? round(($converted / $total) * 100, 1).'%' : '0%';

                                fputcsv($file, [
                                    $record->name,
                                    $total,
                                    $converted,
                                    $rate,
                                ]);
                            }

                            fclose($file);
                        }, 'conversion_rates_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->paginated(false);
    }

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }
}

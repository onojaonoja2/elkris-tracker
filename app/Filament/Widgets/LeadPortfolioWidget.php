<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LeadPortfolioWidget extends TableWidget
{
    protected static ?string $heading = 'Team Portfolio';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->role === 'lead';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $leadId = auth()->id();

                return Customer::query()
                    ->whereHas('leads', fn ($q) => $q->where('users.id', $leadId));
            })
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Customer Name')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable(),
                TextColumn::make('rep.name')
                    ->label('Assigned Rep')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Address')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('created_at')
                    ->label('Date Added')
                    ->date('d/m/Y'),
                BadgeColumn::make('conversion_status')
                    ->label('Conversion')
                    ->getStateUsing(fn (Customer $record): string => $record->orders()->exists() ? 'Converted' : 'Pending')
                    ->colors([
                        'success' => 'Converted',
                        'warning' => 'Pending',
                    ]),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From Date')
                            ->closeOnDateSelection(),
                        DatePicker::make('created_until')
                            ->label('To Date')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
                Filter::make('rep_filter')
                    ->label('Filter by Rep')
                    ->form([
                        Select::make('rep_id')
                            ->label('Select Rep')
                            ->options(fn () => User::where('lead_id', auth()->id())->where('role', 'rep')->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('All Reps'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['rep_id'], fn ($q) => $q->where('rep_id', $data['rep_id']));
                    }),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export Portfolio')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $leadId = auth()->id();
                        $customers = Customer::query()
                            ->whereHas('leads', fn ($q) => $q->where('users.id', $leadId))
                            ->with('rep')
                            ->get();

                        $data = [];
                        foreach ($customers as $customer) {
                            $data[] = [
                                $customer->customer_name,
                                $customer->phone_number,
                                $customer->rep?->name ?? 'Unassigned',
                                $customer->address,
                                $customer->created_at->format('d/m/Y'),
                                $customer->orders()->exists() ? 'Yes' : 'No',
                            ];
                        }

                        return response()->streamDownload(function () use ($data) {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['Customer Name', 'Phone', 'Assigned Rep', 'Address', 'Date Added', 'Converted']);
                            foreach ($data as $row) {
                                fputcsv($file, $row);
                            }
                            fclose($file);
                        }, 'portfolio_export_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment',
                        ]);
                    }),
            ])
            ->paginated(false);
    }
}

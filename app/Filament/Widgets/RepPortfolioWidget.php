<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RepPortfolioWidget extends TableWidget
{
    protected static ?string $heading = 'My Portfolio';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->role === 'rep';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Customer::query()
                ->where('rep_id', auth()->id())
                ->where('rep_acceptance_status', 'accepted'))
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Customer Name')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Address')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('city')
                    ->label('City')
                    ->searchable(),
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
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('created_until')
                            ->label('To Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export Portfolio')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $repId = auth()->id();
                        $customers = Customer::query()
                            ->where('rep_id', $repId)
                            ->where('rep_acceptance_status', 'accepted')
                            ->get();

                        $data = [];
                        foreach ($customers as $customer) {
                            $data[] = [
                                $customer->customer_name,
                                $customer->phone_number,
                                $customer->address,
                                $customer->city,
                                $customer->created_at->format('d/m/Y'),
                                $customer->orders()->exists() ? 'Yes' : 'No',
                            ];
                        }

                        return response()->streamDownload(function () use ($data) {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['Customer Name', 'Phone', 'Address', 'City', 'Date Added', 'Converted']);
                            foreach ($data as $row) {
                                fputcsv($file, $row);
                            }
                            fclose($file);
                        }, 'rep_portfolio_export_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment',
                        ]);
                    }),
            ])
            ->paginated(false);
    }
}

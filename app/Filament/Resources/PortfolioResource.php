<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Filament\Resources\PortfolioResource\Pages;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PortfolioResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Portfolio';

    protected static ?string $modelLabel = 'Portfolio Customer';

    protected static ?string $slug = 'portfolio';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role, ['rep', 'lead']);
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table)
            ->emptyStateHeading('No customers in your portfolio yet')
            ->emptyStateDescription('Customers you accept via the assigned queue will magically appear here.')
            ->filters([
                Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From Date')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                        DatePicker::make('created_until')
                            ->label('To Date')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->toolbarActions([
                Action::make('export')
                    ->label('Export Portfolio')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $user = auth()->user();
                        $query = Customer::query()->with(['rep', 'lead', 'agent']);

                        if ($user->role === 'rep') {
                            $query->where('rep_acceptance_status', 'accepted')
                                ->where('rep_id', $user->id);
                        } elseif ($user->role === 'lead') {
                            $repIds = User::where('lead_id', $user->id)->where('role', 'rep')->pluck('id');
                            $query->where('rep_acceptance_status', 'accepted')
                                ->whereIn('rep_id', $repIds);
                        }

                        $customers = $query->orderBy('created_at', 'desc')->get();
                        $data = [];
                        foreach ($customers as $customer) {
                            $data[] = [
                                $customer->customer_name,
                                $customer->phone_number,
                                $customer->address,
                                $customer->city,
                                $customer->state,
                                $customer->rep?->name ?? 'N/A',
                                $customer->lead?->name ?? 'N/A',
                                $customer->created_at->format('d/m/Y'),
                            ];
                        }

                        return response()->streamDownload(function () use ($data) {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['Customer Name', 'Phone', 'Address', 'City', 'State', 'Assigned Rep', 'Lead', 'Date Added']);
                            foreach ($data as $row) {
                                fputcsv($file, $row);
                            }
                            fclose($file);
                        }, 'portfolio_export_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment',
                        ]);
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if ($user->role === 'rep') {
            return parent::getEloquentQuery()
                ->where('rep_acceptance_status', 'accepted')
                ->where('rep_id', $user->id);
        } elseif ($user->role === 'lead') {
            $repIds = User::where('lead_id', $user->id)->where('role', 'rep')->pluck('id');

            return parent::getEloquentQuery()
                ->where('rep_acceptance_status', 'accepted')
                ->whereIn('rep_id', $repIds);
        }

        return parent::getEloquentQuery();
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPortfolios::route('/'),
            'edit' => Pages\EditPortfolio::route('/{record}/edit'),
        ];
    }
}

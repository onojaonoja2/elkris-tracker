<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Stockists\StockistResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Stockist;
use App\Models\StockistTransaction;
use App\Models\TrialOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SupervisorDashboard extends BaseDashboard
{
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role === 'supervisor';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SupervisorStatsWidget::class,
        ];
    }

    public function mount()
    {
        if (auth()->user()->role !== 'supervisor') {
            return redirect()->to(CustomerResource::getUrl('index'));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStockist')
                ->label('Add Stockist')
                ->icon('heroicon-o-plus')
                ->button()
                ->url(StockistResource::getUrl('create')),

            Action::make('addFieldAgent')
                ->label('Add Field Agent')
                ->icon('heroicon-o-user-plus')
                ->button()
                ->url(UserResource::getUrl('create')),

            Action::make('receiveStock')
                ->label('Receive Stock')
                ->icon('heroicon-o-arrow-down-on-square')
                ->button()
                ->form([
                    Select::make('stockist_id')
                        ->label('Select Stockist')
                        ->options(fn () => Stockist::where('supervisor_id', auth()->id())
                            ->pluck('name', 'id'))
                        ->required(),
                    TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->prefix('₦')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $stockist = Stockist::find($data['stockist_id']);
                    if ($stockist) {
                        $stockist->increment('stock_balance', $data['amount']);

                        StockistTransaction::create([
                            'stockist_id' => $stockist->id,
                            'user_id' => auth()->id(),
                            'type' => 'received',
                            'amount' => $data['amount'],
                            'description' => 'Stock received by stockist',
                            'transaction_date' => now()->toDateString(),
                        ]);
                    }
                })
                ->modalHeading('Receive Stock')
                ->modalButton('Receive'),

            Action::make('exportReport')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->button()
                ->form([
                    DatePicker::make('start_date')
                        ->label('Start Date')
                        ->required(),
                    DatePicker::make('end_date')
                        ->label('End Date')
                        ->required(),
                ])
                ->action(function (array $data) {
                    return $this->exportReport($data['start_date'], $data['end_date']);
                })
                ->modalHeading('Export Activity Report')
                ->modalButton('Export'),
        ];
    }

    protected function exportReport(string $startDate, string $endDate)
    {
        $transactions = StockistTransaction::whereHas('stockist', fn ($q) => $q->where('supervisor_id', auth()->id()))
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'asc')
            ->get();

        $filename = 'supervisor_report_'.date('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Date', 'Stockist', 'Type', 'Amount', 'Field Agent', 'Description']);

            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t->transaction_date->format('d/m/Y'),
                    $t->stockist->name ?? 'N/A',
                    $t->type,
                    $t->amount,
                    $t->fieldAgent->name ?? 'N/A',
                    $t->description,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

class SupervisorStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $stockists = Stockist::where('supervisor_id', $user->id)->get();
        $stockistIds = $stockists->pluck('id');

        $totalStockValue = $stockists->sum('stock_balance');
        $stockistCount = $stockists->count();
        $fieldAgentCount = User::where('role', 'field_agent')
            ->whereJsonContains('assigned_cities', fn ($query) => $query
                ->select('city')
                ->from('stockists')
                ->whereColumn('stockists.city', DB::raw('ANY(assigned_cities)'))
                ->where('supervisor_id', $user->id))
            ->count();

        return [
            Stat::make('Total Stock Value', '₦'.number_format($totalStockValue, 0))
                ->description('All stockists combined')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),
            Stat::make('Stockists', $stockistCount)
                ->description('Registered stockists')
                ->icon('heroicon-o-building-storefront')
                ->color('info'),
            Stat::make('Field Agents', $fieldAgentCount)
                ->description('Active field agents')
                ->icon('heroicon-o-users')
                ->color('warning'),
            Stat::make('Pending Trial Orders', TrialOrder::where('status', 'pending')
                ->whereHas('agent', fn ($query) => $query->whereRaw('0 = 1'))
                ->count())
                ->description('Awaiting approval')
                ->icon('heroicon-o-clock')
                ->color('danger'),
        ];
    }
}

<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasDashboardBreakdownModals;
use App\Filament\Widgets\CreditSalesOutstandingStatsWidget;
use App\Filament\Widgets\DamagedReturnsBreakdownWidget;
use App\Filament\Widgets\ManagerAgentManagementWidget;
use App\Filament\Widgets\ManagerAnalyticsWidget;
use App\Filament\Widgets\ManagerConversionWidget;
use App\Filament\Widgets\ManagerCreditSalesWidget;
use App\Filament\Widgets\ManagerCustomerSubmissionsWidget;
use App\Filament\Widgets\ManagerCustomersWidget;
use App\Filament\Widgets\ManagerPeopleByStateWidget;
use App\Filament\Widgets\ManagerPortfolioPerAgentWidget;
use App\Filament\Widgets\ManagerSalesRecordsByStateWidget;
use App\Filament\Widgets\ManagerStatsWidget;
use App\Filament\Widgets\ManagerStockLevelsOverviewWidget;
use App\Filament\Widgets\ManagerStockMovementsWidget;
use App\Filament\Widgets\OrdersPerCityChart;
use App\Filament\Widgets\OrderStatsWidget;
use App\Filament\Widgets\ProductionActivityWidget;
use App\Filament\Widgets\RevenueTrendChart;
use App\Filament\Widgets\WarehouseReturnApprovalsWidget;
use App\Models\Customer;
use App\Models\Lga;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\State;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ManagerDashboard extends BaseDashboard
{
    use HasDashboardBreakdownModals;

    protected static string $routePath = '/manager-dashboard';

    protected static ?string $slug = 'manager-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['manager', 'admin']);
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['manager', 'admin']);
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasAnyRole(['manager', 'admin'])) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            ManagerStatsWidget::class,
            ProductionActivityWidget::class,
            CreditSalesOutstandingStatsWidget::class,
            OrderStatsWidget::class,
            ManagerAnalyticsWidget::class,
            ManagerCustomerSubmissionsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            ManagerAgentManagementWidget::class,
            ManagerPeopleByStateWidget::class,
            ManagerSalesRecordsByStateWidget::class,
            ManagerCreditSalesWidget::class,
            ManagerStockLevelsOverviewWidget::class,
            ManagerStockMovementsWidget::class,
            ManagerCustomersWidget::class,
            ManagerPortfolioPerAgentWidget::class,
            ManagerConversionWidget::class,
            DamagedReturnsBreakdownWidget::class,
            WarehouseReturnApprovalsWidget::class,
            RevenueTrendChart::class,
            OrdersPerCityChart::class,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            $this->getCreditBreakdownAction(),
            $this->getOrderBreakdownAction(),
            Action::make('create_user')
                ->label('Add Agent')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->form([
                    TextInput::make('name')
                        ->label('Full Name')
                        ->required(),
                    TextInput::make('email')
                        ->label('Email Address')
                        ->email()
                        ->required()
                        ->unique('users', 'email'),
                    Select::make('role')
                        ->label('Agent Type')
                        ->options([
                            'open_market' => 'Open Market Agent',
                            'retail_market' => 'Retail Market Agent',
                        ])
                        ->required()
                        ->live()
                        ->selectablePlaceholder(false),
                    Select::make('state_id')
                        ->label('State')
                        ->options(fn () => State::pluck('name', 'id'))
                        ->searchable()
                        ->live(debounce: 300)
                        ->afterStateUpdated(fn ($set) => $set('lga_id', null))
                        ->required(),
                    Select::make('lga_id')
                        ->label('Local Government Area')
                        ->options(fn ($get) => $get('state_id')
                            ? Lga::where('state_id', $get('state_id'))->pluck('name', 'id')
                            : [])
                        ->searchable()
                        ->required(),
                    TextInput::make('password')
                        ->label('Password (leave blank to auto-generate)')
                        ->password()
                        ->helperText('If left blank, a secure one-time password will be generated and shown once.')
                        ->autocomplete('new-password'),
                ])
                ->action(function (array $data): void {
                    // Re-validate the role server-side to prevent privilege escalation via crafted payloads.
                    if (! in_array($data['role'], ['open_market', 'retail_market'], true)) {
                        Notification::make()
                            ->title('Invalid agent type')
                            ->danger()
                            ->send();

                        $this->halt();
                    }

                    // Generate a secure one-time password when none is provided; never default to a known string.
                    $plainPassword = ! empty($data['password']) ? $data['password'] : Str::random(16);

                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'role' => $data['role'],
                        'state_id' => $data['state_id'],
                        'lga_id' => $data['lga_id'],
                        'password' => Hash::make($plainPassword),
                        'lead_id' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Agent created')
                        ->body(empty($data['password'])
                            ? "{$user->name} has been created as a ".str_replace('_', ' ', $user->getPrimaryRole()).". Share this one-time password securely (it will not be shown again): **{$plainPassword}**"
                            : "{$user->name} has been created as a ".str_replace('_', ' ', $user->getPrimaryRole()).'.')
                        ->success()
                        ->persistent()
                        ->send();
                }),
            Action::make('filter_date')
                ->label('Filter by Date')
                ->icon('heroicon-o-calendar')
                ->color('secondary')
                ->form([
                    Select::make('preset')
                        ->options([
                            'today' => 'Today (8AM-5PM)',
                            'yesterday' => 'Yesterday',
                            'this_week' => 'This Week',
                            'this_month' => 'This Month',
                            'lifetime' => 'Lifetime',
                        ])
                        ->default('today')
                        ->required(),
                ])
                ->action(function (array $data) {
                    Session::put('manager_date_preset', $data['preset']);
                    $this->redirect($this->getUrl());
                })
                ->successNotificationTitle('Date filter applied'),
            Action::make('export_report')
                ->label('Export Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    $from = Session::get('manager_date_preset') === 'lifetime'
                        ? Carbon::now()->subYears(10)
                        : Carbon::now()->startOfDay();

                    $filename = 'system_report_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv';

                    return response()->streamDownload(function () use ($from) {
                        $handle = fopen('php://output', 'w');
                        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

                        fputcsv($handle, ['Section', 'Metric', 'Value']);

                        $totalCustomers = Customer::where('created_at', '>=', $from)->count();
                        $totalOrders = Order::where('created_at', '>=', $from)->where('is_migrated_order', false)->count();
                        $orderRevenue = Order::where('created_at', '>=', $from)->where('is_migrated_order', false)->sum('total_price');
                        $salesRecords = SalesRecord::where('created_at', '>=', $from)->count();
                        $pendingSales = SalesRecord::whereIn('status', ['pending', 'receipt_uploaded'])->count();
                        $activeAgents = User::whereIn('role', ['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])->active()->count();

                        fputcsv($handle, ['Sales', 'Total Customers', $totalCustomers]);
                        fputcsv($handle, ['Sales', 'Total Orders', $totalOrders]);
                        fputcsv($handle, ['Sales', 'Order Revenue (₦)', number_format($orderRevenue, 2)]);
                        fputcsv($handle, ['Sales', 'Sales Records', $salesRecords]);
                        fputcsv($handle, ['Sales', 'Pending Approvals', $pendingSales]);
                        fputcsv($handle, ['Sales', 'Active Agents', $activeAgents]);

                        $roleCounts = User::select('role', DB::raw('COUNT(*) as count'))
                            ->where('is_active', true)
                            ->groupBy('role')
                            ->pluck('count', 'role');

                        fputcsv($handle, ['Users', 'Total Active Users', $roleCounts->sum()]);
                        foreach ($roleCounts as $role => $count) {
                            fputcsv($handle, ['Users', str_replace('_', ' ', $role), $count]);
                        }

                        fclose($handle);
                    }, $filename, ['Content-Type' => 'text/csv']);
                }),
        ];
    }
}

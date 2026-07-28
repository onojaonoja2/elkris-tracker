<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DamagedReturnsBreakdownWidget;
use App\Filament\Widgets\ManagerAgentManagementWidget;
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
use App\Models\Lga;
use App\Models\State;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ManagerDashboard extends BaseDashboard
{
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
            OrdersPerCityChart::class,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
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
        ];
    }
}

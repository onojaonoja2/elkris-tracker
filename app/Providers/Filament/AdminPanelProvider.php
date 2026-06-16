<?php

namespace App\Providers\Filament;

use App\Filament\Http\Middleware\Authenticate;
use App\Filament\Pages\AccountantDashboard;
use App\Filament\Pages\AgentDashboard;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FieldAgentDashboard;
use App\Filament\Pages\LeadDashboard;
use App\Filament\Pages\ManagerDashboard;
use App\Filament\Pages\OrderSettings;
use App\Filament\Pages\Profile;
use App\Filament\Pages\RepDashboard;
use App\Filament\Pages\SalesOrdersDashboard;
use App\Filament\Pages\StockistDashboard;
use App\Filament\Pages\SupervisorDashboard;
use App\Filament\Pages\SystemMaintenance;
use App\Filament\Pages\WarehouseManagerDashboard;
use App\Filament\Widgets\NotificationBellWidget;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(Profile::class)
            ->colors([
                'primary' => Color::Cyan,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                AgentDashboard::class,
                ManagerDashboard::class,
                SupervisorDashboard::class,
                LeadDashboard::class,
                RepDashboard::class,
                FieldAgentDashboard::class,
                SalesOrdersDashboard::class,
                StockistDashboard::class,
                WarehouseManagerDashboard::class,
                AccountantDashboard::class,
                SystemMaintenance::class,
                OrderSettings::class,
            ])
            ->homeUrl(fn () => auth()->user()
                ? match (auth()->user()->role) {
                    'supervisor' => '/admin/supervisor-dashboard',
                    'lead' => '/admin/lead-dashboard',
                    'rep' => '/admin/rep-dashboard',
                    'sales' => '/admin/sales-orders-dashboard',
                    'field_agent' => '/admin/agent-dashboard',
                    'direct_sales' => '/admin/agent-dashboard',
                    'open_market' => '/admin/agent-dashboard',
                    'retail_market' => '/admin/agent-dashboard',
                    'warehouse_manager' => '/admin/warehouse-dashboard',
                    'accountant' => '/admin/accountant-dashboard',
                    'stockist' => '/admin/stockist-dashboard',
                    'manager', 'admin' => '/admin/manager-dashboard',
                    default => '/admin',
                }
                : url('/admin'))
            ->widgets([
                AccountWidget::class,
                NotificationBellWidget::class,
                // FilamentInfoWidget::class,
            ])
            ->maxContentWidth(Width::Full)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                FilamentCopilotPlugin::make(),
            ]);
    }
}

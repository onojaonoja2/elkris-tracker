<?php

namespace App\Providers\Filament;

use App\Filament\Http\Middleware\Authenticate;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FieldAgentDashboard;
use App\Filament\Pages\LeadDashboard;
use App\Filament\Pages\ManagerDashboard;
use App\Filament\Pages\RepDashboard;
use App\Filament\Pages\SupervisorDashboard;
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
            ->colors([
                'primary' => Color::Cyan,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                ManagerDashboard::class,
                SupervisorDashboard::class,
                LeadDashboard::class,
                RepDashboard::class,
                FieldAgentDashboard::class,
            ])
            ->homeUrl(fn () => match (auth()->user()->role) {
                'supervisor' => '/admin/supervisor-dashboard',
                'lead' => '/admin/lead-dashboard',
                'rep' => '/admin/rep-dashboard',
                'sales' => '/admin/sales-orders-dashboard',
                'field_agent' => '/admin/field-agent-dashboard',
                'manager', 'admin' => '/admin/manager-dashboard',
                default => '/admin',
            })
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

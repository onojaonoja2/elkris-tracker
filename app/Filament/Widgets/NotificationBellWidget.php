<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class NotificationBellWidget extends Widget
{
    protected static bool $isGlobal = true;

    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.notification-bell-widget';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return in_array(auth()->user()->role ?? null, ['admin', 'manager', 'lead', 'rep', 'sales', 'supervisor', 'field_agent']);
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->find($notificationId);
        if ($notification) {
            $notification->markAsRead();
        }
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    protected function getUnreadCount(): int
    {
        return Auth::user()?->unreadNotifications()?->count() ?? 0;
    }

    protected function getUnreadNotifications()
    {
        $user = Auth::user();

        return $user?->unreadNotifications()
            ->latest()
            ->limit(10)
            ->get() ?? collect();
    }
}

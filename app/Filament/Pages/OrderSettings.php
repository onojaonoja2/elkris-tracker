<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class OrderSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $slug = 'order-settings';

    protected static ?string $title = 'Order Settings';

    protected static ?string $navigationLabel = 'Order Settings';

    protected string $view = 'filament.pages.order-settings';

    public bool $migratedOrdersEnabled = false;

    public bool $stockAtHandEnabled = false;

    public function mount(): void
    {
        $this->migratedOrdersEnabled = Setting::getValue('migrated_orders_enabled', '0') === '1';
        $this->stockAtHandEnabled = Setting::getValue('stock_at_hand_enabled', '0') === '1';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public function save(): void
    {
        Setting::setValue('migrated_orders_enabled', $this->migratedOrdersEnabled ? '1' : '0');
        Setting::setValue('stock_at_hand_enabled', $this->stockAtHandEnabled ? '1' : '0');

        Notification::make()
            ->title('Setting saved')
            ->success()
            ->send();
    }
}

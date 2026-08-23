<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminCustomerSearchTable;
use Filament\Support\Icons\Heroicon;

class AdminCustomerSearch extends BasePage
{
    protected static ?string $title = 'Customer Search & Management';

    protected static ?string $slug = 'customer-search';

    protected static ?string $navigationLabel = 'Customer Search';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.admin-customer-search';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }

    public function getWidgets(): array
    {
        return [
            AdminCustomerSearchTable::class,
        ];
    }
}

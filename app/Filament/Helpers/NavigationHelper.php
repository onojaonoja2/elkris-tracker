<?php

namespace App\Filament\Helpers;

use App\Models\User;

class NavigationHelper
{
    public static function getDashboardUrl(User $user): string
    {
        return match ($user->getPrimaryRole()) {
            'supervisor' => '/admin/supervisor-dashboard',
            'lead' => '/admin/lead-dashboard',
            'rep' => '/admin/rep-dashboard',
            'sales' => '/admin/sales-orders-dashboard',
            'field_agent' => '/admin/agent-dashboard',
            'community_sales_representative' => '/admin/csr-dashboard',
            'open_market' => '/admin/agent-dashboard',
            'retail_market' => '/admin/agent-dashboard',
            'warehouse_manager' => '/admin/warehouse-dashboard',
            'accountant' => '/admin/accountant-dashboard',
            'general_accountant' => '/admin/general-accountant-dashboard',
            'general_manager' => '/admin/general-manager-dashboard',
            default => '/admin',
        };
    }

    public static function getRoleNavigationGroups(): array
    {
        return [
            'admin' => 'admin',
        ];
    }

    public static function getDashboardLabel(User $user): string
    {
        return match ($user->getPrimaryRole()) {
            'supervisor' => 'Supervisor Dashboard',
            'lead' => 'Lead Dashboard',
            'rep' => 'Portfolio Agent Dashboard',
            'sales' => 'Sales Dashboard',
            'field_agent' => 'Field Agent Dashboard',
            'community_sales_representative' => 'CSR Dashboard',
            'open_market', 'retail_market' => 'Agent Dashboard',
            'warehouse_manager' => 'Warehouse Dashboard',
            'accountant' => 'Accountant Dashboard',
            'general_accountant' => 'General Accountant Dashboard',
            'general_manager' => 'General Manager Dashboard',
            default => 'Dashboard',
        };
    }
}

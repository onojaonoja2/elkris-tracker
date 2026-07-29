<?php

namespace App\Filament\Navigation;

use App\Models\User;

/**
 * Provides role-based navigation grouping for Filament resources and pages.
 *
 * Consuming classes may optionally declare:
 *   protected static ?string $navigationRole = 'role_name';
 *   protected static array $navigationRoles = ['role_one', 'role_two'];
 *
 * The first matching role the current user has determines the collapsible
 * sidebar group label.
 */
trait HasRoleBasedNavigationGroup
{
    /**
     * @return array<string, string>
     */
    public static function getRoleGroupLabels(): array
    {
        return [
            'admin' => 'Administration',
            'manager' => 'Manager',
            'general_manager' => 'General Manager',
            'supervisor' => 'Supervisor',
            'lead' => 'Team Lead',
            'rep' => 'Portfolio Agent',
            'community_sales_representative' => 'Community Sales Rep',
            'open_market' => 'Open Market',
            'retail_market' => 'Retail Market',
            'field_agent' => 'Field Agent',
            'sales' => 'Sales',
            'accountant' => 'Accountant',
            'general_accountant' => 'General Accountant',
            'warehouse_manager' => 'Warehouse',
            'production_management' => 'Production',
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return static::resolveDefaultGroup();
        }

        $labels = static::getRoleGroupLabels();

        foreach (static::getNavigationRoles() as $role) {
            if ($user->hasRole($role)) {
                return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
            }
        }

        return static::resolveDefaultGroup();
    }

    /**
     * @return array<int, string>
     */
    protected static function getNavigationRoles(): array
    {
        $roles = property_exists(static::class, 'navigationRoles')
            ? static::$navigationRoles
            : [];

        if (! empty($roles)) {
            return $roles;
        }

        $role = property_exists(static::class, 'navigationRole')
            ? static::$navigationRole
            : null;

        return $role !== null ? [$role] : [];
    }

    protected static function resolveDefaultGroup(): ?string
    {
        $labels = static::getRoleGroupLabels();

        $role = property_exists(static::class, 'navigationRole')
            ? static::$navigationRole
            : null;

        if ($role !== null && isset($labels[$role])) {
            return $labels[$role];
        }

        $user = auth()->user();
        if ($user instanceof User) {
            foreach ($user->getRoles() as $role) {
                if (isset($labels[$role])) {
                    return $labels[$role];
                }
            }
        }

        return property_exists(static::class, 'navigationGroup')
            ? static::$navigationGroup ?? null
            : null;
    }
}

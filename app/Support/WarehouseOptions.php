<?php

namespace App\Support;

use App\Models\User;
use App\Models\Warehouse;

class WarehouseOptions
{
    /**
     * Resolve the warehouses available on the system.
     *
     * Any agent may request stock from any active warehouse. Falls back to all
     * warehouses so the dropdown never renders empty.
     *
     * @return array<int|string, string>
     */
    public static function for(): array
    {
        $options = Warehouse::query()->where('is_active', true)->pluck('name', 'id');

        return $options->isNotEmpty()
            ? $options->all()
            : Warehouse::query()->pluck('name', 'id')->all();
    }

    /**
     * Resolve the warehouses a sales user may fulfill orders from.
     *
     * Prefers warehouses explicitly assigned to the sales person, falling back
     * to all active warehouses so the picker never renders empty when nothing
     * has been assigned yet.
     *
     * @return array<int|string, string>
     */
    public static function forSales(User $user): array
    {
        $assigned = $user->salesWarehouses()->where('is_active', true)->pluck('name', 'id');

        if ($assigned->isNotEmpty()) {
            return $assigned->all();
        }

        return static::for();
    }

    public static function hasAssignedWarehouses(User $user): bool
    {
        return $user->salesWarehouses()->where('is_active', true)->exists();
    }
}

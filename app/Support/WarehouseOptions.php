<?php

namespace App\Support;

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
}

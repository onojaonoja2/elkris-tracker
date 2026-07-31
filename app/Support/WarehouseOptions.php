<?php

namespace App\Support;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;

class WarehouseOptions
{
    /**
     * Resolve the warehouses a user may return stock to.
     *
     * Prefers warehouses the user manages, plus warehouses in the user's
     * state, and falls back to active warehouses (then all warehouses) so the
     * dropdown never renders empty.
     *
     * @return array<int|string, string>
     */
    public static function for(User $user): array
    {
        $query = Warehouse::query()->where(function (Builder $query) use ($user) {
            $query->where('sales_person_id', $user->id);

            if (filled($user->state_id)) {
                $query->orWhere('state_id', $user->state_id);
            }
        });

        $options = $query->pluck('name', 'id');

        if ($options->isEmpty()) {
            $options = Warehouse::query()->where('is_active', true)->pluck('name', 'id');
        }

        return $options->isNotEmpty()
            ? $options->all()
            : Warehouse::query()->pluck('name', 'id')->all();
    }
}

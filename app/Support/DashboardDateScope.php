<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;

class DashboardDateScope
{
    /**
     * Read the dashboard date filter from session.
     *
     * @return array{Carbon, Carbon}
     */
    public static function fromSession(?string $fromKey = null, ?string $toKey = null): array
    {
        $fromKey = $fromKey ?? 'dashboard_date_from';
        $toKey = $toKey ?? 'dashboard_date_to';

        $from = Session::get($fromKey);
        $to = Session::get($toKey);

        $from = $from ? Carbon::parse($from)->startOfDay() : now()->startOfDay()->subYears(50);
        $to = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }

    /**
     * Convenience helper for query scoping.
     */
    public static function scope(Builder $query, string $column = 'created_at', ?string $fromKey = null, ?string $toKey = null): Builder
    {
        [$from, $to] = self::fromSession($fromKey, $toKey);

        return $query->whereBetween($column, [$from, $to]);
    }
}

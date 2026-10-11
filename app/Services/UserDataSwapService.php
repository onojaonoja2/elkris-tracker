<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserDataSwapService
{
    /**
     * Roles whose users can have their operational data swapped.
     *
     * @var array<int, string>
     */
    public const SWAPABLE_ROLES = [
        'rep',
        'lead',
        'sales',
        'field_agent',
        'community_sales_representative',
        'open_market',
        'retail_market',
    ];

    /**
     * Single-valued user columns that swap ownership between two users.
     * Audit columns (approved_by, verified_by, *_by, ...) are intentionally
     * excluded so history stays with whoever performed the action.
     *
     * @var array<string, array<int, string>>
     */
    private const COLUMN_SWAPS = [
        'customers' => ['rep_id', 'lead_id', 'agent_id'],
        'orders' => ['user_id', 'assigned_to'],
        'sales_records' => ['agent_id'],
        'trial_orders' => ['agent_id'],
        'stock_transfers' => ['from_agent_id', 'to_agent_id'],
        'stock_counts' => ['user_id'],
        'damaged_stock_returns' => ['user_id'],
        'warehouse_returns' => ['user_id'],
        'call_logs' => ['user_id'],
        'stockist_transactions' => ['user_id', 'field_agent_id'],
        'users' => ['lead_id', 'portfolio_agent_id'],
        'warehouses' => ['manager_id', 'sales_person_id'],
    ];

    /**
     * Tables whose rows swap ownership by re-inserting them with the swapped
     * user ids. Rows are re-inserted (instead of updated in place) because
     * these tables carry composite unique constraints that include the user
     * column, which an in-place CASE update would violate mid-swap.
     *
     * @var array<string, array<int, string>>
     */
    private const ROW_SWAPS = [
        'agent_stocks' => ['user_id'],
        'customer_rep' => ['user_id'],
        'customer_lead' => ['user_id'],
        'csr_portfolio_pairings' => ['user_id', 'portfolio_agent_id'],
    ];

    /**
     * Swap every piece of operational data attached to two users.
     *
     * @return array{tables: array<int, array{name: string, rows: int}>, rows: int}
     */
    public static function swap(User $userA, User $userB): array
    {
        static::assertSwapable($userA, $userB);

        return DB::transaction(function () use ($userA, $userB) {
            $summary = [];
            $totalRows = 0;

            foreach (self::COLUMN_SWAPS as $table => $columns) {
                $rows = static::swapColumns($table, $columns, $userA->id, $userB->id);

                if ($rows > 0) {
                    $summary[] = ['name' => $table, 'rows' => $rows];
                    $totalRows += $rows;
                }
            }

            foreach (self::ROW_SWAPS as $table => $userColumns) {
                $rows = static::swapRowOwnership($table, $userColumns, $userA->id, $userB->id);

                if ($rows > 0) {
                    $summary[] = ['name' => $table, 'rows' => $rows];
                    $totalRows += $rows;
                }
            }

            $rows = static::swapNotifications($userA->id, $userB->id);

            if ($rows > 0) {
                $summary[] = ['name' => 'notifications', 'rows' => $rows];
                $totalRows += $rows;
            }

            return ['tables' => $summary, 'rows' => $totalRows];
        });
    }

    /**
     * @throws ValidationException
     */
    public static function assertSwapable(User $userA, User $userB): void
    {
        if ($userA->is($userB)) {
            throw ValidationException::withMessages([
                'user_b_id' => 'Select two different users to swap.',
            ]);
        }

        if ($userA->role !== $userB->role) {
            throw ValidationException::withMessages([
                'user_b_id' => 'Both users must have the same role.',
            ]);
        }

        if (! in_array($userA->role, self::SWAPABLE_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => 'This role does not support portfolio swaps.',
            ]);
        }

        foreach ([$userA, $userB] as $user) {
            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'user_b_id' => "{$user->name} is not active.",
                ]);
            }
        }
    }

    /**
     * Swap single-valued columns between two users using one CASE update per
     * column so rows referencing user A and user B trade places atomically.
     *
     * @param  array<int, string>  $columns
     */
    private static function swapColumns(string $table, array $columns, int $userIdA, int $userIdB): int
    {
        $affected = 0;

        foreach ($columns as $column) {
            $affected += DB::table($table)
                ->whereIn($column, [$userIdA, $userIdB])
                ->update([
                    $column => DB::raw("CASE WHEN {$column} = {$userIdA} THEN {$userIdB} ELSE {$userIdA} END"),
                ]);
        }

        return $affected;
    }

    /**
     * Swap row ownership by deleting the originals and re-inserting them with
     * swapped user references, preserving every other column and the original
     * timestamps.
     *
     * @param  array<int, string>  $userColumns
     */
    private static function swapRowOwnership(string $table, array $userColumns, int $userIdA, int $userIdB): int
    {
        $rows = DB::table($table)
            ->where(function ($query) use ($userColumns, $userIdA, $userIdB) {
                foreach ($userColumns as $column) {
                    $query->orWhereIn($column, [$userIdA, $userIdB]);
                }
            })
            ->get()
            ->map(function ($row) use ($userColumns, $userIdA, $userIdB) {
                foreach ($userColumns as $column) {
                    if ((int) $row->{$column} === $userIdA) {
                        $row->{$column} = $userIdB;
                    } elseif ((int) $row->{$column} === $userIdB) {
                        $row->{$column} = $userIdA;
                    }
                }

                return (array) $row;
            });

        if ($rows->isEmpty()) {
            return 0;
        }

        DB::table($table)
            ->where(function ($query) use ($userColumns, $userIdA, $userIdB) {
                foreach ($userColumns as $column) {
                    $query->orWhereIn($column, [$userIdA, $userIdB]);
                }
            })
            ->delete();

        DB::table($table)->insert($rows->all());

        return $rows->count();
    }

    /**
     * Swap notification recipients between two users.
     */
    private static function swapNotifications(int $userIdA, int $userIdB): int
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', [$userIdA, $userIdB])
            ->update([
                'notifiable_id' => DB::raw("CASE WHEN notifiable_id = {$userIdA} THEN {$userIdB} ELSE {$userIdA} END"),
            ]);
    }
}

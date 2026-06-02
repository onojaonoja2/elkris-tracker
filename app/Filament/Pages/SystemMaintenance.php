<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class SystemMaintenance extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $slug = 'system-maintenance';

    protected string $view = 'filament.pages.system-maintenance';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role === 'admin';
    }

    public static function canAccess(): bool
    {
        return auth()->user()->role === 'admin';
    }

    public function getTableData(): array
    {
        $excludedTables = [
            'migrations', 'users', 'regions', 'states', 'lgas', 'cities',
            'product_types', 'password_reset_tokens', 'sessions',
            'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        ];

        $tables = DB::select('SHOW TABLES');
        $dbName = DB::connection()->getDatabaseName();
        $key = "Tables_in_{$dbName}";
        $data = [];

        foreach ($tables as $table) {
            $tableName = $table->$key;
            $count = DB::table($tableName)->count();
            $data[] = [
                'name' => $tableName,
                'records' => $count,
                'can_clear' => ! in_array($tableName, $excludedTables),
            ];
        }

        usort($data, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $data;
    }

    public function clearTable(string $tableName): void
    {
        $excludedTables = [
            'migrations', 'users', 'regions', 'states', 'lgas', 'cities',
            'product_types', 'password_reset_tokens', 'sessions',
            'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        ];

        if (in_array($tableName, $excludedTables)) {
            Notification::make()->danger()->title("Cannot clear protected table: {$tableName}")->send();

            return;
        }

        DB::table($tableName)->truncate();
        Notification::make()->success()->title("Table '{$tableName}' has been cleared.")->send();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemMaintenance extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $slug = 'system-maintenance';

    protected string $view = 'filament.pages.system-maintenance';

    public string $activeTab = 'overview';

    public ?string $selectedTable = null;

    public int $browsePage = 1;

    public int $browsePerPage = 25;

    public string $sqlQuery = '';

    public ?string $sqlError = null;

    public array $sqlResults = [];

    public array $sqlColumns = [];

    public int $sqlAffectedRows = 0;

    public string $sqlExecutionTime = '';

    public ?string $structureTable = null;

    public array $tableStructure = [];

    public string $sortColumn = '';

    public string $sortDirection = 'ASC';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTableList(): array
    {
        $dbName = DB::connection()->getDatabaseName();
        $results = DB::select(
            'SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, TABLE_COMMENT
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\'
            ORDER BY TABLE_NAME',
            [$dbName]
        );

        $data = [];

        foreach ($results as $row) {
            $data[] = [
                'name' => $row->TABLE_NAME,
                'records' => $row->TABLE_ROWS ?? 0,
                'size' => $this->formatBytes(($row->DATA_LENGTH ?? 0) + ($row->INDEX_LENGTH ?? 0)),
                'comment' => $row->TABLE_COMMENT ?? '',
            ];
        }

        return $data;
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function browseTable(string $tableName): void
    {
        $this->selectedTable = $tableName;
        $this->browsePage = 1;
        $this->sortColumn = '';
        $this->sortDirection = 'ASC';
        $this->activeTab = 'browse';
    }

    public function sortBrowse(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'ASC' ? 'DESC' : 'ASC';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'ASC';
        }
    }

    public function getBrowseData(): array
    {
        if (! $this->selectedTable) {
            return ['columns' => [], 'rows' => [], 'total' => 0];
        }

        $total = DB::table($this->selectedTable)->count();
        $query = DB::table($this->selectedTable);

        if ($this->sortColumn) {
            $query->orderBy($this->sortColumn, $this->sortDirection);
        }

        $rows = $query->skip(($this->browsePage - 1) * $this->browsePerPage)
            ->take($this->browsePerPage)
            ->get()
            ->toArray();

        $columns = ! empty($rows) ? array_keys((array) $rows[0]) : [];

        return [
            'columns' => $columns,
            'rows' => $rows,
            'total' => $total,
        ];
    }

    public function getBrowseTotalPages(): int
    {
        if (! $this->selectedTable) {
            return 0;
        }

        return (int) ceil(DB::table($this->selectedTable)->count() / $this->browsePerPage);
    }

    public function browseNextPage(): void
    {
        if ($this->browsePage < $this->getBrowseTotalPages()) {
            $this->browsePage++;
        }
    }

    public function browsePrevPage(): void
    {
        if ($this->browsePage > 1) {
            $this->browsePage--;
        }
    }

    public function viewStructure(string $tableName): void
    {
        $this->structureTable = $tableName;
        $dbName = DB::connection()->getDatabaseName();

        $columns = DB::select(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
            COLUMN_KEY, COLUMN_COMMENT, EXTRA, CHARACTER_MAXIMUM_LENGTH
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION',
            [$dbName, $tableName]
        );

        $indexes = DB::select(
            'SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns_list
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            GROUP BY INDEX_NAME',
            [$dbName, $tableName]
        );

        $this->tableStructure = [
            'columns' => $columns,
            'indexes' => $indexes,
        ];

        $this->activeTab = 'structure';
    }

    public function executeSql(): void
    {
        $this->sqlError = null;
        $this->sqlResults = [];
        $this->sqlColumns = [];
        $this->sqlAffectedRows = 0;
        $this->sqlExecutionTime = '';

        $query = trim($this->sqlQuery);

        if (empty($query)) {
            $this->sqlError = 'Please enter a SQL query.';

            return;
        }

        $upperQuery = strtoupper(preg_replace('/--.*$|\/\*.*?\*\//m', '', $query));
        $upperQuery = preg_replace('/\s+/', ' ', trim($upperQuery));

        $forbidden = [
            'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE',
            'RENAME', 'GRANT', 'REVOKE', 'CREATE', 'REPLACE',
        ];

        foreach ($forbidden as $word) {
            if (
                str_starts_with($upperQuery, $word.' ')
                || str_starts_with($upperQuery, $word."\n")
                || str_starts_with($upperQuery, $word.'`')
                || str_starts_with($upperQuery, $word.'"')
                || str_starts_with($upperQuery, $word."'")
                || str_contains($upperQuery, ' '.$word.' ')
            ) {
                $this->sqlError = "Operation not allowed: {$word} is forbidden for safety.";

                return;
            }
        }

        $allowedPrefixes = ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'];
        $isAllowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($upperQuery, $prefix.' ') || $upperQuery === $prefix) {
                $isAllowed = true;
                break;
            }
        }

        if (! $isAllowed) {
            $this->sqlError = 'Only read-only queries are allowed (SELECT, SHOW, DESCRIBE, EXPLAIN).';

            return;
        }

        $startTime = microtime(true);

        try {
            $results = DB::select($query);
            $this->sqlResults = array_map(fn ($row) => (array) $row, $results);
            $this->sqlColumns = ! empty($this->sqlResults) ? array_keys($this->sqlResults[0]) : [];
        } catch (\Throwable $e) {
            $this->sqlError = $e->getMessage();

            return;
        }

        $elapsed = microtime(true) - $startTime;
        $this->sqlExecutionTime = $elapsed < 1
            ? round($elapsed * 1000, 2).'ms'
            : round($elapsed, 3).'s';

        Notification::make()->success()->title('Query executed successfully')->send();
    }

    public function clearSqlQuery(): void
    {
        $this->sqlQuery = '';
        $this->sqlError = null;
        $this->sqlResults = [];
        $this->sqlColumns = [];
        $this->sqlAffectedRows = 0;
        $this->sqlExecutionTime = '';
    }

    public function clearTable(string $tableName): void
    {
        $protected = $this->getProtectedTables();

        if (in_array($tableName, $protected)) {
            Notification::make()->danger()->title("Cannot clear protected table: {$tableName}")->send();

            return;
        }

        DB::table($tableName)->truncate();
        Notification::make()->success()->title("Table '{$tableName}' has been cleared.")->send();
    }

    public function dropTable(string $tableName): void
    {
        $protected = $this->getProtectedTables();

        if (in_array($tableName, $protected)) {
            Notification::make()->danger()->title("Cannot drop protected table: {$tableName}")->send();

            return;
        }

        Schema::dropIfExists($tableName);
        Notification::make()->success()->title("Table '{$tableName}' has been dropped.")->send();
    }

    public function getProtectedTables(): array
    {
        return [
            'migrations', 'users', 'regions', 'states', 'lgas', 'cities',
            'product_types', 'password_reset_tokens', 'sessions',
            'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        ];
    }

    public function truncateAllData(): void
    {
        $protected = $this->getProtectedTables();
        $tables = $this->getTableList();
        $cleared = 0;

        foreach ($tables as $table) {
            if (! in_array($table['name'], $protected) && $table['records'] > 0) {
                DB::table($table['name'])->truncate();
                $cleared++;
            }
        }

        Notification::make()->success()->title("Cleared data from {$cleared} table(s).")->send();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        $factor = pow(1024, $i);

        return round($bytes / $factor, 2).' '.$units[$i];
    }
}

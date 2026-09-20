<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasBreakdownViewAction;
use App\Filament\Traits\HasCsrOverview;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class AccountantCsrOverviewWidget extends TableWidget
{
    use HasBreakdownViewAction;
    use HasCsrOverview {
        HasCsrOverview::userBreakdown insteadof HasBreakdownViewAction;
    }

    protected static ?string $heading = 'CSR Overview';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['accountant', 'general_accountant']);
    }

    public function table(Table $table): Table
    {
        $stats = $this->csrStats();

        return $table
            ->query(
                fn () => User::where('role', 'community_sales_representative')
                    ->with(['state', 'lga'])
                    ->orderBy('name')
            )
            ->columns($this->csrOverviewColumns($stats))
            ->recordActions([
                $this->breakdownViewAction(),
            ])
            ->defaultSort('name')
            ->headerActions([
                Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        return $this->exportXlsx();
                    }),
            ])
            ->paginated([10, 25, 50, -1]);
    }
}

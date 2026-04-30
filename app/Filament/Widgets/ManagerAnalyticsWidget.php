<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ManagerAnalyticsWidget extends TableWidget
{
    protected static ?string $heading = 'Analytics Summary';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query()->where('id', '>', 0))
            ->columns([
                TextColumn::make('id')->label('Summary'),
            ])
            ->paginated(false);
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'manager' || auth()->user()->role === 'admin';
    }
}

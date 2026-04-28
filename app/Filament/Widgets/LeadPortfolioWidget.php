<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LeadPortfolioWidget extends TableWidget
{
    protected static ?string $heading = 'Team Portfolio - Customers Under My Reps';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->role === 'lead';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $leadId = auth()->id();

                return Customer::query()
                    ->whereHas('leads', fn ($q) => $q->where('users.id', $leadId));
            })
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Customer Name')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable(),
                TextColumn::make('rep.name')
                    ->label('Assigned Rep')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Address')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('created_at')
                    ->label('Date Added')
                    ->date('d/m/Y'),
            ])
            ->paginated(false);
    }
}

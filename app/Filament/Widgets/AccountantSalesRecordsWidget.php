<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class AccountantSalesRecordsWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Sales Record Verifications';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'accountant';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SalesRecord::where('status', 'receipt_uploaded')
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
            )
            ->columns([
                TextColumn::make('agent.name')
                    ->label('Agent'),
                TextColumn::make('agent_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open_market' => 'Open Market',
                        'retail_market' => 'Retail Market',
                        default => $state,
                    }),
                TextColumn::make('total_value')
                    ->label('Total (₦)')
                    ->money('NGN'),
                TextColumn::make('vendor_name')
                    ->label('Vendor')
                    ->placeholder('-'),
                TextColumn::make('business_name')
                    ->label('Business')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime(),
            ])
            ->paginated(false);
    }
}

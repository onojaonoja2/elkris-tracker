<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LeadAgentSubmissionsWidget extends TableWidget
{
    protected static ?string $heading = 'Field Agent Submissions';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->role === 'lead';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Customer::query()->whereNotNull('agent_id')->whereNull('rep_id'))
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Customer Name')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Address')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('agent.name')
                    ->label('Submitted By')
                    ->searchable(),
                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('city')
                    ->searchable(),
                TextColumn::make('state')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('d/m/Y'),
            ])
            ->recordActions([
                Action::make('assignToRep')
                    ->label('Assign to Rep')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Select::make('rep_id')
                            ->label('Select Rep')
                            ->options(fn () => User::where('role', 'rep')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'rep_id' => $data['rep_id'],
                            'rep_acceptance_status' => 'pending',
                            'lead_id' => auth()->id(),
                        ]);
                        $record->reps()->syncWithoutDetaching([$data['rep_id']]);
                    }),
            ])
            ->paginated(false);
    }
}

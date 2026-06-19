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
use Livewire\Attributes\On;

class ManagerCustomerSubmissionsWidget extends TableWidget
{
    protected static ?string $heading = 'Agent Customer Submissions';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['manager', 'admin', 'general_manager']);
    }

    public function table(Table $table): Table
    {
        $managerId = auth()->id();

        return $table
            ->query(fn (): Builder => Customer::query()
                ->where('submission_target_type', 'manager')
                ->where('lead_id', $managerId)
                ->whereNull('rep_id')
                ->whereNull('rep_acceptance_status'))
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
                TextColumn::make('city')
                    ->label('City')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('d/m/Y'),
            ])
            ->defaultSort('created_at', 'desc')
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
                            'submission_target_type' => 'rep',
                        ]);
                        $record->reps()->syncWithoutDetaching([$data['rep_id']]);
                        $this->dispatch('refresh-dashboard');
                    }),
                Action::make('assignToLead')
                    ->label('Assign to Lead')
                    ->icon('heroicon-o-users')
                    ->form([
                        Select::make('lead_id')
                            ->label('Select Team Lead')
                            ->options(fn () => User::where('role', 'lead')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'lead_id' => $data['lead_id'],
                            'rep_acceptance_status' => 'pending',
                            'submission_target_type' => 'lead',
                        ]);
                        $record->leads()->syncWithoutDetaching([$data['lead_id']]);
                        $this->dispatch('refresh-dashboard');
                    }),
            ])
            ->paginated(false);
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class RepPendingAssignmentsWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Assignments';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'rep';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Customer::query()
                ->where('rep_id', auth()->id())
                ->where('rep_acceptance_status', 'pending'))
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
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('d/m/Y'),
            ])
            ->recordActions([
                Action::make('accept')
                    ->label('Accept')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->action(function ($record) {
                        $record->update([
                            'rep_acceptance_status' => 'accepted',
                            'rejection_note' => null,
                        ]);
                        $this->dispatch('refresh-dashboard');
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->form([
                        Textarea::make('rejection_note')
                            ->label('Reason for Rejection')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'rep_id' => null,
                            'rep_acceptance_status' => 'rejected',
                            'rejected_at' => now(),
                            'rejected_by' => auth()->id(),
                            'rejection_note' => $data['rejection_note'],
                        ]);
                        $record->reps()->detach();
                        $this->dispatch('refresh-dashboard');
                    }),
            ])
            ->paginated(false);
    }
}

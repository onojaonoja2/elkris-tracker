<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class LeadCsrAssignmentWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'CSR Assignment';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('lead');
    }

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        $leadId = auth()->id();

        $repIds = User::where('lead_id', $leadId)
            ->where('role', 'rep')
            ->pluck('id');

        return $table
            ->query(
                fn () => User::where('role', 'community_sales_representative')
                    ->with(['state', 'lga'])
                    ->orderBy('name')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('CSR Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('N/A'),

                TextColumn::make('lga.name')
                    ->label('LGA')
                    ->searchable(),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Suspended'),

                TextColumn::make('assignment_status')
                    ->label('Assigned To')
                    ->getStateUsing(function (User $record) use ($leadId, $repIds): string {
                        if ($record->portfolio_agent_id === null) {
                            return 'Unassigned';
                        }
                        if ($record->portfolio_agent_id === $leadId) {
                            return 'You (Lead)';
                        }
                        if ($repIds->contains($record->portfolio_agent_id)) {
                            $rep = User::find($record->portfolio_agent_id);

                            return 'Rep: '.$rep->name;
                        }

                        return 'Other';
                    })
                    ->badge()
                    ->color(function (User $record) use ($leadId, $repIds): string {
                        if ($record->portfolio_agent_id === null) {
                            return 'gray';
                        }
                        if ($record->portfolio_agent_id === $leadId) {
                            return 'success';
                        }
                        if ($repIds->contains($record->portfolio_agent_id)) {
                            return 'info';
                        }

                        return 'warning';
                    }),
            ])
            ->recordActions([
                Action::make('assignToMe')
                    ->label('Assign to Me')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->size('sm')
                    ->visible(fn (User $record): bool => $record->portfolio_agent_id !== auth()->id())
                    ->requiresConfirmation()
                    ->modalHeading('Assign CSR to Yourself')
                    ->modalDescription(fn (User $record): string => "Assign {$record->name} to yourself as portfolio agent?")
                    ->modalButton('Assign')
                    ->action(function (User $record) {
                        $record->update(['portfolio_agent_id' => auth()->id()]);

                        Notification::make()
                            ->title('CSR assigned')
                            ->body("{$record->name} has been assigned to you.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('assignToRep')
                    ->label('Assign to Rep')
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->size('sm')
                    ->visible(function (User $record) use ($repIds): bool {
                        return $repIds->isNotEmpty() && $record->portfolio_agent_id !== auth()->id();
                    })
                    ->form([
                        Select::make('rep_id')
                            ->label('Select Rep')
                            ->options(fn () => User::where('lead_id', auth()->id())
                                ->where('role', 'rep')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        $rep = User::find($data['rep_id']);

                        if (! $rep || $rep->lead_id !== auth()->id()) {
                            return;
                        }

                        $record->update(['portfolio_agent_id' => $rep->id]);

                        Notification::make()
                            ->title('CSR assigned')
                            ->body("{$record->name} has been assigned to {$rep->name}.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('reassign')
                    ->label('Reassign')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->size('sm')
                    ->visible(fn (User $record): bool => $record->portfolio_agent_id !== null)
                    ->form([
                        Select::make('new_agent_id')
                            ->label('Reassign To')
                            ->options(function () use ($leadId, $repIds) {
                                $options = [$leadId => 'Myself (Lead)'];

                                foreach ($repIds as $repId) {
                                    $rep = User::find($repId);
                                    if ($rep) {
                                        $options[$repId] = 'Rep: '.$rep->name;
                                    }
                                }

                                return $options;
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->update(['portfolio_agent_id' => $data['new_agent_id']]);

                        $newAgent = User::find($data['new_agent_id']);
                        Notification::make()
                            ->title('CSR reassigned')
                            ->body("{$record->name} has been reassigned to {$newAgent->name}.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('unassign')
                    ->label('Unassign')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->size('sm')
                    ->visible(fn (User $record): bool => $record->portfolio_agent_id !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Unassign CSR')
                    ->modalDescription(fn (User $record): string => "Remove {$record->name}'s portfolio agent assignment?")
                    ->modalButton('Unassign')
                    ->action(function (User $record) {
                        $record->update(['portfolio_agent_id' => null]);

                        Notification::make()
                            ->title('CSR unassigned')
                            ->body("{$record->name} has been unassigned.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, -1]);
    }
}

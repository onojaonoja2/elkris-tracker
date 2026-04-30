<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\CallLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('lead.name')
                    ->label('Lead Name')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                TextColumn::make('rep.name')
                    ->label('Rep Name')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                TextColumn::make('rep.my_id')
                    ->label('Rep Internal ID')
                    ->formatStateUsing(fn ($state): string => 'rep-'.$state)
                    ->sortable()
                    ->searchable(query: function ($query, $search) {
                        $search = preg_replace('/^rep-/', '', $search);

                        return $query->whereHas('rep', function ($q) use ($search) {
                            $q->where('my_id', 'like', "%{$search}%");
                        });
                    })
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                TextColumn::make('agent.name')
                    ->label('Agent Name')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                TextColumn::make('customer_name')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone_number')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('address')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('age')
                    ->numeric()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),
                TextColumn::make('gender')
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),
                TextColumn::make('city')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => in_array(auth()->user()->role, ['admin', 'manager', 'lead', 'rep'])),
                TextColumn::make('state')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => in_array(auth()->user()->role, ['admin', 'manager', 'lead', 'rep'])),
                TextColumn::make('region')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),
                TextColumn::make('rep_acceptance_status')
                    ->label('Assignment Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'Unassigned')
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('customer_status')
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),
                TextColumn::make('diabetic_awareness')
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),
                TextColumn::make('call_date')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),
                TextColumn::make('preffered_call_time')
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),
                TextColumn::make('follow_up_date')
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),
            ])
            ->filters([
                Filter::make('call_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(
                        fn ($query, array $data) => $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('call_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('call_date', '<=', $date))
                    ),
            ])
            ->recordActions([
                Action::make('assignToLead')
                    ->label(fn ($record) => $record->lead_id ? 'Reassign Lead' : 'Assign to Lead')
                    ->color(fn ($record) => $record->lead_id ? 'success' : 'primary')
                    ->icon('heroicon-o-users')
                    ->visible(fn ($record) => in_array(auth()->user()->role, ['admin', 'manager']) || (auth()->user()->role === 'lead' && $record->agent_id !== null))
                    ->form([
                        Select::make('lead_id')
                            ->label('Select Team Lead')
                            ->options(User::where('role', 'lead')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update(['lead_id' => $data['lead_id']]);
                        $record->leads()->syncWithoutDetaching([$data['lead_id']]);
                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('assignToRep')
                    ->label(fn ($record) => $record->rep_id ? 'Reassign' : 'Assign to Rep')
                    ->color(fn ($record) => $record->rep_id ? 'success' : 'primary')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn ($record) => in_array(auth()->user()->role, ['admin', 'manager']) || (auth()->user()->role === 'lead' && $record->lead_id == auth()->id() && $record->agent_id !== null))
                    ->form([
                        Select::make('rep_id')
                            ->label('Select Rep')
                            ->options(function () {
                                return User::where('role', 'rep')->pluck('name', 'id');
                            })
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'rep_id' => $data['rep_id'],
                            'rep_acceptance_status' => 'pending',
                            'lead_id' => auth()->id(),
                        ]);
                        $record->reps()->syncWithoutDetaching([$data['rep_id']]);
                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('acceptAssignment')
                    ->label('Accept')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn ($record) => in_array(auth()->user()->role, ['rep', 'lead']) && $record->rep_acceptance_status === 'pending' && $record->rep_id === auth()->id())
                    ->action(function ($record) {
                        $record->update([
                            'rep_acceptance_status' => 'accepted',
                            'rejection_note' => null,
                        ]);
                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('rejectAssignment')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn ($record) => in_array(auth()->user()->role, ['rep', 'lead']) && $record->rep_acceptance_status === 'pending' && $record->rep_id === auth()->id())
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

                Action::make('rejectByTeamLead')
                    ->label('Reject Customer')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn ($record) => auth()->user()->role === 'lead' && $record->lead_id === auth()->id() && $record->rep_acceptance_status !== 'rejected')
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

                Action::make('requestReplacement')
                    ->label('Request Replacement')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record) => auth()->user()->role === 'lead' && $record->lead_id === auth()->id() && $record->rep_acceptance_status === 'rejected' && ! $record->needs_replacement)
                    ->action(function ($record) {
                        $record->update([
                            'needs_replacement' => true,
                            'replacement_requested_by' => auth()->id(),
                            'replacement_requested_at' => now(),
                            'lead_id' => null,
                        ]);
                        $record->leads()->detach();
                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('logCall')
                    ->label('Log Call')
                    ->color('info')
                    ->icon('heroicon-o-phone')
                    ->visible(fn ($record) => in_array(auth()->user()->role, ['rep', 'lead', 'admin', 'manager']))
                    ->form([
                        DatePicker::make('called_at')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->default(now()),
                        Select::make('outcome')
                            ->options([
                                'connected' => 'Connected',
                                'voicemail' => 'Left Voicemail',
                                'not_reachable' => 'Not Reachable',
                                'wrong_number' => 'Wrong Number',
                                'callback' => 'Will Call Back',
                                'no_answer' => 'No Answer',
                            ])
                            ->required(),
                        Textarea::make('notes')
                            ->rows(3),
                        Textarea::make('other_comment')
                            ->label('Other Comment')
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        CallLog::create([
                            'user_id' => auth()->id(),
                            'customer_id' => $record->id,
                            'called_at' => $data['called_at'] ?? now(),
                            'outcome' => $data['outcome'],
                            'notes' => $data['notes'],
                            'other_comment' => $data['other_comment'],
                        ]);
                        $this->dispatch('refresh-dashboard');
                    })
                    ->successNotificationTitle('Call logged successfully'),

                ViewAction::make(),
                EditAction::make()
                    ->visible(fn () => auth()->user()->role !== 'sales'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

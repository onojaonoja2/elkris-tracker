<?php

namespace App\Filament\Resources\Customers\Tables;

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
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),
                TextColumn::make('state')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),
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
                            'rep_acceptance_status' => 'pending',
                            'lead_id' => null,
                            'rejection_note' => $data['rejection_note'],
                        ]);
                        $record->leads()->detach();
                        $record->reps()->detach();
                    }),

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

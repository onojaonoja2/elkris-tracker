<?php

namespace App\Filament\Widgets;

use App\Enums\CustomerPriority;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AdminCustomerSearchTable extends TableWidget
{
    protected static ?string $heading = 'Customer Search & Management';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'manager', 'general_manager']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => Customer::query()
                    ->with(['lead', 'rep', 'agent'])
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('phone_number')
                    ->searchable(),
                TextColumn::make('address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('region')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agent.name')
                    ->label('Agent')
                    ->searchable()
                    ->placeholder('Unassigned'),
                TextColumn::make('rep.name')
                    ->label('Rep')
                    ->searchable()
                    ->placeholder('Unassigned'),
                TextColumn::make('lead.name')
                    ->label('Team Lead')
                    ->searchable()
                    ->placeholder('Unassigned'),
                TextColumn::make('rep_acceptance_status')
                    ->label('Assignment')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'Unassigned')
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('priority')
                    ->badge()
                    ->sortable(),
                TextColumn::make('customer_status')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('agent_id')
                    ->label('Agent')
                    ->options(fn () => User::whereIn('role', [
                        'field_agent',
                        'community_sales_representative',
                        'open_market',
                        'retail_market',
                    ])->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('rep_id')
                    ->label('Rep')
                    ->options(fn () => User::where('role', 'rep')->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('lead_id')
                    ->label('Team Lead')
                    ->options(fn () => User::where('role', 'lead')->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('priority')
                    ->label('Priority')
                    ->options(CustomerPriority::class),
                SelectFilter::make('customer_status')
                    ->label('Status')
                    ->options(fn () => Customer::query()->distinct()->pluck('customer_status')->filter()),
            ])
            ->recordActions([
                Action::make('reassignAgent')
                    ->label(fn (Customer $record): string => $record->agent_id ? 'Reassign Agent' : 'Assign to Agent')
                    ->icon('heroicon-o-user-group')
                    ->color('primary')
                    ->form([
                        Select::make('agent_id')
                            ->label('Select Agent')
                            ->options(fn () => User::whereIn('role', [
                                'field_agent',
                                'community_sales_representative',
                                'open_market',
                                'retail_market',
                            ])->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Customer $record, array $data): void {
                        $record->update(['agent_id' => $data['agent_id']]);

                        Notification::make()->title('Customer reassigned to agent')->success()->send();
                    }),

                Action::make('reassignRep')
                    ->label(fn (Customer $record): string => $record->rep_id ? 'Reassign Rep' : 'Assign to Rep')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->form([
                        Select::make('rep_id')
                            ->label('Select Rep')
                            ->options(fn () => User::where('role', 'rep')->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Customer $record, array $data): void {
                        $record->update([
                            'rep_id' => $data['rep_id'],
                            'rep_acceptance_status' => 'pending',
                            'lead_id' => $record->lead_id ?? auth()->id(),
                        ]);
                        $record->reps()->syncWithoutDetaching([$data['rep_id']]);

                        Notification::make()->title('Customer reassigned to rep')->success()->send();
                    }),

                Action::make('reassignLead')
                    ->label(fn (Customer $record): string => $record->lead_id ? 'Reassign Team Lead' : 'Assign to Team Lead')
                    ->icon('heroicon-o-users')
                    ->color('warning')
                    ->form([
                        Select::make('lead_id')
                            ->label('Select Team Lead')
                            ->options(fn () => User::where('role', 'lead')->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Customer $record, array $data): void {
                        $record->update(['lead_id' => $data['lead_id']]);
                        $record->leads()->syncWithoutDetaching([$data['lead_id']]);

                        Notification::make()->title('Customer reassigned to team lead')->success()->send();
                    }),

                EditAction::make()
                    ->url(fn (Customer $record): string => CustomerResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ])
            ->paginated(25);
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class UnassignedCustomersWidget extends TableWidget
{
    protected static ?string $heading = 'Unassigned Customers';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Customer::query()->unassigned())
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Customer Name')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable(),
                TextColumn::make('city')
                    ->label('City')
                    ->searchable(),
                TextColumn::make('state')
                    ->label('State')
                    ->searchable(),
                TextColumn::make('manager_visible_at')
                    ->label('Manager Visibility')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Left for Manager' : 'Admin Only')
                    ->color(fn ($state): string => $state ? 'warning' : 'gray'),
                TextColumn::make('created_at')
                    ->label('Date Added')
                    ->date('d/m/Y'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('assignToRep')
                    ->label('Assign to Rep (EPA)')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Select::make('rep_id')
                            ->label('Select Rep or Team Lead')
                            ->options(fn () => User::portfolioAssigneeOptions())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Customer $record, array $data) {
                        $assignee = User::findOrFail($data['rep_id']);

                        if ($assignee->hasRole('lead')) {
                            $record->assignToLeadPortfolio($assignee);
                            $this->dispatch('refresh-dashboard');

                            Notification::make()
                                ->title('Customer assigned to team lead')
                                ->body("{$record->customer_name} is now in {$assignee->name}'s portfolio.")
                                ->success()
                                ->send();

                            return;
                        }

                        $record->update([
                            'rep_id' => $assignee->id,
                            'rep_acceptance_status' => 'pending',
                            'submission_target_type' => 'rep',
                            'manager_visible_at' => null,
                        ]);
                        $record->reps()->syncWithoutDetaching([$assignee->id]);
                        $this->dispatch('refresh-dashboard');

                        Notification::make()
                            ->title('Customer assigned to rep')
                            ->body("{$record->customer_name} is now awaiting rep acceptance.")
                            ->success()
                            ->send();
                    }),
                Action::make('assignToSales')
                    ->label('Assign to Sales')
                    ->icon('heroicon-o-shopping-cart')
                    ->form([
                        Select::make('sales_id')
                            ->label('Select Sales User')
                            ->options(fn () => User::where('role', 'sales')->active()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Customer $record, array $data) {
                        $record->update([
                            'agent_id' => $data['sales_id'],
                            'manager_visible_at' => null,
                        ]);
                        $this->dispatch('refresh-dashboard');

                        Notification::make()
                            ->title('Customer assigned to sales')
                            ->body("{$record->customer_name} is now handled by sales.")
                            ->success()
                            ->send();
                    }),
                Action::make('assignToLead')
                    ->label('Assign to Team Lead')
                    ->icon('heroicon-o-users')
                    ->form([
                        Select::make('lead_id')
                            ->label('Select Team Lead')
                            ->options(fn () => User::where('role', 'lead')->active()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Customer $record, array $data) {
                        $record->update([
                            'lead_id' => $data['lead_id'],
                            'rep_acceptance_status' => 'pending',
                            'submission_target_type' => 'lead',
                            'manager_visible_at' => null,
                        ]);
                        $record->leads()->syncWithoutDetaching([$data['lead_id']]);
                        $this->dispatch('refresh-dashboard');

                        Notification::make()
                            ->title('Customer assigned to team lead')
                            ->body("{$record->customer_name} is now awaiting lead acceptance.")
                            ->success()
                            ->send();
                    }),
                Action::make('leaveForManager')
                    ->label('Leave for Manager')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->visible(fn (Customer $record): bool => $record->manager_visible_at === null)
                    ->action(function (Customer $record) {
                        $record->update(['manager_visible_at' => now()]);
                        $this->dispatch('refresh-dashboard');

                        Notification::make()
                            ->title('Customer left for manager')
                            ->body("{$record->customer_name} is now viewable by managers.")
                            ->success()
                            ->send();
                    }),
                Action::make('hideFromManager')
                    ->label('Hide from Manager')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->visible(fn (Customer $record): bool => $record->manager_visible_at !== null)
                    ->action(function (Customer $record) {
                        $record->update(['manager_visible_at' => null]);
                        $this->dispatch('refresh-dashboard');

                        Notification::make()
                            ->title('Customer hidden from managers')
                            ->body("{$record->customer_name} is now admin only.")
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated([10, 25, 50]);
    }
}

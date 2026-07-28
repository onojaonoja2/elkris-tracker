<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class LeadCsrSubmissionsWidget extends TableWidget
{
    protected static ?string $heading = 'CSR Customer Submissions';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole('lead')) {
            return false;
        }

        return User::where('portfolio_agent_id', $user->id)
            ->where('role', 'community_sales_representative')
            ->exists();
    }

    public function table(Table $table): Table
    {
        $leadId = auth()->id();

        return $table
            ->query(fn (): Builder => Customer::query()
                ->where('submission_target_type', 'lead')
                ->where('lead_id', $leadId)
                ->where('rep_acceptance_status', 'pending')
                ->whereNull('rep_id'))
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
                    ->label('Submitted By CSR')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('d/m/Y'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('accept')
                    ->label('Accept')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->action(function ($record) {
                        $lead = auth()->user();

                        $record->update([
                            'rep_id' => $lead->id,
                            'rep_acceptance_status' => 'accepted',
                            'rejection_note' => null,
                        ]);
                        $record->reps()->syncWithoutDetaching([$lead->id]);
                        $record->leads()->syncWithoutDetaching([$lead->id]);
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
                            'lead_id' => null,
                            'rep_acceptance_status' => 'rejected',
                            'rejected_at' => now(),
                            'rejected_by' => auth()->id(),
                            'rejection_note' => $data['rejection_note'],
                        ]);
                        $record->leads()->detach();
                        $record->reps()->detach();
                        $this->dispatch('refresh-dashboard');
                    }),
            ])
            ->paginated([10, 25, 50]);
    }
}

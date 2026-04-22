<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LeadPendingAssignmentsWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Rep Acceptance';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->role === 'lead';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Customer::query()
                ->where('lead_id', auth()->id())
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
                TextColumn::make('rep.name')
                    ->label('Assigned To')
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
                            'rep_acceptance_status' => 'pending',
                            'lead_id' => null,
                            'rejection_note' => $data['rejection_note'],
                        ]);
                        $record->leads()->detach();
                        $record->reps()->detach();
                    }),
            ])
            ->paginated(false);
    }
}

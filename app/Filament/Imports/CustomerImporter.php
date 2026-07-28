<?php

namespace App\Filament\Imports;

use App\Models\Customer;
use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;

class CustomerImporter extends Importer
{
    protected static ?string $model = Customer::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('customer_name')
                ->label('Customer Name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('phone_number')
                ->label('Phone Number')
                ->requiredMapping()
                ->rules(['required', 'max:11', 'unique:customers,phone_number']),
            ImportColumn::make('address')
                ->label('Address'),
            ImportColumn::make('city')
                ->label('City')
                ->rules(['max:255']),
            ImportColumn::make('state')
                ->label('State')
                ->rules(['max:255']),
            ImportColumn::make('region')
                ->label('Region')
                ->rules(['max:255']),
            ImportColumn::make('age')
                ->label('Age')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:0', 'max:150']),
            ImportColumn::make('gender')
                ->label('Gender')
                ->rules(['nullable', 'in:male,female']),
            ImportColumn::make('priority')
                ->label('Priority')
                ->rules(['nullable', 'in:high,medium,low']),
            ImportColumn::make('customer_status')
                ->label('Customer Status')
                ->rules(['max:255']),
            ImportColumn::make('diabetic_awareness')
                ->label('Diabetic Awareness')
                ->rules(['nullable', 'in:yes,no,unknown']),
            ImportColumn::make('call_date')
                ->label('Call Date')
                ->rules(['nullable', 'date']),
            ImportColumn::make('preffered_call_time')
                ->label('Preferred Call Time')
                ->rules(['max:255']),
            ImportColumn::make('follow_up_date')
                ->label('Follow-up Date')
                ->rules(['nullable', 'date']),
        ];
    }

    protected function afterCreate(): void
    {
        $user = $this->import->user;

        if (! $user) {
            return;
        }

        if ($user->hasRole('lead')) {
            $this->record->updateQuietly([
                'lead_id' => $user->id,
                'agent_id' => $user->id,
                'rep_acceptance_status' => 'accepted',
            ]);
            $this->record->leads()->syncWithoutDetaching([$user->id]);
        } elseif ($user->hasRole('rep')) {
            $this->record->updateQuietly([
                'rep_id' => $user->id,
                'lead_id' => $user->lead_id ?? null,
                'rep_acceptance_status' => 'accepted',
            ]);
            $this->record->reps()->syncWithoutDetaching([$user->id]);
            if ($user->lead_id) {
                $this->record->leads()->syncWithoutDetaching([$user->lead_id]);
            }
        } elseif (auth()->user()->hasAnyRole(['admin', 'manager'])) {
            $leadId = $this->options['lead_id'] ?? null;
            $repId = $this->options['rep_id'] ?? null;

            if ($leadId) {
                $this->record->updateQuietly(['lead_id' => $leadId]);
                $this->record->leads()->syncWithoutDetaching([$leadId]);
            }
            if ($repId) {
                $this->record->updateQuietly([
                    'rep_id' => $repId,
                    'rep_acceptance_status' => 'accepted',
                ]);
                $this->record->reps()->syncWithoutDetaching([$repId]);
            }
        }
    }

    public static function getOptionsFormComponents(): array
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['admin', 'manager'])) {
            return [
                Select::make('lead_id')
                    ->label('Assign to Lead')
                    ->options(User::where('role', 'lead')->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Select lead (optional)'),
                Select::make('rep_id')
                    ->label('Assign to Rep')
                    ->options(User::where('role', 'rep')->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Select rep (optional)'),
            ];
        }

        return [];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your customer import has completed with '.number_format($import->successful_rows).' imported rows.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' rows failed to import.';
        }

        return $body;
    }
}

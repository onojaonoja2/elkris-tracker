<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $user = auth()->user();
        if ($user && $user->role === 'lead') {
            $this->record->leads()->syncWithoutDetaching([$user->id]);
        }
        $this->dispatch('refresh-dashboard');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $payload = $data;
        $user = auth()->user();

        if ($user && $user->role === 'rep') {
            $payload['rep_id'] = $user->id;
            if (empty($payload['lead_id'])) {
                $payload['lead_id'] = $user->lead_id ?? null;
            }
            $data['reps'] = array_unique(array_merge($data['reps'] ?? [], [$user->id]));
            if (! empty($payload['lead_id'])) {
                $data['leads'] = array_unique(array_merge($data['leads'] ?? [], [$payload['lead_id']]));
            }
        } elseif ($user && $user->role === 'lead') {
            $payload['lead_id'] = $user->id;
            $data['leads'] = array_unique(array_merge($data['leads'] ?? [], [$user->id]));
        }

        if (empty($payload['lead_id']) && array_key_exists('leads', $data) && ! empty($data['leads'])) {
            $payload['lead_id'] = is_array($data['leads']) ? reset($data['leads']) : $data['leads'];
        }
        if (empty($payload['rep_id']) && array_key_exists('reps', $data) && ! empty($data['reps'])) {
            $payload['rep_id'] = is_array($data['reps']) ? reset($data['reps']) : $data['reps'];
        }

        $record->update(collect($payload)->except(['leads', 'reps'])->toArray());

        if (array_key_exists('leads', $data)) {
            $record->leads()->sync($data['leads'] ?? []);
        }

        if (array_key_exists('reps', $data)) {
            $record->reps()->sync($data['reps'] ?? []);
        }

        // Ensure the form data matches what we synced so saveRelationships() doesn't overwrite
        if (! empty($data['leads'])) {
            $this->data['leads'] = $data['leads'];
        }
        if (! empty($data['reps'])) {
            $this->data['reps'] = $data['reps'];
        }

        return $record;
    }
}

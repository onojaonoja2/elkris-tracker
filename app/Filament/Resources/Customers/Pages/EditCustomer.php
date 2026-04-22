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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Ensure scalar fallbacks for legacy non-nullable columns when updating
        $payload = $data;
        $user = auth()->user();
        if ($user && $user->role === 'rep') {
            $payload['rep_id'] = $user->id;
            if (empty($payload['lead_id'])) {
                $payload['lead_id'] = $user->lead_id ?? null;
            }
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

        return $record;
    }
}

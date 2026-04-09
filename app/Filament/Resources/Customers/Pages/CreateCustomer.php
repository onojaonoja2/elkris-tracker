<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Ensure scalar fallbacks for legacy non-nullable columns
        $payload = $data;
        // If current user is a rep, default rep_id to them and lead_id to their lead
        $user = auth()->user();
        if ($user && $user->role === 'rep') {
            $payload['rep_id'] = $user->id;
            if (empty($payload['lead_id'])) {
                $payload['lead_id'] = $user->lead_id ?? null;
            }
        }

        if (empty($payload['lead_id']) && ! empty($data['leads'])) {
            $payload['lead_id'] = is_array($data['leads']) ? reset($data['leads']) : $data['leads'];
        }
        if (empty($payload['rep_id']) && ! empty($data['reps'])) {
            $payload['rep_id'] = is_array($data['reps']) ? reset($data['reps']) : $data['reps'];
        }

        // Create the customer record (exclude pivot arrays)
        $customer = static::getModel()::create(collect($payload)->except(['leads', 'reps'])->toArray());

        // Sync leads and reps into pivot tables
        if (! empty($data['leads'])) {
            $customer->leads()->sync($data['leads']);
        }

        if (! empty($data['reps'])) {
            $customer->reps()->sync($data['reps']);
        }

        return $customer;
    }
}

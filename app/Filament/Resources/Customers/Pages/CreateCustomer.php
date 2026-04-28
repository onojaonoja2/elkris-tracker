<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $payload = $data;
        $user = auth()->user();

        if ($user && $user->role === 'rep') {
            $payload['rep_id'] = $user->id;
            $payload['lead_id'] = $payload['lead_id'] ?? $user->lead_id ?? null;

            $data['reps'] = array_unique(array_merge($data['reps'] ?? [], [$user->id]));
            if (! empty($payload['lead_id'])) {
                $data['leads'] = array_unique(array_merge($data['leads'] ?? [], [$payload['lead_id']]));
            }
        } elseif ($user && $user->role === 'lead') {
            $payload['agent_id'] = $user->id;
            $payload['lead_id'] = $user->id;
            $data['leads'] = array_unique(array_merge($data['leads'] ?? [], [$user->id]));
        } elseif ($user && $user->role === 'field_agent') {
            $payload['agent_id'] = $user->id;
        }

        if (empty($payload['lead_id']) && ! empty($data['leads'])) {
            $payload['lead_id'] = is_array($data['leads']) ? reset($data['leads']) : $data['leads'];
        }
        if (empty($payload['rep_id']) && ! empty($data['reps'])) {
            $payload['rep_id'] = is_array($data['reps']) ? reset($data['reps']) : $data['reps'];
        }

        $customer = static::getModel()::create(collect($payload)->except(['leads', 'reps'])->toArray());

        if (! empty($data['leads'])) {
            $customer->leads()->sync($data['leads']);
        }

        if (! empty($data['reps'])) {
            $customer->reps()->sync($data['reps']);
        }

        return $customer;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('create');
    }
}

<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Events\CustomerCreated;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\User;
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
            $payload['rep_acceptance_status'] = 'accepted';

            $data['reps'] = array_unique(array_merge($data['reps'] ?? [], [$user->id]));
            if (! empty($payload['lead_id'])) {
                $data['leads'] = array_unique(array_merge($data['leads'] ?? [], [$payload['lead_id']]));
            }
        } elseif ($user && $user->role === 'lead') {
            $payload['agent_id'] = $user->id;
            $payload['lead_id'] = $user->id;
            $data['leads'] = array_unique(array_merge($data['leads'] ?? [], [$user->id]));
        } elseif ($user && $user->role === 'community_sales_representative') {
            // CSR submits to paired agent (rep or lead) for acceptance
            $payload['agent_id'] = $user->id;

            if ($user->portfolio_agent_id) {
                $pairedAgent = User::find($user->portfolio_agent_id);

                if ($pairedAgent && $pairedAgent->role === 'rep') {
                    $payload['rep_id'] = $pairedAgent->id;
                    $payload['rep_acceptance_status'] = 'pending';
                    $payload['submission_target_type'] = 'rep';
                    $data['reps'] = array_unique(array_merge($data['reps'] ?? [], [$pairedAgent->id]));
                } elseif ($pairedAgent && $pairedAgent->role === 'lead') {
                    $payload['lead_id'] = $pairedAgent->id;
                    $payload['rep_acceptance_status'] = 'pending';
                    $payload['submission_target_type'] = 'lead';
                    $data['leads'] = array_unique(array_merge($data['leads'] ?? [], [$pairedAgent->id]));
                }
            }
        } elseif ($user && in_array($user->role, ['open_market', 'retail_market'])) {
            // Retail/open_market submit to their managing manager
            $payload['agent_id'] = $user->id;
            if ($user->lead_id) {
                $payload['lead_id'] = $user->lead_id;
                $payload['submission_target_type'] = 'manager';
            }
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

        // Sync back to form data so saveRelationships() doesn't overwrite with empty hidden field states
        if (! empty($data['leads'])) {
            $this->data['leads'] = $data['leads'];
        }
        if (! empty($data['reps'])) {
            $this->data['reps'] = $data['reps'];
        }

        return $customer;
    }

    protected function afterCreate(): void
    {
        $user = auth()->user();
        if ($user && $user->role === 'lead') {
            $this->record->leads()->syncWithoutDetaching([$user->id]);
        }

        // Notify paired agent when CSR creates a customer
        if ($user && $user->role === 'community_sales_representative' && $user->portfolio_agent_id) {
            $portfolioAgent = User::find($user->portfolio_agent_id);
            if ($portfolioAgent) {
                CustomerCreated::dispatch($this->record, $user);
            }
        }

        $this->dispatch('refresh-dashboard');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('create');
    }
}

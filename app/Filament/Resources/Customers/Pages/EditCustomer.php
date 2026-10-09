<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\User;
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
        if ($user && $user->hasRole('lead')) {
            $this->record->leads()->syncWithoutDetaching([$user->id]);
        }
        $this->dispatch('refresh-dashboard');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $payload = $data;
        $user = auth()->user();

        $data['leads'] = $data['leads'] ?? ($this->data['leads'] ?? []);
        $data['reps'] = $data['reps'] ?? ($this->data['reps'] ?? []);

        if ($user && $user->hasRole('rep')) {
            $payload['rep_id'] = $user->id;
            if (empty($payload['lead_id'])) {
                $payload['lead_id'] = $user->lead_id ?? null;
            }
            $data['reps'] = array_unique(array_merge($data['reps'] ?? [], [$user->id]));
            if (! empty($payload['lead_id'])) {
                $data['leads'] = array_unique(array_merge($data['leads'] ?? [], [$payload['lead_id']]));
            }
        } elseif ($user && $user->hasRole('lead')) {
            $payload['lead_id'] = $user->id;
            $data['leads'] = array_unique(array_merge($data['leads'] ?? [], [$user->id]));
        } elseif ($user && $user->hasAnyRole(['manager', 'admin', 'general_manager'])) {
            // Manager assigns from retail/open_market submissions
            if (! empty($data['rep_id'])) {
                $payload['submission_target_type'] = 'rep';
                $data['reps'] = array_unique(array_merge($data['reps'] ?? [], [$data['rep_id']]));
            } elseif (! empty($data['lead_id']) && $data['lead_id'] != $user->id) {
                $payload['submission_target_type'] = 'lead';
                $data['leads'] = array_unique(array_merge($data['leads'] ?? [], [$data['lead_id']]));
            }
        }

        if (empty($payload['lead_id']) && array_key_exists('leads', $data) && ! empty($data['leads'])) {
            $payload['lead_id'] = is_array($data['leads']) ? reset($data['leads']) : $data['leads'];
        }
        if (empty($payload['rep_id']) && array_key_exists('reps', $data) && ! empty($data['reps'])) {
            $payload['rep_id'] = is_array($data['reps']) ? reset($data['reps']) : $data['reps'];
        }

        if (! empty($payload['rep_id'])) {
            $assignee = User::find($payload['rep_id']);

            if ($assignee && $assignee->hasRole('lead')) {
                $payload['rep_id'] = $assignee->id;
                $payload['lead_id'] = $assignee->id;
                $payload['rep_acceptance_status'] = 'accepted';
                $payload['submission_target_type'] = 'lead';
                $data['reps'] = array_values(array_diff($data['reps'] ?? [], [$assignee->id]));
                $data['leads'] = array_unique(array_merge($data['leads'] ?? [], [$assignee->id]));
            }
        }

        $record->update(collect($payload)->except(['leads', 'reps'])->toArray());

        if (is_array($this->data['leads'] ?? null)) {
            $record->leads()->sync($data['leads'] ?? []);
        }

        if (is_array($this->data['reps'] ?? null)) {
            $record->reps()->sync($data['reps'] ?? []);
        }

        // Ensure the form data matches what we synced so saveRelationships() doesn't overwrite
        if (is_array($this->data['leads'] ?? null)) {
            $this->data['leads'] = $data['leads'];
        }
        if (is_array($this->data['reps'] ?? null)) {
            $this->data['reps'] = $data['reps'];
        }

        return $record;
    }
}

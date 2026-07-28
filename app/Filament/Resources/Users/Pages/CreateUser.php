<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Re-validate the submitted role against the options the current user is
     * permitted to assign. The form's `Select` only narrows dropdown options,
     * so a crafted Livewire payload could otherwise set any role (e.g. admin).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $allowedRoles = array_keys(UserForm::getRoleOptions());

        if (! isset($data['role']) || ! in_array($data['role'], $allowedRoles, true)) {
            abort(403, 'You are not permitted to assign this role.');
        }

        return $data;
    }
}

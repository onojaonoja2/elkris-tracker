<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deleteUser')
                ->label('Delete User')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->visible(fn (): bool => auth()->user()->role === 'admin')
                ->requiresConfirmation()
                ->modalHeading('Delete User')
                ->modalDescription(fn (): string => 'You are about to delete '.$this->getRecord()->name.' ('.ucfirst($this->getRecord()->role).').')
                ->modalButton('Delete & Reassign')
                ->form($this->getDeleteUserFormSchema())
                ->action(function (array $data) {
                    $this->handleDeleteUser($data);
                }),
        ];
    }

    protected function getDeleteUserFormSchema(): array
    {
        $user = $this->getRecord();

        $schema = [
            TextInput::make('user_info')
                ->label('User')
                ->disabled()
                ->default($user->name.' - '.ucfirst($user->role)),

            TextInput::make('customers_count')
                ->label('Assigned Customers')
                ->disabled()
                ->default(Customer::where('rep_id', $user->id)
                    ->orWhere('lead_id', $user->id)
                    ->orWhere('agent_id', $user->id)
                    ->count()),

            TextInput::make('orders_count')
                ->label('Orders Created')
                ->disabled()
                ->default(Order::where('user_id', $user->id)->count()),
        ];

        if ($user->role === 'field_agent') {
            $schema[] = Select::make('delete_action')
                ->label('Action')
                ->options([
                    'freeze' => 'Freeze Account (Prevent Login)',
                    'delete' => 'Delete Completely',
                ])
                ->default('freeze')
                ->required();
        } else {
            $schema[] = Select::make('replacement_user_id')
                ->label('Reassign Portfolio To')
                ->options(fn () => $this->getReplacementOptions())
                ->helperText('Select a user to inherit this user\'s portfolio (leave empty to just delete)');
        }

        return $schema;
    }

    protected function getReplacementOptions(): array
    {
        $user = $this->getRecord();
        $role = $user->role;

        return match ($role) {
            'rep' => User::where('role', 'rep')->where('id', '!=', $user->id)->pluck('name', 'id')->toArray(),
            'lead' => User::where('role', 'lead')->where('id', '!=', $user->id)->pluck('name', 'id')->toArray(),
            'supervisor' => User::where('role', 'supervisor')->where('id', '!=', $user->id)->pluck('name', 'id')->toArray(),
            'sales' => User::where('role', 'sales')->where('id', '!=', $user->id)->pluck('name', 'id')->toArray(),
            default => [],
        };
    }

    protected function handleDeleteUser(array $data): void
    {
        $user = $this->getRecord();
        $replacementId = $data['replacement_user_id'] ?? null;

        if ($user->role === 'field_agent') {
            $action = $data['delete_action'] ?? 'freeze';

            if ($action === 'freeze') {
                $user->update(['is_active' => false]);

                Notification::make()
                    ->title('Field Agent Frozen')
                    ->body($user->name."'s account has been frozen and they can no longer log in.")
                    ->success()
                    ->send();
            } else {
                $user->delete();

                Notification::make()
                    ->title('Field Agent Deleted')
                    ->body($user->name.' has been deleted.')
                    ->success()
                    ->send();
            }
        } else {
            if ($replacementId) {
                $this->reassignPortfolio($user, $replacementId);
            }

            $user->delete();

            Notification::make()
                ->title('User Deleted')
                ->body($replacementId
                    ? $user->name.' has been deleted and portfolio reassigned.'
                    : $user->name.' has been deleted.')
                ->success()
                ->send();
        }

        $this->redirect(route('filament.admin.resources.users.index'));
    }

    protected function reassignPortfolio(User $leavingUser, int $replacementId): void
    {
        $role = $leavingUser->role;

        match ($role) {
            'rep' => $this->reassignRep($leavingUser, $replacementId),
            'lead' => $this->reassignLead($leavingUser, $replacementId),
            'supervisor' => $this->reassignSupervisor($leavingUser, $replacementId),
            'sales' => $this->reassignSales($leavingUser, $replacementId),
            default => null,
        };
    }

    protected function reassignRep(User $user, int $replacementId): void
    {
        Customer::where('rep_id', $user->id)->update(['rep_id' => $replacementId]);

        $customerIds = DB::table('customer_rep')->where('user_id', $user->id)->pluck('customer_id');
        $user->customersRepped()->detach($customerIds);
        if ($replacementUser = User::find($replacementId)) {
            $replacementUser->customersRepped()->syncWithoutDetaching($customerIds);
        }

        Order::where('user_id', $user->id)->update(['user_id' => $replacementId]);
    }

    protected function reassignLead(User $user, int $replacementId): void
    {
        Customer::where('lead_id', $user->id)->update(['lead_id' => $replacementId]);

        DB::table('customer_lead')
            ->where('user_id', $user->id)
            ->update(['user_id' => $replacementId]);

        User::where('lead_id', $user->id)->update(['lead_id' => $replacementId]);

        Order::where('user_id', $user->id)->update(['user_id' => $replacementId]);
    }

    protected function reassignSupervisor(User $user, int $replacementId): void
    {
        Customer::where('agent_id', $user->id)->update(['agent_id' => $replacementId]);
    }

    protected function reassignSales(User $user, int $replacementId): void
    {
        Order::where('user_id', $user->id)->update(['user_id' => $replacementId]);
    }
}

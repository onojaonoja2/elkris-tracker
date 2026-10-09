<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserDataSwapService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->getSwapUsersDataAction(),
        ];
    }

    protected function getSwapUsersDataAction(): Action
    {
        return Action::make('swapUsersData')
            ->label('Swap Users Data')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false)
            ->form([
                Select::make('role')
                    ->label('Role')
                    ->options(collect(UserDataSwapService::SWAPABLE_ROLES)
                        ->mapWithKeys(fn (string $role): array => [$role => UserRole::from($role)->getLabel()]))
                    ->live()
                    ->required(),
                Select::make('user_a_id')
                    ->label('First User')
                    ->options(fn (callable $get): array => filled($get('role'))
                        ? User::where('role', $get('role'))->active()->pluck('name', 'id')->toArray()
                        : [])
                    ->searchable()
                    ->required(),
                Select::make('user_b_id')
                    ->label('Second User')
                    ->options(fn (callable $get): array => filled($get('role'))
                        ? User::where('role', $get('role'))
                            ->active()
                            ->when(filled($get('user_a_id')), fn ($query) => $query->where('id', '!=', $get('user_a_id')))
                            ->pluck('name', 'id')
                            ->toArray()
                        : [])
                    ->searchable()
                    ->required(),
            ])
            ->requiresConfirmation()
            ->modalHeading('Swap Users Data')
            ->modalDescription('Every customer, order, sales record, stock, transfer, call log, team membership and pairing attached to each user will be swapped. Historical audit trails (who approved or verified something) stay unchanged.')
            ->modalButton('Swap Data')
            ->action(function (array $data) {
                $userA = User::findOrFail($data['user_a_id']);
                $userB = User::findOrFail($data['user_b_id']);

                UserDataSwapService::assertSwapable($userA, $userB);

                $summary = UserDataSwapService::swap($userA, $userB);

                Notification::make()
                    ->title('Users data swapped')
                    ->body("Swapped {$summary['rows']} rows across ".count($summary['tables']).' tables between '.$userA->name.' and '.$userB->name.'.')
                    ->success()
                    ->send();

                $this->dispatch('refresh-dashboard');
            });
    }
}

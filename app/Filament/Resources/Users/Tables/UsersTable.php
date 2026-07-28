<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Notifications\AccountStatusNotification;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('role')
                    ->label('Primary Role')
                    ->searchable()
                    ->color(fn (string $state): ?string => UserRole::tryFrom($state)?->color())
                    ->toggleable(),
                TextColumn::make('additional_roles')
                    ->label('Additional Roles')
                    ->badge()
                    ->color(fn (string $state): ?string => 'gray')
                    ->formatStateUsing(fn (?array $state): ?string => $state ? implode(', ', array_map(fn ($r) => UserRole::tryFrom($r)?->getLabel() ?? $r, $state)) : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('my_id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Suspended')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Suspended',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                UserResource::getViewActionForResource(UserResource::class),

                EditAction::make(),

                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->is_active
                        && $record->hasAnyRole(['community_sales_representative', 'open_market', 'retail_market'])
                        && self::canManageUser(auth()->user(), $record))
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason for Suspension')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Suspend Agent')
                    ->modalDescription(fn (User $record): string => "Are you sure you want to suspend {$record->name}? They will no longer be able to log in.")
                    ->modalButton('Suspend')
                    ->action(function (User $record, array $data) {
                        $manager = auth()->user();
                        $record->suspend($data['reason']);

                        $record->notify(new AccountStatusNotification(
                            action: 'suspended',
                            managerName: $manager->name,
                            reason: $data['reason'],
                        ));

                        Notification::make()
                            ->title('Agent Suspended')
                            ->body("{$record->name}'s account has been suspended.")
                            ->success()
                            ->send();
                    }),

                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record): bool => ! $record->is_active
                        && $record->hasAnyRole(['community_sales_representative', 'open_market', 'retail_market'])
                        && self::canManageUser(auth()->user(), $record))
                    ->requiresConfirmation()
                    ->modalHeading('Reactivate Agent')
                    ->modalDescription(fn (User $record): string => "Are you sure you want to reactivate {$record->name}? They will be able to log in again.")
                    ->modalButton('Reactivate')
                    ->action(function (User $record) {
                        $manager = auth()->user();
                        $record->reactivate();

                        $record->notify(new AccountStatusNotification(
                            action: 'reactivated',
                            managerName: $manager->name,
                        ));

                        Notification::make()
                            ->title('Agent Reactivated')
                            ->body("{$record->name}'s account has been reactivated.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->hasAnyRole(['admin', 'general_manager'])),
                ]),
            ]);
    }

    protected static function canManageUser(User $manager, User $target): bool
    {
        return $target->canBeManagedBy($manager);
    }
}

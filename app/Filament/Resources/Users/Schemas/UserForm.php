<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Customers\Schemas\CustomerForm;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->label('Phone Number')
                    ->tel()
                    ->placeholder('e.g. +2348012345678')
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                Checkbox::make('sms_notifications')
                    ->label('Receive SMS notifications')
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                // TextInput::make('role')
                //     ->required()
                //     ->default('rep'),
                TextInput::make('my_id')
                    ->label('Internal ID')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                Select::make('role')
                    ->label('User Role')
                    ->options(self::getRoleOptions())
                    ->required()
                    ->default('field_agent')
                    ->selectablePlaceholder(false)
                    ->live(),

                Select::make('assigned_cities')
                    ->label('Assigned Cities')
                    ->multiple()
                    ->options(CustomerForm::nigerianCities())
                    ->searchable()
                    ->visible(fn (callable $get) => $get('role') === 'field_agent')
                    ->required(fn (callable $get) => $get('role') === 'field_agent'),

                Select::make('lead_id')
                    ->label('Reports To')
                    ->relationship('lead', 'name', fn ($query) => $query->where('role', 'lead'))
                    ->visible(fn (callable $get) => $get('role') === 'rep') // Only shows if 'rep' is selected
                    ->required(fn (callable $get) => $get('role') === 'rep')
                    ->live(),
            ]);
    }

    public static function getRoleOptions(): array
    {
        $role = auth()->user()->role;

        if ($role === 'supervisor') {
            return [
                'field_agent' => 'Field Agent',
            ];
        }

        return [
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'supervisor' => 'Supervisor',
            'lead' => 'Team Lead',
            'rep' => 'Representative',
            'field_agent' => 'Field Agent',
            'sales' => 'Sales',
            'warehouse_manager' => 'Warehouse Manager',
            'accountant' => 'Accountant',
        ];
    }
}

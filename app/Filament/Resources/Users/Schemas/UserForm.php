<?php

namespace App\Filament\Resources\Users\Schemas;

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
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                // TextInput::make('role')
                //     ->required()
                //     ->default('rep'),
                TextInput::make('my_id')
                    ->label('Internal ID')
                    ->numeric() // Ensures only numbers can be typed
                    ->unique(ignoreRecord: true) // Prevents duplicate IDs
                    ->required(),
                Select::make('role')
                    ->label('User Role')
                    ->options([
                        'admin' => 'Administrator',
                        'manager' => 'Manager',
                        'lead' => 'Team Lead',
                        'rep' => 'Representative',
                        'field_agent' => 'Field Agent',
                        'sales' => 'Sales',
                    ])
                    ->required()
                    ->default('rep')
                    ->selectablePlaceholder(false) // Prevents picking a blank option
                    ->live(), // This tells Filament to refresh the form instantly when changed

                Select::make('assigned_cities')
                    ->label('Assigned Cities')
                    ->multiple()
                    ->options(\App\Filament\Resources\Customers\Schemas\CustomerForm::nigerianCities())
                    ->searchable()
                    ->visible(fn(callable $get) => $get('role') === 'field_agent')
                    ->required(fn(callable $get) => $get('role') === 'field_agent'),

                Select::make('lead_id')
                    ->label('Reports To')
                    ->relationship('lead', 'name', fn ($query) => $query->where('role', 'lead'))
                    ->visible(fn(callable $get) => $get('role') === 'rep') // Only shows if 'rep' is selected
                    ->required(fn(callable $get) => $get('role') === 'rep')
                    ->live(),
            ]);
    }
}

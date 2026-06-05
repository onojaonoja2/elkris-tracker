<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Models\Stockist;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
                    ->default('direct_sales')
                    ->selectablePlaceholder(false)
                    ->live(),

                TagsInput::make('assigned_cities')
                    ->label('Assigned Cities')
                    ->suggestions(fn () => collect(CustomerForm::getCityMapping())->pluck('city')->unique()->sort()->values()->toArray())
                    ->visible(fn (callable $get) => in_array($get('role'), ['direct_sales', 'open_market', 'retail_market']))
                    ->required(fn (callable $get) => in_array($get('role'), ['direct_sales', 'open_market', 'retail_market'])),

                Select::make('lead_id')
                    ->label('Reports To')
                    ->relationship('lead', 'name', fn ($query) => $query->where('role', 'lead'))
                    ->visible(fn (callable $get) => $get('role') === 'rep')
                    ->required(fn (callable $get) => $get('role') === 'rep')
                    ->live(),

                Select::make('stockist_id')
                    ->label('Stockist')
                    ->relationship('stockist', 'name')
                    ->searchable()
                    ->visible(fn (callable $get) => $get('role') === 'stockist')
                    ->required(fn (callable $get) => $get('role') === 'stockist')
                    ->live()
                    ->afterStateUpdated(function (callable $set, $state) {
                        if ($state) {
                            $stockist = Stockist::find($state);
                            if ($stockist) {
                                $set('name', $stockist->name);
                            }
                        }
                    }),
            ]);
    }

    public static function getRoleOptions(): array
    {
        $role = auth()->user()->role;

        if ($role === 'supervisor') {
            return [
                'direct_sales' => 'Direct Sales Agent',
                'open_market' => 'Open Market Agent',
                'retail_market' => 'Retail Market Agent',
                'stockist' => 'Stockist',
            ];
        }

        return [
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'supervisor' => 'Supervisor',
            'lead' => 'Team Lead',
            'rep' => 'Representative',
            'direct_sales' => 'Direct Sales Agent',
            'open_market' => 'Open Market Agent',
            'retail_market' => 'Retail Market Agent',
            'sales' => 'Sales',
            'warehouse_manager' => 'Warehouse Manager',
            'accountant' => 'Accountant',
            'stockist' => 'Stockist',
        ];
    }
}

<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Models\Lga;
use App\Models\State;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                TextInput::make('my_id')
                    ->label('Internal ID')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                Select::make('role')
                    ->label('Primary Role')
                    ->options(self::getRoleOptions())
                    ->required()
                    ->default('community_sales_representative')
                    ->selectablePlaceholder(false)
                    ->live(),

                Select::make('additional_roles')
                    ->label('Additional Roles')
                    ->options(self::getRoleOptions())
                    ->multiple()
                    ->visible(fn (): bool => auth()->user()->hasAnyRole(['admin', 'general_manager']))
                    ->live(),

                Select::make('state_id')
                    ->label('State')
                    ->options(fn () => State::pluck('name', 'id'))
                    ->searchable()
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn (Set $set) => $set('lga_id', null)),

                Select::make('lga_id')
                    ->label('Local Government Area')
                    ->options(fn (Get $get) => $get('state_id')
                        ? Lga::where('state_id', $get('state_id'))->pluck('name', 'id')
                        : [])
                    ->searchable()
                    ->live(),

                TagsInput::make('assigned_cities')
                    ->label('Assigned Cities')
                    ->suggestions(fn () => collect(CustomerForm::getCityMapping())->pluck('city')->unique()->sort()->values()->toArray())
                    ->visible(fn (callable $get) => self::hasFieldAgentRole($get))
                    ->required(fn (callable $get) => self::hasFieldAgentRole($get)),

                Select::make('lead_id')
                    ->label(fn (callable $get) => self::isOpenOrRetailMarket($get) ? 'Managing Manager' : 'Reports To')
                    ->relationship('lead', 'name', fn ($query, callable $get) => self::isOpenOrRetailMarket($get)
                        ? $query->whereIn('role', ['manager', 'admin', 'general_manager'])
                        : $query->where('role', 'lead'))
                    ->visible(fn (callable $get) => self::needsLead($get))
                    ->required(fn (callable $get) => self::isOpenOrRetailMarket($get))
                    ->searchable()
                    ->live(),

                Select::make('portfolio_agent_id')
                    ->label('Paired Agent (Rep or Lead)')
                    ->relationship('portfolioAgent', 'name', fn ($query) => $query->whereIn('role', ['rep', 'lead']))
                    ->searchable()
                    ->visible(fn (callable $get) => self::hasRoleValue($get, 'community_sales_representative'))
                    ->live(),
            ]);
    }

    protected static function hasRoleValue(callable $get, string $role): bool
    {
        $primary = $get('role');
        $additional = $get('additional_roles') ?? [];

        if ($primary === $role) {
            return true;
        }

        return in_array($role, $additional);
    }

    protected static function hasFieldAgentRole(callable $get): bool
    {
        return self::hasRoleValue($get, 'community_sales_representative')
            || self::hasRoleValue($get, 'open_market')
            || self::hasRoleValue($get, 'retail_market');
    }

    protected static function isOpenOrRetailMarket(callable $get): bool
    {
        return self::hasRoleValue($get, 'open_market')
            || self::hasRoleValue($get, 'retail_market');
    }

    protected static function needsLead(callable $get): bool
    {
        return self::hasRoleValue($get, 'rep')
            || self::hasRoleValue($get, 'open_market')
            || self::hasRoleValue($get, 'retail_market');
    }

    public static function getRoleOptions(): array
    {
        $user = auth()->user();

        if ($user->hasRole('supervisor')) {
            return [
                'community_sales_representative' => 'Community Sales Representative',
                'open_market' => 'Open Market Agent',
                'retail_market' => 'Retail Market Agent',
            ];
        }

        if ($user->hasRole('manager')) {
            return [
                'open_market' => 'Open Market Agent',
                'retail_market' => 'Retail Market Agent',
            ];
        }

        if ($user->hasRole('general_accountant')) {
            return [
                'accountant' => 'Accountant',
                'warehouse_manager' => 'Warehouse Manager',
            ];
        }

        return [
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'general_manager' => 'General Manager',
            'general_accountant' => 'General Accountant',
            'supervisor' => 'Supervisor',
            'lead' => 'Team Lead',
            'rep' => 'Elkris Portfolio Agent',
            'community_sales_representative' => 'Community Sales Representative',
            'open_market' => 'Open Market Agent',
            'retail_market' => 'Retail Market Agent',
            'sales' => 'Sales',
            'warehouse_manager' => 'Warehouse Manager',
            'accountant' => 'Accountant',
        ];
    }
}

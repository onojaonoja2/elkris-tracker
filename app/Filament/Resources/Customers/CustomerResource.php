<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use BackedEnum;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotResource;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource implements CopilotResource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Customers';

    public static function canCreate(): bool
    {
        return ! in_array(auth()->user()->role, ['sales', 'supervisor']);
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        if (in_array($user->role, ['admin', 'manager'])) {
            return parent::getEloquentQuery();
        }

        // Supervisors see customers submitted by field agents globally
        if ($user->role === 'supervisor') {
            return parent::getEloquentQuery()->whereNotNull('agent_id');
        }

        // Leads see customers they assigned (for tracking rep acceptance)
        if ($user->role === 'lead') {
            return parent::getEloquentQuery()->where('lead_id', $user->id);
        }

        // Field agents see only theirs
        if ($user->role === 'field_agent') {
            return parent::getEloquentQuery()->where('agent_id', $user->id);
        }

        // Sales personnel see customers whose orders are pending or dispatched delivery
        if ($user->role === 'sales') {
            return parent::getEloquentQuery()
                ->whereIn('delivery_status', ['pending', 'dispatched']);
        }

        // Reps see only theirs
        return parent::getEloquentQuery()->where(function (Builder $query) use ($user) {
            $query->whereHas('reps', fn ($q) => $q->where('users.id', $user->id))
                ->orWhere('rep_id', $user->id);
        });
    }

    public static function copilotResourceDescription(): ?string
    {
        return 'Manages user accounts including names, emails, roles, and permissions.';
    }

    public static function copilotTools(): array
    {
        return [
            // new \App\Filament\Resources\Customers\CustomerResource\CopilotTools\ListCustomersTool(),
            // new \App\Filament\Resources\Customers\CustomerResource\CopilotTools\SearchCustomersTool(),
            // new \App\Filament\Resources\Customers\CustomerResource\CopilotTools\CreateCustomerTool(),
            // new \App\Filament\Resources\Customers\CustomerResource\CopilotTools\ViewCustomerTool(),
            // new \App\Filament\Resources\Customers\CustomerResource\CopilotTools\EditCustomerTool(),
            // new \App\Filament\Resources\Customers\CustomerResource\CopilotTools\DeleteCustomerTool(),
        ];
    }
}

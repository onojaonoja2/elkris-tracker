<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotResource;

class CustomerResource extends Resource implements CopilotResource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Customers';

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
            //
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
        if (in_array($user->role, ['admin', 'manager'])) return parent::getEloquentQuery();
        
        // Leads see their customers AND all field agent submissions
        if ($user->role === 'lead') {
            return parent::getEloquentQuery()->where(function (Builder $query) use ($user) {
                $query->whereHas('leads', fn($q) => $q->where('users.id', $user->id))
                      ->orWhere('lead_id', $user->id)
                      ->orWhereNotNull('agent_id');
            });
        }
        
        // Field agents see only theirs
        if ($user->role === 'field_agent') {
            return parent::getEloquentQuery()->where('agent_id', $user->id);
        }

        // Reps see only theirs
        return parent::getEloquentQuery()->where(function (Builder $query) use ($user) {
            $query->whereHas('reps', fn($q) => $q->where('users.id', $user->id))
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

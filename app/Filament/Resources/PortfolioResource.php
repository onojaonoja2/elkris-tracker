<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PortfolioResource\Pages;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class PortfolioResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Portfolio';

    protected static ?string $modelLabel = 'Portfolio Customer';

    protected static ?string $slug = 'portfolio';
    
    // Sort slightly below the Customer resource (alphabetically standard is default but explicit is safer)
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role, ['rep', 'lead']);
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table)
            ->emptyStateHeading('No customers in your portfolio yet')
            ->emptyStateDescription('Customers you accept via the assigned queue will magically appear here.');
    }

    public static function getEloquentQuery(): Builder
    {
        // Enforce the portfolio rules globally for this resource
        // "accepted customers by the call reps should automatically be added under the portfolio tab"
        // "also self assigned cutomer by team leads or teamlead acceptance from other teamleads"
        return parent::getEloquentQuery()
            ->where('rep_acceptance_status', 'accepted')
            ->where('rep_id', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPortfolios::route('/'),
            'edit' => Pages\EditPortfolio::route('/{record}/edit'),
        ];
    }
}

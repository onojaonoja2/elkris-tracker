<?php

namespace App\Filament\Resources\Customers\Schemas;

// use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 1. LEAD SELECTION: Open for Admin/Lead, Locked for Rep
                Select::make('lead_id')
                    ->relationship('lead', 'name', fn($query) => $query->where('role', 'lead'))
                    ->label('Assigned Lead')
                    ->searchable()
                    ->preload()
                    ->required()
                    // ->default(fn () => auth()->user()->role === 'rep' ? auth()->user()->lead_id : null)
                    // Logic: Disable ONLY if the user is a Rep
                    // ->disabled(fn () => auth()->user()->role === 'rep')
                    ->dehydrated(), // Ensures the ID is sent even if the field is disabled

                // Select::make('lead_id')
                //     ->label('Assigned Lead')
                //     // 1. Direct query to get all Users where role is 'lead'
                //     ->options(User::query()
                //         ->where('role', 'lead')
                //         ->pluck('name', 'id') // 'name' for the label, 'id' for the value
                //     )
                //     ->searchable()
                //     // 2. Since you have 200,000 users, don't use ->preload() if the list is huge
                //     // Use ->getSearchResultsUsing() instead for high performance
                //     ->getSearchResultsUsing(fn (string $search): array => User::query()
                //         ->where('role', 'lead')
                //         ->where('name', 'like', "%{$search}%")
                //         ->limit(50)
                //         ->pluck('name', 'id')
                //         ->toArray()
                //     )
                //     // 3. Ensure we can see the name when editing an existing record
                //     ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                //     ->required()
                //     // ->default(fn () => auth()->user()->role === 'rep' ? auth()->user()->lead_id : null)
                //     // ->disabled(fn () => auth()->user()->role === 'rep')
                //     ->dehydrated(),

                // 2. REP SELECTION: Open for Admin/Lead, Hidden for Rep (Auto-fills current ID)
                Select::make('rep_id')
                    ->relationship('rep', 'name', fn($query) => $query->where('role', 'rep'))
                    ->label('Assigned Rep')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(fn() => auth()->user()->role === 'rep' ? auth()->id() : null)
                    // Logic: Only hide if the user is a Rep (Admin and Leads see the dropdown)
                    ->hidden(fn() => auth()->user()->role === 'rep'),

                TextInput::make('customer_name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('phone_number')
                    ->tel()
                    ->required(),

                TextInput::make('age')
                    ->numeric(),

                Select::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female'
                    ]),

                // TextInput::make('city'),
                Select::make('city')
                    ->options([
                        'abuja' => 'Abuja',
                        'lagos' => 'Lagos',
                    ])
                    ->required(),

                Textarea::make('address')
                    ->columnSpanFull(),

                // Select::make('status')
                //     ->options([
                //         'draft' => 'Draft',
                //         'pending' => 'Pending',
                //         'active' => 'Active',
                //         'closed' => 'Closed',
                //     ])
                //     ->required()
                //     ->default('draft'),

                Select::make('customer_status')
                    ->options([
                        'prospect' => 'Prospect',
                        'customer' => 'Customer',
                    ])
                    ->required(),

                Select::make('diabetic_awareness')
                    ->options([
                        'yes' => 'Yes',
                        'no' => 'No',
                        'unknown' => 'Unknown',
                    ]),

                DatePicker::make('call_date')
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                TextInput::make('preffered_call_time'),

                Textarea::make('feedback')
                    ->rows(3)
                    ->columnSpanFull(),

                Textarea::make('remarks')
                    ->rows(3)
                    ->columnSpanFull(),

                DatePicker::make('follow_up_date')
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                TextInput::make('order_quantity')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('Qty:'),

                Textarea::make('delivery_details')
                    ->columnSpanFull(),

                Select::make('delivery_status')
                    ->options([
                        'pending' => 'Pending',
                        'dispatched' => 'Dispatched',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ]),

                // TextInput::make('sort')
                //     ->numeric()
                //     ->default(0)
                //     ->hidden(), 
            ]);
    }
}

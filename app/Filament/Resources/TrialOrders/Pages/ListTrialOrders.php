<?php

namespace App\Filament\Resources\TrialOrders\Pages;

use App\Filament\Resources\TrialOrders\TrialOrderResource;
use App\Models\City;
use App\Models\State;
use App\Models\TrialOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListTrialOrders extends ListRecords
{
    #[Url]
    public ?string $state = null;

    protected static string $resource = TrialOrderResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            CreateAction::make()
                ->visible(fn () => in_array(auth()->user()->role, ['field_agent', 'community_sales_representative'])),

            Action::make('filter_by_location')
                ->label('Filter by Location')
                ->icon('heroicon-o-funnel')
                ->form([
                    Select::make('state_filter')
                        ->label('Select State')
                        ->options(fn () => State::pluck('name', 'name')->toArray())
                        ->placeholder('All States'),
                ])
                ->action(function (array $data) {
                    $this->state = $data['state_filter'] ?? null;

                    $this->resetTable();
                }),
        ];

        if (auth()->user()->role === 'supervisor') {
            $actions[] = Action::make('createForAgent')
                ->label('Create for Agent')
                ->icon('heroicon-o-user-plus')
                ->form([
                    Select::make('field_agent_id')
                        ->label('Select Field Agent')
                        ->options(fn () => $this->getFieldAgentOptions())
                        ->required()
                        ->searchable(),
                    Repeater::make('products')
                        ->label('Products')
                        ->schema([
                            Select::make('product_name')
                                ->label('Product')
                                ->options(self::getProductOptions())
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (callable $set) => $set('grammage', null)),
                            Select::make('grammage')
                                ->label('Grammage')
                                ->options(fn (Get $get) => self::getGrammageOptions($get('product_name')))
                                ->required(),
                            TextInput::make('quantity')
                                ->label('Qty')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                            TextInput::make('price')
                                ->label('Unit Price')
                                ->numeric()
                                ->prefix('₦')
                                ->required(),
                        ])
                        ->columns(4)
                        ->minItems(1)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->createTrialOrderForFieldAgent($data);
                })
                ->modalHeading('Create Trial Order for Field Agent')
                ->modalButton('Create');
        }

        return $actions;
    }

    protected function getFieldAgentOptions(): array
    {
        return User::whereIn('role', ['field_agent', 'community_sales_representative'])
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function getProductOptions(): array
    {
        return [
            'Elkris Oat Flour' => 'Elkris Oat Flour',
            'Elkris Plantain' => 'Elkris Plantain',
            'Elkris Poundo Yam' => 'Elkris Poundo Yam',
        ];
    }

    protected static function getGrammageOptions(?string $product): array
    {
        if (! $product) {
            return [];
        }

        return match ($product) {
            'Elkris Oat Flour' => [
                '5000' => '5000g',
                '1300' => '1300g',
                '650' => '650g',
            ],
            'Elkris Plantain' => [
                '1800' => '1800g',
                '900' => '900g',
            ],
            'Elkris Poundo Yam' => [
                '1800' => '1800g',
            ],
            default => [],
        };
    }

    protected function createTrialOrderForFieldAgent(array $data): void
    {
        $agent = User::find($data['field_agent_id']);
        if (! $agent) {
            return;
        }

        $products = $data['products'] ?? [];
        if (empty($products)) {
            return;
        }

        $totalValue = 0;
        foreach ($products as $product) {
            $qty = (float) ($product['quantity'] ?? 1);
            $price = (float) ($product['price'] ?? 0);
            $totalValue += $qty * $price;
        }

        if ($totalValue <= 0) {
            return;
        }

        $trialOrder = TrialOrder::create([
            'agent_id' => $agent->id,
            'products' => $products,
            'total_value' => $totalValue,
            'status' => 'approved',
            'approved_by' => auth()->id(),
        ]);

        $agent->increment('stock_balance', $totalValue);
    }

    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->modifyQueryUsing(function (Builder $query) {
                if (! $this->state) {
                    return;
                }

                $cityNames = City::whereHas('state', fn ($q) => $q->where('name', $this->state))
                    ->pluck('name')
                    ->toArray();

                if (empty($cityNames)) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereHas('agent', function ($q) use ($cityNames) {
                    foreach ($cityNames as $city) {
                        $q->orWhereJsonContains('assigned_cities', $city);
                    }
                });
            });
    }
}

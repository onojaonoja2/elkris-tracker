<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Widgets\StockBalanceWidget;
use App\Models\StockTransaction;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ManageOrders extends ManageRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            StockBalanceWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receive_stock')
                ->label('Receive Stock')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => in_array(auth()->user()->role, ['admin', 'sales']))
                ->form([
                    Select::make('product_name')
                        ->options([
                            'Elkris Oat Flour' => 'Elkris Oat Flour',
                            'Elkris Plantain' => 'Elkris Plantain',
                            'Elkris Poundo Yam' => 'Elkris Poundo Yam',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('grammage', null)),
                    Select::make('grammage')
                        ->label('Grammage (g)')
                        ->options(fn (Get $get): array => match ($get('product_name')) {
                            'Elkris Oat Flour' => ['5000' => '5000g', '1300' => '1300g', '650' => '650g'],
                            'Elkris Plantain' => ['1800' => '1800g', '900' => '900g'],
                            'Elkris Poundo Yam' => ['1800' => '1800g'],
                            default => [],
                        })
                        ->required(),
                    TextInput::make('quantity')
                        ->numeric()
                        ->required()
                        ->minValue(1),
                    DatePicker::make('transaction_date')
                        ->label('Transaction Date')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->default(now()),
                ])
                ->action(function (array $data): void {
                    StockTransaction::create([
                        'type' => 'received',
                        'product_name' => $data['product_name'],
                        'grammage' => $data['grammage'],
                        'quantity' => $data['quantity'],
                        'transaction_date' => $data['transaction_date'],
                        'user_id' => auth()->id(),
                    ]);
                    Notification::make()->title('Stock Received successfully!')->success()->send();
                }),

            Action::make('disburse_stock')
                ->label('Disburse Stock')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->visible(fn () => in_array(auth()->user()->role, ['admin', 'sales']))
                ->form([
                    Select::make('product_name')
                        ->options([
                            'Elkris Oat Flour' => 'Elkris Oat Flour',
                            'Elkris Plantain' => 'Elkris Plantain',
                            'Elkris Poundo Yam' => 'Elkris Poundo Yam',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('grammage', null)),
                    Select::make('grammage')
                        ->label('Grammage (g)')
                        ->options(fn (Get $get): array => match ($get('product_name')) {
                            'Elkris Oat Flour' => ['5000' => '5000g', '1300' => '1300g', '650' => '650g'],
                            'Elkris Plantain' => ['1800' => '1800g', '900' => '900g'],
                            'Elkris Poundo Yam' => ['1800' => '1800g'],
                            default => [],
                        })
                        ->required(),
                    TextInput::make('quantity')
                        ->numeric()
                        ->required()
                        ->minValue(1),
                    TextInput::make('disbursed_to')
                        ->label('Disbursed To')
                        ->required(),
                    DatePicker::make('transaction_date')
                        ->label('Transaction Date')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->default(now()),
                ])
                ->action(function (array $data): void {
                    StockTransaction::create([
                        'type' => 'disbursed',
                        'product_name' => $data['product_name'],
                        'grammage' => $data['grammage'],
                        'quantity' => $data['quantity'],
                        'transaction_date' => $data['transaction_date'],
                        'disbursed_to' => $data['disbursed_to'],
                        'user_id' => auth()->id(),
                    ]);
                    Notification::make()->title('Stock Disbursed successfully!')->success()->send();
                }),
        ];
    }
}

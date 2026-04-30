<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Stockists\StockistResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\SupervisorStatsWidget;
use App\Filament\Widgets\SupervisorStockWidget;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Utilities\Get;

class SupervisorDashboard extends BaseDashboard
{
    // Use a unique route path so this page doesn't overwrite the main dashboard route ('/')
    protected static string $routePath = '/supervisor-dashboard';

    protected static ?string $slug = 'supervisor-dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role === 'supervisor';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SupervisorStatsWidget::class,
            SupervisorStockWidget::class,
        ];
    }

    public function mount()
    {
        if (! auth()->check() || auth()->user()->role !== 'supervisor') {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStockist')
                ->label('Add Stockist')
                ->icon('heroicon-o-plus')
                ->button()
                ->url(StockistResource::getUrl('create')),

            Action::make('addFieldAgent')
                ->label('Add Field Agent')
                ->icon('heroicon-o-user-plus')
                ->button()
                ->url(UserResource::getUrl('create')),

            Action::make('receiveStock')
                ->label('Receive Stock')
                ->icon('heroicon-o-arrow-down-on-square')
                ->button()
                ->form([
                    Select::make('stockist_id')
                        ->label('Select Stockist')
                        ->options(fn () => Stockist::where('supervisor_id', auth()->id())
                            ->pluck('name', 'id'))
                        ->required(),
                    Repeater::make('products')
                        ->label('Products')
                        ->schema([
                            Select::make('product_name')
                                ->label('Product')
                                ->options(self::getProductOptions())
                                ->required()
                                ->live(),
                            Select::make('grammage')
                                ->label('Grammage')
                                ->options(fn (Get $get) => self::getGrammageOptions($get('product_name') ?? $get('products.product_name')))
                                ->required(),
                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                        ])
                        ->columns(3)
                        ->minItems(1)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $stockist = Stockist::find($data['stockist_id']);
                    if ($stockist) {
                        foreach ($data['products'] as $product) {
                            $stock = StockistStock::firstOrNew([
                                'stockist_id' => $stockist->id,
                                'product_name' => $product['product_name'],
                                'grammage' => $product['grammage'],
                            ]);

                            $stock->quantity = ($stock->quantity ?? 0) + $product['quantity'];
                            $stock->save();

                            StockistTransaction::create([
                                'stockist_id' => $stockist->id,
                                'user_id' => auth()->id(),
                                'type' => 'received',
                                'amount' => 0,
                                'description' => "Received {$product['quantity']}x {$product['product_name']} ({$product['grammage']}g)",
                                'transaction_date' => now()->toDateString(),
                            ]);
                        }
                    }
                    $this->dispatch('refresh-dashboard');
                })
                ->modalHeading('Receive Stock')
                ->modalButton('Receive')
                ->successNotificationTitle('Stock received successfully'),

            Action::make('exportReport')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->button()
                ->form([
                    DatePicker::make('start_date')
                        ->label('Start Date')
                        ->required(),
                    DatePicker::make('end_date')
                        ->label('End Date')
                        ->required(),
                ])
                ->action(function (array $data) {
                    return $this->exportReport($data['start_date'], $data['end_date']);
                })
                ->modalHeading('Export Activity Report')
                ->modalButton('Export'),
        ];
    }

    protected function exportReport(string $startDate, string $endDate)
    {
        $transactions = StockistTransaction::whereHas('stockist', fn ($q) => $q->where('supervisor_id', auth()->id()))
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'asc')
            ->get();

        $filename = 'supervisor_report_'.date('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Date', 'Stockist', 'Type', 'Amount', 'Field Agent', 'Description']);

            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t->transaction_date->format('d/m/Y'),
                    $t->stockist->name ?? 'N/A',
                    $t->type,
                    $t->amount,
                    $t->fieldAgent->name ?? 'N/A',
                    $t->description,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public static function getProductOptions(): array
    {
        return [
            'Elkris Oat Flour' => 'Elkris Oat Flour',
            'Elkris Plantain' => 'Elkris Plantain',
            'Elkris Poundo Yam' => 'Elkris Poundo Yam',
        ];
    }

    public static function getGrammageOptions(?string $product): array
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
}

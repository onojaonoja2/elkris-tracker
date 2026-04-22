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

            Action::make('export_stock_report')
                ->label('Export Stock Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('secondary')
                ->visible(fn () => in_array(auth()->user()->role, ['admin', 'sales']))
                ->action(function () {
                    $transactions = \App\Models\StockTransaction::all();
                    $deliveredProducts = \App\Models\Product::whereHas('order', function ($q) {
                        $q->where('status', 'delivered');
                    })->with('order')->get();

                    $data = [];
                    foreach ($transactions as $t) {
                        $date = $t->transaction_date ? \Carbon\Carbon::parse($t->transaction_date)->format('Y-m-d') : 'N/A';
                        $key = $date . '|' . $t->product_name . '|' . $t->grammage;
                        if (!isset($data[$key])) {
                            $data[$key] = ['Date' => $date, 'Product' => $t->product_name, 'Grammage' => $t->grammage, 'Received' => 0, 'Disbursed' => 0, 'Delivered' => 0];
                        }
                        if ($t->type === 'received') $data[$key]['Received'] += $t->quantity;
                        if ($t->type === 'disbursed') $data[$key]['Disbursed'] += $t->quantity;
                    }

                    foreach ($deliveredProducts as $p) {
                        $date = $p->order->updated_at ? $p->order->updated_at->format('Y-m-d') : 'N/A';
                        $key = $date . '|' . $p->product_name . '|' . $p->grammage;
                        if (!isset($data[$key])) {
                            $data[$key] = ['Date' => $date, 'Product' => $p->product_name, 'Grammage' => $p->grammage, 'Received' => 0, 'Disbursed' => 0, 'Delivered' => 0];
                        }
                        $data[$key]['Delivered'] += $p->quantity;
                    }

                    usort($data, fn($a, $b) => strcmp($a['Date'], $b['Date']));

                    $headers = [
                        "Content-type"        => "text/csv",
                        "Content-Disposition" => "attachment; filename=stock_report_" . date('Y_m_d_H_i_s') . ".csv",
                        "Pragma"              => "no-cache",
                        "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                        "Expires"             => "0"
                    ];

                    return response()->streamDownload(function() use($data) {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, ['Date', 'Product Name', 'Grammage (g)', 'Received', 'Disbursed', 'Delivered', 'Net Change']);

                        foreach ($data as $row) {
                            $net = $row['Received'] - $row['Disbursed'] - $row['Delivered'];
                            fputcsv($file, [$row['Date'], $row['Product'], $row['Grammage'], $row['Received'], $row['Disbursed'], $row['Delivered'], $net]);
                        }
                        fclose($file);
                    }, 'stock_report_' . date('Y_m_d_H_i_s') . '.csv', $headers);
                }),
        ];
    }
}

<?php

namespace App\Filament\Resources\StockTransactions\Pages;

use App\Filament\Resources\StockTransactions\StockTransactionResource;
use App\Models\Product;
use App\Models\StockTransaction;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageStockTransactions extends ManageRecords
{
    protected static string $resource = StockTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_stock_report')
                ->label('Export Stock Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('secondary')
                ->visible(fn () => in_array(auth()->user()->role, ['admin', 'sales']))
                ->action(function (Action $action) {
                    $livewire = $action->getLivewire();
                    $filters = $livewire->tableFilters ?? [];

                    $transactionsQuery = StockTransaction::query();
                    $deliveredQuery = Product::whereHas('order', function ($q) {
                        $q->where('status', 'delivered');
                    })->with('order');

                    if (! empty($filters['product_name']['value'])) {
                        $transactionsQuery->where('product_name', $filters['product_name']['value']);
                        $deliveredQuery->where('product_name', $filters['product_name']['value']);
                    }

                    if (! empty($filters['grammage']['value'])) {
                        $transactionsQuery->where('grammage', $filters['grammage']['value']);
                        $deliveredQuery->where('grammage', $filters['grammage']['value']);
                    }

                    if (! empty($filters['transaction_date']['created_from'])) {
                        $transactionsQuery->whereDate('transaction_date', '>=', $filters['transaction_date']['created_from']);
                        $deliveredQuery->whereHas('order', function ($q) use ($filters) {
                            $q->whereDate('updated_at', '>=', $filters['transaction_date']['created_from']);
                        });
                    }

                    if (! empty($filters['transaction_date']['created_until'])) {
                        $transactionsQuery->whereDate('transaction_date', '<=', $filters['transaction_date']['created_until']);
                        $deliveredQuery->whereHas('order', function ($q) use ($filters) {
                            $q->whereDate('updated_at', '<=', $filters['transaction_date']['created_until']);
                        });
                    }

                    $transactions = $transactionsQuery->get();
                    $deliveredProducts = $deliveredQuery->get();

                    $data = [];
                    foreach ($transactions as $t) {
                        $date = $t->transaction_date ? Carbon::parse($t->transaction_date)->format('Y-m-d') : 'N/A';
                        $key = $date.'|'.$t->product_name.'|'.$t->grammage;
                        if (! isset($data[$key])) {
                            $data[$key] = ['Date' => $date, 'Product' => $t->product_name, 'Grammage' => $t->grammage, 'Received' => 0, 'Disbursed' => 0, 'Delivered' => 0];
                        }
                        if ($t->type === 'received') {
                            $data[$key]['Received'] += $t->quantity;
                        }
                        if ($t->type === 'disbursed') {
                            $data[$key]['Disbursed'] += $t->quantity;
                        }
                    }

                    foreach ($deliveredProducts as $p) {
                        $date = $p->order->updated_at ? $p->order->updated_at->format('Y-m-d') : 'N/A';
                        $key = $date.'|'.$p->product_name.'|'.$p->grammage;
                        if (! isset($data[$key])) {
                            $data[$key] = ['Date' => $date, 'Product' => $p->product_name, 'Grammage' => $p->grammage, 'Received' => 0, 'Disbursed' => 0, 'Delivered' => 0];
                        }
                        $data[$key]['Delivered'] += $p->quantity;
                    }

                    usort($data, fn ($a, $b) => strcmp($b['Date'], $a['Date']));

                    $headers = [
                        'Content-type' => 'text/csv',
                        'Content-Disposition' => 'attachment; filename=stock_report_'.date('Y_m_d_H_i_s').'.csv',
                        'Pragma' => 'no-cache',
                        'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                        'Expires' => '0',
                    ];

                    return response()->streamDownload(function () use ($data) {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, ['Date', 'Product Name', 'Grammage (g)', 'Received', 'Disbursed', 'Delivered', 'Net Change']);

                        foreach ($data as $row) {
                            $net = $row['Received'] - $row['Disbursed'] - $row['Delivered'];
                            fputcsv($file, [$row['Date'], $row['Product'], $row['Grammage'], $row['Received'], $row['Disbursed'], $row['Delivered'], $net]);
                        }
                        fclose($file);
                    }, 'stock_report_'.date('Y_m_d_H_i_s').'.csv', $headers);
                }),
        ];
    }
}

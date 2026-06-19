<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use App\Models\StockTransfer;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class AccountantStockReceiveRequestsWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Stock Receive Requests';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['accountant', 'general_accountant']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => StockTransfer::where('status', 'requested')
                ->where('requires_approval', true)
                ->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('toWarehouse.name')
                    ->label('To Warehouse')
                    ->searchable(),
                TextColumn::make('source_type')
                    ->label('Source')
                    ->formatStateUsing(fn (string $state): string => $state === 'warehouse' ? 'Internal' : 'External'),
                TextColumn::make('source_name')
                    ->label('Source Name')
                    ->placeholder('-'),
                TextColumn::make('items')
                    ->label('Items')
                    ->formatStateUsing(fn ($record): string => $record->items->count().' item(s)'),
                TextColumn::make('requester.name')
                    ->label('Requested By'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('viewDispatchPapers')
                    ->label('View Papers')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn (StockTransfer $record) => $record->dispatch_papers_path)
                    ->action(function (StockTransfer $record) {
                        $paths = json_decode($record->dispatch_papers_path, true);
                        if (! is_array($paths)) {
                            $paths = [$record->dispatch_papers_path];
                        }

                        $html = '<div class="space-y-4">';
                        foreach ($paths as $path) {
                            $url = asset('storage/'.$path);
                            if (str_ends_with(strtolower($path), '.pdf')) {
                                $html .= '<iframe src="'.$url.'" class="w-full h-96 rounded-lg border"></iframe>';
                            } else {
                                $html .= '<img src="'.$url.'" class="max-w-full rounded-lg border" />';
                            }
                        }
                        $html .= '</div>';

                        return $html;
                    })
                    ->modalHeading('Dispatch Papers')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('approveReceive')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Stock Receive')
                    ->modalDescription('This will add the stock to the destination warehouse inventory.')
                    ->action(function (StockTransfer $record) {
                        DB::transaction(function () use ($record) {
                            foreach ($record->items as $item) {
                                $inv = Inventory::firstOrCreate(
                                    [
                                        'warehouse_id' => $record->to_warehouse_id,
                                        'product_type_id' => $item->product_type_id,
                                        'grammage' => $item->grammage,
                                    ],
                                    ['quantity' => 0]
                                );
                                $inv->increment('quantity', $item->quantity);
                            }

                            $record->update([
                                'status' => 'received',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                                'received_by' => auth()->id(),
                                'received_at' => now(),
                            ]);
                        });

                        Notification::make()->title('Stock receive approved and inventory updated')->success()->send();
                    }),

                Action::make('rejectReceive')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Reject Stock Receive')
                    ->action(function (StockTransfer $record, array $data) {
                        $record->update([
                            'status' => 'cancelled',
                            'rejection_reason' => $data['rejection_reason'],
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);

                        Notification::make()->title('Stock receive request rejected')->danger()->send();
                    }),
            ])
            ->paginated(10);
    }
}

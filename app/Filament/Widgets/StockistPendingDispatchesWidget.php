<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use App\Models\StockTransfer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class StockistPendingDispatchesWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Stock Dispatches';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'stockist';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => StockTransfer::where('to_stockist_id', auth()->user()->stockist?->id)
                ->where('status', 'dispatched')
                ->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')
                    ->label('Dispatch #'),
                TextColumn::make('fromWarehouse.name')
                    ->label('From Warehouse'),
                TextColumn::make('items')
                    ->label('Items')
                    ->formatStateUsing(fn (StockTransfer $record): string => $record->items->map(
                        fn ($item) => ($item->productType?->name ?? '').' x'.$item->quantity
                    )->implode(', '))
                    ->limit(60),
                TextColumn::make('dispatcher.name')
                    ->label('Dispatched By'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('accept')
                    ->label('Accept & Receive Stock')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Accept Stock')
                    ->modalDescription('Confirm that you have received and accepted this stock. Your stock balance will be updated.')
                    ->action(function (StockTransfer $record) {
                        $stockist = auth()->user()->stockist;
                        if (! $stockist) {
                            Notification::make()->title('No linked stockist found')->danger()->send();

                            return;
                        }

                        foreach ($record->items as $item) {
                            $accepted = $item->quantity - ($item->rejected_quantity ?? 0);

                            if ($accepted > 0) {
                                $stock = StockistStock::firstOrCreate(
                                    [
                                        'stockist_id' => $stockist->id,
                                        'product_name' => $item->productType?->name ?? 'Unknown',
                                        'grammage' => $item->grammage,
                                    ],
                                    ['quantity' => 0]
                                );
                                $stock->increment('quantity', $accepted);

                                if ($record->requested_by) {
                                    $agentStock = AgentStock::firstOrCreate(
                                        [
                                            'user_id' => $record->requested_by,
                                            'product_type_id' => $item->product_type_id,
                                            'product_name' => $item->productType?->name ?? 'Unknown',
                                            'grammage' => $item->grammage,
                                        ],
                                        ['quantity' => 0]
                                    );
                                    $agentStock->increment('quantity', $accepted);
                                }
                            }
                        }

                        $record->update([
                            'status' => 'received',
                            'received_by' => auth()->id(),
                            'received_at' => now(),
                            'stockist_accepted_at' => now(),
                        ]);

                        StockistTransaction::create([
                            'stockist_id' => $stockist->id,
                            'user_id' => auth()->id(),
                            'type' => 'received',
                            'amount' => 0,
                            'description' => "Accepted dispatch #{$record->id} from {$record->fromWarehouse?->name}",
                            'transaction_date' => now()->toDateString(),
                        ]);

                        $this->dispatch('refresh-dashboard');

                        Notification::make()->title('Stock accepted successfully')->success()->send();
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading('No pending dispatches')
            ->emptyStateDescription('All stock has been received.');
    }
}

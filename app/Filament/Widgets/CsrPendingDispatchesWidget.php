<?php

namespace App\Filament\Widgets;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

class CsrPendingDispatchesWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Pending Dispatches';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['community_sales_representative', 'open_market', 'retail_market']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(StockTransfer::where('to_agent_id', auth()->id())
                ->whereIn('status', ['requested', 'approved', 'dispatched', 'received']))
            ->columns([
                TextColumn::make('id')
                    ->label('Transfer #')
                    ->sortable(),
                TextColumn::make('fromWarehouse.name')
                    ->label('From')
                    ->state(fn ($record): ?string => $record->fromWarehouse?->name ?? $record->fromAgent?->name),
                TextColumn::make('items')
                    ->label('Items')
                    ->formatStateUsing(fn ($record): string => $record->items->map(
                        fn ($item) => ($item->productType?->name ?? '').' x'.$item->quantity
                    )->implode(', '))
                    ->limit(50),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date(),
            ])
            ->recordActions([
                Action::make('acceptReceive')
                    ->label('Accept & Mark Received')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (StockTransfer $record): bool => $record->status === StockTransferStatus::Dispatched)
                    ->form(function (StockTransfer $record): array {
                        return [
                            Repeater::make('items')
                                ->label('Received Items')
                                ->schema([
                                    TextInput::make('product_label')
                                        ->label('Product')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->default(fn ($state) => ''),
                                    TextInput::make('accepted_quantity')
                                        ->label('Accepted Qty')
                                        ->numeric()
                                        ->integer()
                                        ->minValue(0)
                                        ->required(),
                                    TextInput::make('rejected_quantity')
                                        ->label('Rejected Qty')
                                        ->numeric()
                                        ->integer()
                                        ->minValue(0)
                                        ->default(0)
                                        ->live(),
                                    TextInput::make('rejection_reason')
                                        ->label('Rejection Reason')
                                        ->visible(fn (callable $get) => ($get('rejected_quantity') ?? 0) > 0),
                                ])
                                ->defaultItems(0)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->default(
                                    $record->items->map(fn ($item) => [
                                        'item_id' => $item->id,
                                        'product_label' => ($item->productType?->name ?? 'Unknown')." {$item->grammage}g",
                                        'accepted_quantity' => $item->quantity,
                                        'rejected_quantity' => 0,
                                        'rejection_reason' => null,
                                    ])->toArray()
                                ),
                        ];
                    })
                    ->action(function (StockTransfer $record, array $data): void {
                        StockTransferService::receive($record, $data['items']);

                        $hasRejections = $record->items()->where('rejected_quantity', '>', 0)->exists();

                        Notification::make()
                            ->title($hasRejections
                                ? 'Stock received with some rejected items'
                                : 'Stock received successfully')
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
                Action::make('viewReceived')
                    ->label('View Received Breakdown')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn (StockTransfer $record): bool => $record->status === StockTransferStatus::Received)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (StockTransfer $record): View => view('filament.stock-received-breakdown', ['transfer' => $record])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

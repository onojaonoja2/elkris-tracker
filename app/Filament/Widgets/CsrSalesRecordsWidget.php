<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use App\Services\SalesRecordService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class CsrSalesRecordsWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'My Sales Records';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(SalesRecord::where('agent_id', auth()->id()))
            ->columns([
                TextColumn::make('id')
                    ->label('Record #'),
                TextColumn::make('total_value')
                    ->label('Value')
                    ->money('NGN'),
                TextColumn::make('products')
                    ->label('Products')
                    ->formatStateUsing(fn ($products) => collect($products)->map(fn ($p) => "{$p['quantity']}x {$p['product_name']}")->implode(', '))
                    ->limit(40),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('viewReceipt')
                    ->label('View Receipt')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->size('sm')
                    ->visible(fn (SalesRecord $record): bool => (bool) $record->receipt_path)
                    ->modalContent(fn (SalesRecord $record) => view('filament.sales-record-receipt', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalFooterActions(fn (SalesRecord $record): array => [
                        ...($this->canReplaceReceipt($record) ? [$this->makeReplaceReceiptAction('replaceReceiptFromPreview')->cancelParentActions()] : []),
                        Action::make('closePreview')
                            ->label('Close')
                            ->color('gray')
                            ->close(),
                    ]),

                $this->makeReplaceReceiptAction(),
            ]);
    }

    private function canReplaceReceipt(SalesRecord $record): bool
    {
        return (bool) $record->receipt_path
            && ! $record->isLocked()
            && $record->agent_id === auth()->id();
    }

    private function makeReplaceReceiptAction(string $name = 'replaceReceipt'): Action
    {
        return Action::make($name)
            ->label('Replace Receipt')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->size('sm')
            ->visible(fn (SalesRecord $record): bool => $this->canReplaceReceipt($record))
            ->form([
                FileUpload::make('receipt_path')
                    ->label('Payment Receipt / Slip')
                    ->image()
                    ->maxSize(2048)
                    ->disk('s3')
                    ->directory('receipts/sales-records')
                    ->visibility('private')
                    ->imageEditor()
                    ->storeFileNamesIn('receipt_original_name')
                    ->required(),
            ])
            ->action(function (SalesRecord $record, array $data) {
                try {
                    SalesRecordService::replaceReceipt($record, $data, auth()->id());
                } catch (ValidationException $e) {
                    Notification::make()
                        ->danger()
                        ->title('Replacement failed')
                        ->body($e->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Receipt replaced')
                    ->success()
                    ->send();

                $this->dispatch('refresh-dashboard');
            })
            ->modalHeading('Replace Receipt')
            ->modalDescription('Upload a corrected receipt. The previous file will be removed.');
    }
}

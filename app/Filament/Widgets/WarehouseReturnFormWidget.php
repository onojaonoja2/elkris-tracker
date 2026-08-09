<?php

namespace App\Filament\Widgets;

use App\Models\ProductType;
use App\Models\Warehouse;
use App\Models\WarehouseReturn;
use App\Services\WarehouseReturnService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseReturnFormWidget extends TableWidget
{
    protected static ?string $heading = 'Return Stock to Warehouse';

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
            ->query(fn () => WarehouseReturn::where('user_id', auth()->id())->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('warehouse.name')->label('Warehouse'),
                TextColumn::make('productType.name')->label('Product'),
                TextColumn::make('grammage')->label('Weight')->formatStateUsing(fn ($state) => $state.'g'),
                TextColumn::make('quantity')->label('Qty'),
                TextColumn::make('reason')->label('Reason')->limit(40)->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Date')->dateTime(),
            ])
            ->headerActions([
                Action::make('returnStock')
                    ->label('Return Stock to Warehouse')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->form([
                        Select::make('warehouse_id')
                            ->label('Return To Warehouse')
                            ->options(fn () => Warehouse::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('product_type_id')
                            ->label('Product')
                            ->options(fn () => ProductType::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('grammage', null)),
                        Select::make('grammage')
                            ->label('Weight (g)')
                            ->options(function (callable $get) {
                                $ptId = $get('product_type_id');
                                if (! $ptId) {
                                    return [];
                                }
                                $pt = ProductType::find($ptId);

                                return $pt
                                    ? collect($pt->available_grammages)
                                        ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                        ->mapWithKeys(fn ($g) => [(string) $g => $g.'g'])
                                        ->toArray()
                                    : [];
                            })
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        Textarea::make('reason')
                            ->label('Reason for Return')
                            ->rows(3),
                    ])
                    ->action(function (array $data) {
                        try {
                            WarehouseReturnService::submit($data, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Return request failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()->title('Return request submitted')->success()->send();
                        $this->dispatch('refresh-dashboard');
                    })
                    ->modalHeading('Return Stock to Warehouse')
                    ->modalDescription('Request to return unsold stock from your inventory to the selected warehouse.'),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export My Returns')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        return new StreamedResponse(function () {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['ID', 'Warehouse', 'Product', 'Weight', 'Qty', 'Reason', 'Status', 'Requested At']);

                            WarehouseReturn::where('user_id', auth()->id())
                                ->with(['warehouse', 'productType'])
                                ->orderBy('created_at', 'desc')
                                ->chunk(100, function ($returns) use ($file) {
                                    foreach ($returns as $return) {
                                        fputcsv($file, [
                                            $return->id,
                                            $return->warehouse?->name ?? 'N/A',
                                            $return->productType?->name ?? 'N/A',
                                            $return->grammage.'g',
                                            $return->quantity,
                                            $return->reason ?? '-',
                                            $return->status,
                                            $return->created_at->format('d/m/Y H:i'),
                                        ]);
                                    }
                                });

                            fclose($file);
                        }, 200, [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment; filename="my_warehouse_returns_'.now()->format('Y_m_d_H_i_s').'.csv"',
                        ]);
                    }),
            ])
            ->paginated(10);
    }
}

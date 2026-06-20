<?php

namespace App\Filament\Resources\StockTransfers\Tables;

use App\Enums\StockTransferStatus;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Transfer #')
                    ->sortable(),

                TextColumn::make('fromWarehouse.name')
                    ->label('From')
                    ->state(fn ($record): ?string => $record->fromWarehouse?->name ?? $record->fromAgent?->name),

                TextColumn::make('toWarehouse.name')
                    ->label('To Warehouse'),

                TextColumn::make('toAgent.name')
                    ->label('To Agent'),

                TextColumn::make('collector.name')
                    ->label('Collected By')
                    ->placeholder('-'),

                TextColumn::make('items')
                    ->label('Items')
                    ->formatStateUsing(fn ($record): string => $record->items->map(
                        fn ($item) => ($item->productType?->name ?? '')." {$item->grammage}g x".$item->quantity
                    )->implode(', '))
                    ->limit(60),

                TextColumn::make('rejected')
                    ->label('Rejections')
                    ->state(fn ($record): string => $record->items->where('rejected_quantity', '>', 0)
                        ->map(fn ($item) => ($item->productType?->name ?? '').' x'.$item->rejected_quantity
                            .($item->rejection_resolved_at ? ' (resolved)' : ''))
                        ->implode(', '))
                    ->placeholder('None')
                    ->limit(40),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (StockTransferStatus $state): string => $state->color()),

                TextColumn::make('requester.name')
                    ->label('Requested By')
                    ->placeholder('-'),

                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->placeholder('-'),

                TextColumn::make('dispatcher.name')
                    ->label('Dispatched By'),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->date(),
            ])
            ->filters([
                Filter::make('date_range')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from')
                            ->label('From Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('until')
                            ->label('Until Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'From '.Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until '.Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                SelectFilter::make('status')
                    ->options(StockTransferStatus::class),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([

                // === REQUEST FLOW ACTIONS ===

                Action::make('requestFromWarehouse')
                    ->label('Request from Warehouse')
                    ->icon('heroicon-o-building-storefront')
                    ->color('info')
                    ->visible(fn () => in_array(auth()->user()->role, ['supervisor', 'admin', 'community_sales_representative']))
                    ->form([
                        Select::make('from_warehouse_id')
                            ->label('From Warehouse')
                            ->options(fn () => Warehouse::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live(),
                        Select::make('to_agent_id')
                            ->label('To Community Sales Rep')
                            ->options(fn () => User::where('role', 'community_sales_representative')->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Repeater::make('items')
                            ->label('Stock Items')
                            ->schema([
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
                                        if (! $pt) {
                                            return [];
                                        }

                                        return collect($pt->available_grammages)
                                            ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                            ->mapWithKeys(fn ($g) => [(string) $g => $g.'g'])
                                            ->toArray();
                                    })
                                    ->required()
                                    ->live(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->required(),
                            ])
                            ->addActionLabel('Add Item')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Request Notes'),
                    ])
                    ->action(function (array $data) {
                        $transfer = StockTransfer::create([
                            'from_warehouse_id' => $data['from_warehouse_id'],
                            'to_agent_id' => $data['to_agent_id'],
                            'requested_by' => auth()->id(),
                            'status' => StockTransferStatus::Requested,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        foreach ($data['items'] as $item) {
                            $transfer->items()->create($item);
                        }

                        Notification::make()->title('Stock request created')->success()->send();
                    }),

                Action::make('requestFromCsrPeer')
                    ->label('Request from CSR Peer')
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->visible(fn () => auth()->user()->role === 'community_sales_representative')
                    ->form([
                        Select::make('from_agent_id')
                            ->label('From CSR (Same LGA/State)')
                            ->options(function () {
                                $user = auth()->user();

                                return User::where('role', 'community_sales_representative')
                                    ->where('id', '!=', $user->id)
                                    ->where('is_active', true)
                                    ->where(function ($q) use ($user) {
                                        if ($user->lga_id) {
                                            $q->where('lga_id', $user->lga_id);
                                        }
                                        if ($user->assigned_cities) {
                                            $q->orWhereIn('assigned_cities', $user->assigned_cities ?? []);
                                        }
                                    })
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required(),
                        Repeater::make('items')
                            ->label('Stock Items')
                            ->schema([
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
                                        if (! $pt) {
                                            return [];
                                        }

                                        return collect($pt->available_grammages)
                                            ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                            ->mapWithKeys(fn ($g) => [(string) $g => $g.'g'])
                                            ->toArray();
                                    })
                                    ->required()
                                    ->live(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->required(),
                            ])
                            ->addActionLabel('Add Item')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Request Notes'),
                    ])
                    ->action(function (array $data) {
                        $transfer = StockTransfer::create([
                            'from_agent_id' => $data['from_agent_id'],
                            'to_agent_id' => auth()->id(),
                            'requested_by' => auth()->id(),
                            'status' => StockTransferStatus::Requested,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        foreach ($data['items'] as $item) {
                            $transfer->items()->create($item);
                        }

                        Notification::make()->title('Stock request sent to CSR peer')->success()->send();
                    }),

                // === STOCK COLLECTION ACTIONS ===

                Action::make('collectFromAgent')
                    ->label('Collect from Agent')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('warning')
                    ->visible(fn () => in_array(auth()->user()->role, ['supervisor', 'manager', 'admin']))
                    ->form([
                        Select::make('from_agent_id')
                            ->label('Agent')
                            ->options(function () {
                                $user = auth()->user();

                                $query = User::where('is_active', true)
                                    ->whereIn('role', ['community_sales_representative', 'open_market', 'retail_market']);

                                if ($user->role === 'supervisor') {
                                    $query->where(function ($q) use ($user) {
                                        $q->where('lead_id', $user->id)
                                            ->orWhere('portfolio_agent_id', $user->id);
                                    });
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->live(),
                        Repeater::make('items')
                            ->label('Stock Items to Collect')
                            ->schema([
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
                                        if (! $pt) {
                                            return [];
                                        }

                                        return collect($pt->available_grammages)
                                            ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                            ->mapWithKeys(fn ($g) => [(string) $g => $g.'g'])
                                            ->toArray();
                                    })
                                    ->required()
                                    ->live(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->required(),
                            ])
                            ->addActionLabel('Add Item')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Collection Notes'),
                    ])
                    ->action(function (array $data) {
                        $transfer = StockTransfer::create([
                            'from_agent_id' => $data['from_agent_id'],
                            'requested_by' => auth()->id(),
                            'status' => StockTransferStatus::Collected,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        StockTransferService::collect($transfer, $data['items']);

                        Notification::make()->title('Stock collected from agent')->success()->send();
                    }),

                Action::make('reassign')
                    ->label('Re-assign')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->visible(fn (StockTransfer $record) => $record->status === StockTransferStatus::Collected
                        && in_array(auth()->user()->role, ['supervisor', 'manager', 'admin']))
                    ->form([
                        Select::make('to_warehouse_id')
                            ->label('To Warehouse')
                            ->options(fn () => Warehouse::pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(fn ($set, $state) => $state ? $set('to_agent_id', null) : null),

                        Select::make('to_agent_id')
                            ->label('To Agent')
                            ->options(function () {
                                $user = auth()->user();

                                $query = User::where('is_active', true)
                                    ->whereIn('role', ['community_sales_representative', 'open_market', 'retail_market']);

                                if ($user->role === 'supervisor') {
                                    $query->where(function ($q) use ($user) {
                                        $q->where('lead_id', $user->id)
                                            ->orWhere('portfolio_agent_id', $user->id);
                                    });
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(fn ($set, $state) => $state ? $set('to_warehouse_id', null) : null),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Re-assign Collected Stock')
                    ->modalDescription('This will move the collected stock to the selected warehouse or agent. The source agent\'s stock balance will be deducted.')
                    ->action(function (StockTransfer $record, array $data) {
                        if (empty($data['to_warehouse_id']) && empty($data['to_agent_id'])) {
                            Notification::make()
                                ->title('Please select a destination')
                                ->danger()
                                ->send();

                            return;
                        }

                        StockTransferService::reassign($record, $data);

                        Notification::make()->title('Stock re-assigned successfully')->success()->send();
                    }),

                // === ACCOUNTANT APPROVAL (sole approver) ===

                Action::make('accountantApprove')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (StockTransfer $record) => $record->status === StockTransferStatus::Requested && auth()->user()->role === 'accountant')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Stock Request')
                    ->modalDescription('Confirm approval of this stock request. Warehouse stock will be verified.')
                    ->action(function (StockTransfer $record) {
                        StockTransferService::approve($record, validateInventory: true);
                        Notification::make()->title('Stock request approved')->success()->send();
                    }),

                Action::make('accountantReject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (StockTransfer $record) => $record->status === StockTransferStatus::Requested && auth()->user()->role === 'accountant')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (StockTransfer $record, array $data) {
                        StockTransferService::reject($record, $data['rejection_reason']);
                        Notification::make()->title('Stock request rejected')->danger()->send();
                    }),

                // === EXISTING ACTIONS ===

                EditAction::make()
                    ->visible(fn (StockTransfer $record) => $record->status === StockTransferStatus::Draft),

                Action::make('printDispatch')
                    ->label('Print Dispatch Note')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->visible(fn (StockTransfer $record) => in_array($record->status, [StockTransferStatus::Approved, StockTransferStatus::Dispatched, StockTransferStatus::Received]))
                    ->action(function (StockTransfer $record) {
                        $pdf = Pdf::loadView('pdf.dispatch-note', ['transfer' => $record]);

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            "dispatch-note-{$record->id}.pdf"
                        );
                    }),

                Action::make('dispatch')
                    ->label('Dispatch')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->visible(fn (StockTransfer $record) => $record->status === StockTransferStatus::Approved)
                    ->requiresConfirmation()
                    ->action(function (StockTransfer $record) {
                        $record->update([
                            'status' => StockTransferStatus::Dispatched,
                            'dispatched_by' => auth()->id(),
                        ]);
                        Notification::make()->title('Stock dispatched successfully')->success()->send();
                    }),

                Action::make('receive')
                    ->label('Mark Received')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (StockTransfer $record) => $record->status === StockTransferStatus::Dispatched
                        && $record->dispatched_by !== auth()->id()
                        && (
                            ($record->to_warehouse_id && in_array($record->to_warehouse_id, auth()->user()->managedWarehouses()->pluck('id')->toArray()))
                            || $record->to_agent_id === auth()->id()
                            || $record->requested_by === auth()->id()
                        ))
                    ->form(fn (StockTransfer $record) => [
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
                    ])
                    ->action(function (StockTransfer $record, array $data) {
                        StockTransferService::receive($record, $data['items']);

                        $hasRejections = $record->items()->where('rejected_quantity', '>', 0)->exists();

                        Notification::make()
                            ->title($hasRejections
                                ? 'Stock received with some rejected items'
                                : 'Stock received successfully')
                            ->success()
                            ->send();
                    }),

                Action::make('resolveRejections')
                    ->label('Resolve Rejections')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->visible(fn (StockTransfer $record) => $record->status === StockTransferStatus::Received && $record->unresolvedRejectedItems()->exists() && auth()->user()->role === 'admin')
                    ->form([
                        Textarea::make('resolution_notes')
                            ->label('Resolution Notes')
                            ->required(),
                    ])
                    ->action(function (StockTransfer $record, array $data) {
                        $record->unresolvedRejectedItems()->update([
                            'rejection_resolved_at' => now(),
                        ]);

                        $record->update([
                            'notes' => ($record->notes ? $record->notes."\n" : '').'Rejections resolved: '.$data['resolution_notes'],
                        ]);

                        Notification::make()
                            ->title('Rejections resolved')
                            ->success()
                            ->send();
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->visible(fn (StockTransfer $record) => in_array($record->status, [StockTransferStatus::Draft, StockTransferStatus::Requested, StockTransferStatus::Approved, StockTransferStatus::Dispatched]))
                    ->requiresConfirmation()
                    ->form(fn (StockTransfer $record) => $record->status === StockTransferStatus::Requested
                        ? [Textarea::make('rejection_reason')->label('Reason for Cancellation')->required()]
                        : [])
                    ->action(function (StockTransfer $record, array $data) {
                        $record->update([
                            'status' => StockTransferStatus::Cancelled,
                            'rejection_reason' => $data['rejection_reason'] ?? $record->rejection_reason,
                        ]);
                        Notification::make()->title('Stock transfer cancelled')->danger()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

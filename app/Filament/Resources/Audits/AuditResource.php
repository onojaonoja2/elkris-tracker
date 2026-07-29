<?php

namespace App\Filament\Resources\Audits;

use App\Filament\Navigation\HasRoleBasedNavigationGroup;
use App\Filament\Resources\Audits\Pages\ManageAudits;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;

class AuditResource extends Resource
{
    use HasRoleBasedNavigationGroup;

    protected static ?string $model = Audit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Audit Trail';

    protected static ?string $navigationRole = 'admin';

    protected static ?int $navigationSort = 99;

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        $auditableTypes = Audit::distinct()->pluck('auditable_type')
            ->mapWithKeys(fn ($type) => [$type => class_basename($type)])
            ->toArray();

        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'restored' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('auditable_type')
                    ->label('Model')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'N/A'),

                TextColumn::make('auditable_id')
                    ->label('Record ID')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->default('System')
                    ->sortable(),

                TextColumn::make('summary')
                    ->label('Changes')
                    ->getStateUsing(function (Audit $record): string {
                        $changed = array_keys(array_diff_assoc(
                            $record->new_values ?? [],
                            $record->old_values ?? []
                        ));

                        return ! empty($changed)
                            ? implode(', ', array_map(fn ($f) => Str::title(str_replace('_', ' ', $f)), $changed))
                            : match ($record->event) {
                                'created' => 'Record created',
                                'deleted' => 'Record deleted',
                                default => 'No changes',
                            };
                    })
                    ->limit(60)
                    ->sortable(false),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'restored' => 'Restored',
                    ]),
                SelectFilter::make('auditable_type')
                    ->label('Model')
                    ->options($auditableTypes),
                Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                        DatePicker::make('created_until')
                            ->label('To')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['created_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['created_until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date)),
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (Audit $record) => 'Audit #'.$record->id)
                    ->modalWidth('7xl')
                    ->form(null)
                    ->infolist(fn (Audit $record): array => self::getAuditInfolist($record)),
            ])
            ->paginated([10, 25, 50]);
    }

    protected static function getAuditInfolist(Audit $record): array
    {
        $sections = [];

        $sections[] = Fieldset::make('Event Details')
            ->columns(4)
            ->schema([
                TextEntry::make('id')->label('Audit ID'),
                TextEntry::make('created_at')->label('Date')->dateTime(),
                TextEntry::make('event')->label('Event')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextEntry::make('user.name')->label('User')->default('System'),
                TextEntry::make('auditable_type')
                    ->label('Model')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'N/A'),
                TextEntry::make('auditable_id')->label('Record ID'),
                TextEntry::make('ip_address')->label('IP Address'),
                TextEntry::make('url')->label('URL')->limit(60),
            ]);

        $oldValues = $record->old_values ?? [];
        $newValues = $record->new_values ?? [];
        $allFields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        if (! empty($allFields)) {
            $diffFields = [];
            foreach ($allFields as $field) {
                $old = $oldValues[$field] ?? null;
                $new = $newValues[$field] ?? null;
                $changed = $old !== $new;

                $diffFields[] = TextEntry::make("field_{$field}")
                    ->label(Str::title(str_replace('_', ' ', $field)))
                    ->formatStateUsing(function () use ($old, $new, $changed): string {
                        $oldDisplay = is_array($old) ? json_encode($old) : ($old ?? '(empty)');
                        $newDisplay = is_array($new) ? json_encode($new) : ($new ?? '(empty)');

                        if ($changed) {
                            return "**{$newDisplay}** (was: {$oldDisplay})";
                        }

                        return $newDisplay;
                    })
                    ->html()
                    ->color(fn (): string => $changed ? 'warning' : 'gray');
            }

            $sections[] = Fieldset::make('Changed Values')
                ->columns(2)
                ->schema($diffFields);
        }

        if (! empty($record->user_agent)) {
            $sections[] = Fieldset::make('Request Details')
                ->columns(1)
                ->schema([
                    TextEntry::make('user_agent')->label('User Agent')->limit(200),
                    TextEntry::make('tags')->label('Tags'),
                ]);
        }

        return $sections;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAudits::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Traits;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait HasViewModal
{
    public static function getViewActionForResource(string $resourceClass): ViewAction
    {
        $model = app($resourceClass::getModel());

        return ViewAction::make()
            ->modalHeading(fn (Model $record) => 'View '.class_basename($record))
            ->modalWidth('7xl')
            ->form(null)
            ->infolist(fn (Model $record) => static::getViewInfolist($record, $resourceClass));
    }

    protected static function getViewInfolist(Model $record, string $resourceClass): array
    {
        $fillable = $record->getFillable();
        $sections = [];
        $mainFields = [];
        $casts = $record->getCasts();

        $relations = static::getViewRelations();

        foreach ($fillable as $field) {
            if (in_array($field, $relations)) {
                continue;
            }

            if (str_ends_with($field, '_id')) {
                $relationName = Str::before($field, '_id');
                if (method_exists($record, $relationName)) {
                    $mainFields[] = TextEntry::make($relationName.'.name')
                        ->label(Str::title(str_replace('_', ' ', $relationName)))
                        ->default(fn () => $record->{$relationName}?->name ?? 'N/A');

                    continue;
                }
            }

            $label = Str::title(str_replace('_', ' ', $field));

            if ($field === 'password') {
                continue;
            }

            $entry = TextEntry::make($field)->label($label);

            $castType = $casts[$field] ?? null;
            if ($castType === 'array' || str_starts_with((string) $castType, 'array')) {
                $entry->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : $state);
            } elseif ($castType === 'json') {
                $entry->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : $state);
            } elseif ($castType === 'boolean' || $castType === 'bool') {
                $entry->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No');
            }

            $mainFields[] = $entry;
        }

        if (! empty($mainFields)) {
            $sections[] = Fieldset::make('Details')
                ->columns(3)
                ->schema($mainFields);
        }

        foreach ($relations as $relation => $config) {
            $label = is_array($config) ? ($config['label'] ?? Str::title(str_replace('_', ' ', $relation))) : Str::title(str_replace('_', ' ', $relation));
            $columns = is_array($config) ? ($config['columns'] ?? ['name']) : [$config];

            if (method_exists($record, $relation)) {
                $relatedModel = $record->{$relation};
                if ($relatedModel instanceof Model) {
                    $fields = [];
                    foreach ($columns as $col) {
                        $fieldLabel = Str::title(str_replace('_', ' ', $col));
                        $fields[] = TextEntry::make($relation.'.'.$col)->label($fieldLabel);
                    }
                    $sections[] = Fieldset::make($label)
                        ->columns(3)
                        ->schema($fields);
                } elseif ($relatedModel !== null) {
                    $items = $relatedModel instanceof Collection ? $relatedModel : collect($relatedModel);
                    if ($items->isNotEmpty()) {
                        $itemFields = [];
                        $firstItem = $items->first();
                        $itemKeys = is_array($config) ? ($config['columns'] ?? array_keys(is_object($firstItem) ? $firstItem->toArray() : $firstItem)) : array_keys(is_object($firstItem) ? $firstItem->toArray() : $firstItem);
                        foreach ($itemKeys as $key) {
                            $fieldLabel = Str::title(str_replace('_', ' ', $key));
                            $itemFields[] = TextEntry::make($key)->label($fieldLabel)->default('N/A');
                        }
                        $sections[] = Section::make($label)
                            ->schema([
                                RepeatableEntry::make($relation)
                                    ->schema($itemFields)
                                    ->columns(count($itemFields)),
                            ]);
                    }
                }
            }
        }

        $timestamps = ['created_at', 'updated_at'];
        $timestampFields = [];
        foreach ($timestamps as $ts) {
            if (isset($record->{$ts})) {
                $timestampFields[] = TextEntry::make($ts)
                    ->label(Str::title(str_replace('_', ' ', $ts)))
                    ->dateTime();
            }
        }
        if (! empty($timestampFields)) {
            $sections[] = Fieldset::make('Timestamps')
                ->columns(2)
                ->schema($timestampFields);
        }

        return $sections;
    }

    protected static function getViewRelations(): array
    {
        return [];
    }
}

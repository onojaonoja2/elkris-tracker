<?php

namespace App\Filament\Resources\CallLogs;

use App\Filament\Resources\CallLogs\Pages\CreateCallLog;
use App\Filament\Resources\CallLogs\Pages\ListCallLogs;
use App\Models\CallLog;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CallLogResource extends Resource
{
    protected static ?string $model = CallLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Phone;

    protected static ?string $navigationLabel = 'Call Logs';

    protected static ?string $modelLabel = 'Call Log';

    protected static ?string $slug = 'call-logs';

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'manager', 'lead', 'rep']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! in_array(auth()->user()->role, ['manager', 'admin']);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        if ($user->role === 'rep') {
            return $query->where('user_id', $user->id);
        }

        if ($user->role === 'lead') {
            return $query->whereIn('user_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('users')
                    ->where('lead_id', $user->id)
                    ->orWhere('id', $user->id);
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('customer_id')
                ->label('Customer')
                ->relationship('customer', 'customer_name')
                ->searchable()
                ->required(),
            DateTimePicker::make('called_at')
                ->native(false)
                ->displayFormat('d/m/Y H:i')
                ->default(now()),
            Select::make('outcome')
                ->options([
                    'connected' => 'Connected',
                    'voicemail' => 'Left Voicemail',
                    'not_reachable' => 'Not Reachable',
                    'wrong_number' => 'Wrong Number',
                    'callback' => 'Will Call Back',
                    'no_answer' => 'No Answer',
                ])
                ->required(),
            Textarea::make('notes')
                ->rows(3),
            Textarea::make('other_comment')
                ->label('Other Comment')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Rep')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('called_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('outcome')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'connected' => 'success',
                        'voicemail' => 'warning',
                        'not_reachable' => 'danger',
                        'wrong_number' => 'gray',
                        'callback' => 'info',
                        'no_answer' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'connected' => 'Connected',
                        'voicemail' => 'Voicemail',
                        'not_reachable' => 'Not Reachable',
                        'wrong_number' => 'Wrong Number',
                        'callback' => 'Callback',
                        'no_answer' => 'No Answer',
                        default => $state,
                    }),
                TextColumn::make('notes')
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('other_comment')
                    ->label('Other Comment')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('called_at')
                    ->label('Date Range')
                    ->form([
                        DateTimePicker::make('called_from')
                            ->label('From Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DateTimePicker::make('called_until')
                            ->label('To Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['called_from'], fn ($q, $date) => $q->whereDate('called_at', '>=', $date))
                            ->when($data['called_until'], fn ($q, $date) => $q->whereDate('called_at', '<=', $date));
                    }),
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                Action::make('export')
                    ->label('Export Call Logs')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $query = static::getEloquentQuery()->with(['user', 'customer']);
                        $logs = $query->orderBy('called_at', 'desc')->get();
                        $data = [];
                        foreach ($logs as $log) {
                            $data[] = [
                                $log->user?->name ?? 'N/A',
                                $log->customer?->customer_name ?? 'N/A',
                                Carbon::parse($log->called_at)->format('d/m/Y H:i'),
                                ucfirst(str_replace('_', ' ', $log->outcome)),
                                $log->notes ?? '',
                                $log->other_comment ?? '',
                            ];
                        }

                        return response()->streamDownload(function () use ($data) {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['Rep', 'Customer', 'Called At', 'Outcome', 'Notes', 'Other Comment']);
                            foreach ($data as $row) {
                                fputcsv($file, $row);
                            }
                            fclose($file);
                        }, 'call_logs_export_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment',
                        ]);
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCallLogs::route('/'),
            'create' => CreateCallLog::route('/create'),
        ];
    }
}

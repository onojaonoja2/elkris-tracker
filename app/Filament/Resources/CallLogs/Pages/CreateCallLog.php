<?php

namespace App\Filament\Resources\CallLogs\Pages;

use App\Filament\Resources\CallLogs\CallLogResource;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCallLog extends CreateRecord
{
    protected static string $resource = CallLogResource::class;

    protected function getFormSchema(): array
    {
        return [
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
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }
}

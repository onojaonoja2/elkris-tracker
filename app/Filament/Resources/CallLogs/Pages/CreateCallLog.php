<?php

namespace App\Filament\Resources\CallLogs\Pages;

use App\Filament\Resources\CallLogs\CallLogResource;
use App\Models\Customer;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCallLog extends CreateRecord
{
    protected static string $resource = CallLogResource::class;

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('customer_id')
                ->label('Customer')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => Customer::query()
                    ->where(function ($q) use ($search) {
                        $q->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    })
                    ->where(function ($q) {
                        $user = auth()->user();
                        if ($user->role === 'rep') {
                            $q->where('rep_id', $user->id);
                        } elseif ($user->role === 'lead') {
                            $q->whereHas('reps', fn ($qr) => $qr->where('lead_id', $user->id));
                        }
                    })
                    ->limit(10)
                    ->pluck('customer_name', 'id'))
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

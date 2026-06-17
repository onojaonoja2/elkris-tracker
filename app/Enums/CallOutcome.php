<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CallOutcome: string implements HasLabel
{
    case Connected = 'connected';
    case Voicemail = 'voicemail';
    case NotReachable = 'not_reachable';
    case WrongNumber = 'wrong_number';
    case Callback = 'callback';
    case NoAnswer = 'no_answer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::Voicemail => 'Voicemail',
            self::NotReachable => 'Not Reachable',
            self::WrongNumber => 'Wrong Number',
            self::Callback => 'Callback',
            self::NoAnswer => 'No Answer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Connected => 'success',
            self::Voicemail => 'info',
            self::NotReachable => 'warning',
            self::WrongNumber => 'danger',
            self::Callback => 'primary',
            self::NoAnswer => 'gray',
        };
    }
}

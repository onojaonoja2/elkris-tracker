<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CustomerPriority: string implements HasLabel
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function getLabel(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::High => 'danger',
            self::Medium => 'warning',
            self::Low => 'success',
        };
    }
}

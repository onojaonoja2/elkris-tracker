<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Assigned = 'assigned';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Dispatched => 'Dispatched',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Assigned => 'Assigned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Dispatched => 'info',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
            self::Assigned => 'info',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssignmentStatus: string implements HasLabel
{
    case None = 'none';
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Delivered = 'delivered';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Not Assigned',
            self::Assigned => 'Assigned',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Delivered => 'Delivered',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Assigned => 'info',
            self::Accepted => 'warning',
            self::Rejected => 'danger',
            self::Delivered => 'success',
        };
    }
}

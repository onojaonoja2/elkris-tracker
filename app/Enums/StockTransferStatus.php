<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StockTransferStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Requested = 'requested';
    case Approved = 'approved';
    case Dispatched = 'dispatched';
    case Received = 'received';
    case Cancelled = 'cancelled';
    case Collected = 'collected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Dispatched => 'Dispatched',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
            self::Collected => 'Collected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Requested => 'info',
            self::Approved => 'primary',
            self::Dispatched => 'warning',
            self::Received => 'success',
            self::Cancelled => 'danger',
            self::Collected => 'warning',
        };
    }
}

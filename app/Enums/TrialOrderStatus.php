<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TrialOrderStatus: string implements HasLabel
{
    case Pending = 'pending';
    case ReceiptUploaded = 'receipt_uploaded';
    case VerifiedByAccountant = 'verified_by_accountant';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::ReceiptUploaded => 'Receipt Uploaded',
            self::VerifiedByAccountant => 'Verified by Accountant',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::ReceiptUploaded => 'info',
            self::VerifiedByAccountant => 'primary',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}

<?php

namespace App\Filament\Resources\Audits\Pages;

use App\Filament\Resources\Audits\AuditResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAudits extends ManageRecords
{
    protected static string $resource = AuditResource::class;
}

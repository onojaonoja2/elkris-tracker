<?php

namespace App\Filament\Resources\CallLogs\Pages;

use App\Filament\Resources\CallLogs\CallLogResource;
use Filament\Resources\Pages\ListRecords;

class ListCallLogs extends ListRecords
{
    protected static string $resource = CallLogResource::class;
}

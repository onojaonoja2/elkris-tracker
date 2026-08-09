<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\HasRoleBasedNavigationGroup;
use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{
    use HasRoleBasedNavigationGroup;
}

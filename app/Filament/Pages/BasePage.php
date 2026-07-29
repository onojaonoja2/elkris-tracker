<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\HasRoleBasedNavigationGroup;
use Filament\Pages\Page;

abstract class BasePage extends Page
{
    use HasRoleBasedNavigationGroup;
}

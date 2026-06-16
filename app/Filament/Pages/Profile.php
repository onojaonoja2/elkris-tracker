<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile;

class Profile extends EditProfile
{
    protected function getRedirectUrl(): ?string
    {
        return filament()->getHomeUrl();
    }
}

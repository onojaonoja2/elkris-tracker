<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role !== 'field_agent';
    }

    public function mount()
    {
        if (auth()->user()->role === 'field_agent') {
            return redirect()->to(CustomerResource::getUrl('index'));
        }
    }
}

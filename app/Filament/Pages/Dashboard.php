<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function shouldRegisterNavigation(): bool
    {
        return ! in_array(auth()->user()->role, ['field_agent', 'sales']);
    }

    public function mount()
    {
        if (in_array(auth()->user()->role, ['field_agent', 'sales'])) {
            return redirect()->to(CustomerResource::getUrl('index'));
        }
    }
}

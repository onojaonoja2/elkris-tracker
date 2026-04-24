<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function shouldRegisterNavigation(): bool
    {
        return ! in_array(auth()->user()->role, ['field_agent', 'sales', 'supervisor']);
    }

    public function mount()
    {
        $role = auth()->user()->role;
        if ($role === 'field_agent' || $role === 'sales') {
            return redirect()->to(CustomerResource::getUrl('index'));
        }
        if ($role === 'supervisor') {
            return redirect()->to(SupervisorDashboard::getUrl());
        }
    }
}

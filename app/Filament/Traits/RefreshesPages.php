<?php

namespace App\Filament\Traits;

use Livewire\Attributes\On;

trait RefreshesPages
{
    protected function dispatchRefresh(): void
    {
        $this->dispatch('refresh-dashboard');
    }

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}
}

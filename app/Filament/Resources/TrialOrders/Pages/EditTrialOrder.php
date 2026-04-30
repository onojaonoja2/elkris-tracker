<?php

namespace App\Filament\Resources\TrialOrders\Pages;

use App\Filament\Resources\TrialOrders\TrialOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrialOrder extends EditRecord
{
    protected static string $resource = TrialOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => ! $this->getRecord()->isLocked()),
        ];
    }

    protected function afterSave(): void
    {
        $this->dispatch('refresh-dashboard');
    }

    public function getFormActions(): array
    {
        // Hide save actions for locked records
        if ($this->getRecord()->isLocked()) {
            return [];
        }

        return parent::getFormActions();
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        // Prevent saving locked records
        if ($this->getRecord()->isLocked()) {
            throw new \Exception('Cannot modify a locked trial order.');
        }

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    protected function getRedirectUrl(): ?string
    {
        // Always stay on the edit page for locked records (view only)
        if ($this->getRecord()->isLocked()) {
            return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()->getKey()]);
        }

        return parent::getRedirectUrl();
    }
}

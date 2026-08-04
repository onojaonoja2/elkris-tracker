<?php

namespace App\Filament\Resources\SalesRecords\Pages;

use App\Filament\Resources\SalesRecords\SalesRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesRecord extends EditRecord
{
    protected static string $resource = SalesRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => ! $this->getRecord()->isLocked()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['is_credit'] ?? false) && blank($this->record->credit_status)) {
            $data['credit_status'] = 'pending_payment';
        }

        if (empty($data['is_credit'])) {
            $data['credit_status'] = null;
            $data['expected_collection_date'] = null;
        }

        if (! in_array($this->record->status, ['approved', 'rejected'], true)) {
            $data['status'] = 'pending';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->dispatch('refresh-dashboard');
    }

    public function getFormActions(): array
    {
        if ($this->getRecord()->isLocked()) {
            return [];
        }

        return parent::getFormActions();
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if ($this->getRecord()->isLocked()) {
            throw new \Exception('Cannot modify a locked sales record.');
        }

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    protected function getRedirectUrl(): ?string
    {
        if ($this->getRecord()->isLocked()) {
            return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()->getKey()]);
        }

        return parent::getRedirectUrl();
    }
}

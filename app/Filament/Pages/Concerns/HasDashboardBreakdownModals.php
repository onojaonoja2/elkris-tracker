<?php

namespace App\Filament\Pages\Concerns;

use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

trait HasDashboardBreakdownModals
{
    public ?string $breakdownCategory = null;

    public ?string $breakdownType = null;

    public ?string $breakdownApprovalType = null;

    #[On('open-credit-breakdown')]
    public function openCreditBreakdown(string $category): void
    {
        $this->breakdownCategory = $category;
        $this->breakdownType = 'credit';
        $this->mountAction('creditBreakdown');
    }

    #[On('open-order-breakdown')]
    public function openOrderBreakdown(string $category): void
    {
        $this->breakdownCategory = $category;
        $this->breakdownType = 'order';
        $this->mountAction('orderBreakdown');
    }

    #[On('open-office-sales-breakdown')]
    public function openOfficeSalesBreakdown(): void
    {
        $this->breakdownType = 'office_sales';
        $this->mountAction('officeSalesBreakdown');
    }

    #[On('open-csr-order-breakdown')]
    public function openCsrOrderBreakdown(): void
    {
        $this->breakdownType = 'csr_order';
        $this->mountAction('csrOrderBreakdown');
    }

    #[On('open-approval-breakdown')]
    public function openApprovalBreakdown(string $type): void
    {
        $this->breakdownApprovalType = $type;
        $this->mountAction('approvalBreakdown');
    }

    protected function getCreditBreakdownAction(): Action
    {
        return Action::make('creditBreakdown')
            ->label('Credit Breakdown')
            ->icon('heroicon-o-banknotes')
            ->modalHeading('Credit Outstanding Breakdown')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(function (): View {
                return view('filament.credit-breakdown-modal', [
                    'type' => 'credit',
                    'category' => $this->breakdownCategory,
                ]);
            })
            ->visible(fn (): bool => auth()->user()->hasAnyRole([
                'community_sales_representative',
                'open_market',
                'retail_market',
                'supervisor',
                'accountant',
                'general_accountant',
                'manager',
                'general_manager',
                'admin',
            ]));
    }

    protected function getOrderBreakdownAction(): Action
    {
        return Action::make('orderBreakdown')
            ->label('Order Breakdown')
            ->icon('heroicon-o-shopping-cart')
            ->modalHeading('Order Value Breakdown')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(function (): View {
                return view('filament.order-breakdown-modal', [
                    'type' => 'order',
                    'category' => $this->breakdownCategory,
                ]);
            })
            ->visible(fn (): bool => auth()->user()->hasAnyRole([
                'rep',
                'lead',
                'sales',
                'community_sales_representative',
                'open_market',
                'retail_market',
                'supervisor',
                'manager',
                'general_manager',
                'admin',
            ]));
    }

    protected function getOfficeSalesBreakdownAction(): Action
    {
        return Action::make('officeSalesBreakdown')
            ->label('Office Sales Breakdown')
            ->icon('heroicon-o-building-office')
            ->modalHeading('Office Sales Breakdown')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(function (): View {
                return view('filament.office-sales-breakdown-modal');
            })
            ->visible(fn (): bool => auth()->user()->hasAnyRole([
                'sales',
                'manager',
                'admin',
                'general_manager',
                'accountant',
                'general_accountant',
            ]));
    }

    protected function getCsrOrderBreakdownAction(): Action
    {
        return Action::make('csrOrderBreakdown')
            ->label('CSR Completed Orders')
            ->icon('heroicon-o-check-badge')
            ->modalHeading('CSR Completed Orders Breakdown')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(function (): View {
                return view('filament.csr-order-breakdown-modal');
            })
            ->visible(fn (): bool => auth()->user()->hasAnyRole([
                'supervisor',
                'manager',
                'admin',
                'general_manager',
                'accountant',
                'general_accountant',
            ]));
    }

    protected function getApprovalBreakdownAction(): Action
    {
        return Action::make('approvalBreakdown')
            ->label('Pending Approvals')
            ->icon('heroicon-o-clock')
            ->modalHeading(fn (): string => $this->getApprovalBreakdownHeading())
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(function (): View {
                return view('filament.approval-breakdown-modal', [
                    'type' => $this->breakdownApprovalType,
                ]);
            })
            ->visible(fn (): bool => auth()->user()?->hasAnyRole(['supervisor', 'manager', 'admin']));
    }

    protected function getApprovalBreakdownHeading(): string
    {
        $isManager = auth()->user()?->hasAnyRole(['manager', 'admin']) ?? false;

        if ($isManager) {
            return match ($this->breakdownApprovalType) {
                'stock_count' => 'Open Market Stock Count Approvals',
                'sales_records' => 'Open Market Sales Record Approvals',
                'damaged_return' => 'Open Market Damaged Stock Returns',
                default => 'Pending Stock Transfers',
            };
        }

        return match ($this->breakdownApprovalType) {
            'stock_count' => 'Pending Stock Count Approvals',
            'sales_records' => 'Pending Sales Record Approvals',
            'damaged_return' => 'Damaged Stock Returns Awaiting Supervisor',
            default => 'Pending Stock Transfer Approvals',
        };
    }
}

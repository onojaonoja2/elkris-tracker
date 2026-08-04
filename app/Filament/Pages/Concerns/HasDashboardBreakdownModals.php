<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

trait HasDashboardBreakdownModals
{
    public ?string $breakdownCategory = null;

    public ?string $breakdownType = null;

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

    private function creditBreakdownQuery(): Builder
    {
        $query = SalesRecord::outstanding()->with('agent');

        $user = auth()->user();
        $role = $user->getPrimaryRole();

        if (in_array($role, ['community_sales_representative', 'open_market', 'retail_market'], true) || $this->breakdownCategory === 'my') {
            $query->where('agent_id', $user->id);
        } elseif ($role === 'supervisor' || $this->breakdownCategory === 'csr') {
            $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');
            $query->whereIn('agent_id', $csrIds);
        }

        if (in_array($this->breakdownCategory, ['open_market', 'retail_market', 'community_sales_representative'], true)) {
            $query->where('agent_type', $this->breakdownCategory);
        }

        if ($this->breakdownCategory === 'overdue') {
            $query->where('expected_collection_date', '<', now()->toDateString());
        }

        return $query;
    }

    private function orderBreakdownQuery(): Builder
    {
        $query = Order::where('is_migrated_order', false)
            ->with(['customer', 'user']);

        $user = auth()->user();
        $role = $user->getPrimaryRole();

        if (in_array($role, ['rep', 'lead', 'sales', 'community_sales_representative', 'open_market', 'retail_market'], true) || $this->breakdownCategory === 'my') {
            $query->where('user_id', $user->id);
        } elseif ($role === 'supervisor' || $this->breakdownCategory === 'csr') {
            $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');
            $query->whereIn('user_id', $csrIds);
        }

        if ($this->breakdownCategory === 'pending') {
            $query->where('status', 'pending');
        } elseif ($this->breakdownCategory === 'delivered') {
            $query->where('status', 'delivered');
        }

        if (in_array($this->breakdownCategory, ['open_market', 'retail_market', 'community_sales_representative'], true)) {
            $query->whereHas('user', fn ($q) => $q->where('role', $this->breakdownCategory));
        }

        return $query;
    }
}

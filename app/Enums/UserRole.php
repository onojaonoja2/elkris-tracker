<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Supervisor = 'supervisor';
    case Lead = 'lead';
    case Rep = 'rep';
    case FieldAgent = 'field_agent';
    case CommunitySalesRepresentative = 'community_sales_representative';
    case OpenMarket = 'open_market';
    case RetailMarket = 'retail_market';
    case Sales = 'sales';
    case WarehouseManager = 'warehouse_manager';
    case Accountant = 'accountant';
    case GeneralAccountant = 'general_accountant';
    case GeneralManager = 'general_manager';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Supervisor => 'Supervisor',
            self::Lead => 'Team Lead',
            self::Rep => 'Elkris Portfolio Agent',
            self::FieldAgent => 'Field Agent',
            self::CommunitySalesRepresentative => 'Community Sales Rep',
            self::OpenMarket => 'Open Market',
            self::RetailMarket => 'Retail Market',
            self::Sales => 'Sales',
            self::WarehouseManager => 'Warehouse Manager',
            self::Accountant => 'Accountant',
            self::GeneralAccountant => 'General Accountant',
            self::GeneralManager => 'General Manager',
        };
    }

    public function color(): ?string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Manager => 'primary',
            self::Supervisor => 'info',
            self::Lead => 'warning',
            self::Rep => 'success',
            self::FieldAgent => 'info',
            self::CommunitySalesRepresentative => 'success',
            self::OpenMarket => 'gray',
            self::RetailMarket => 'gray',
            self::Sales => 'warning',
            self::WarehouseManager => 'info',
            self::Accountant => 'primary',
            self::GeneralAccountant => 'primary',
            self::GeneralManager => 'danger',
        };
    }

    public function isFieldAgent(): bool
    {
        return in_array($this, [
            self::FieldAgent,
            self::CommunitySalesRepresentative,
            self::OpenMarket,
            self::RetailMarket,
        ]);
    }

    public function isManagement(): bool
    {
        return in_array($this, [
            self::Admin,
            self::Manager,
            self::GeneralManager,
        ]);
    }

    public function isCommunitySalesRep(): bool
    {
        return $this === self::CommunitySalesRepresentative;
    }

    public function isGeneralAccountant(): bool
    {
        return $this === self::GeneralAccountant;
    }

    public function isGeneralManager(): bool
    {
        return $this === self::GeneralManager;
    }
}

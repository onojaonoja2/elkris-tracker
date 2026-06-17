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
    case DirectSales = 'direct_sales';
    case OpenMarket = 'open_market';
    case RetailMarket = 'retail_market';
    case Sales = 'sales';
    case WarehouseManager = 'warehouse_manager';
    case Accountant = 'accountant';
    case Stockist = 'stockist';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Supervisor => 'Supervisor',
            self::Lead => 'Team Lead',
            self::Rep => 'Sales Rep',
            self::FieldAgent => 'Field Agent',
            self::DirectSales => 'Direct Sales',
            self::OpenMarket => 'Open Market',
            self::RetailMarket => 'Retail Market',
            self::Sales => 'Sales',
            self::WarehouseManager => 'Warehouse Manager',
            self::Accountant => 'Accountant',
            self::Stockist => 'Stockist',
        };
    }

    public function isFieldAgent(): bool
    {
        return in_array($this, [
            self::FieldAgent,
            self::DirectSales,
            self::OpenMarket,
            self::RetailMarket,
        ]);
    }

    public function isManagement(): bool
    {
        return in_array($this, [
            self::Admin,
            self::Manager,
        ]);
    }
}

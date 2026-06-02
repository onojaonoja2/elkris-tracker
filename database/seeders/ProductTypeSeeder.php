<?php

namespace Database\Seeders;

use App\Models\ProductType;
use Illuminate\Database\Seeder;

class ProductTypeSeeder extends Seeder
{
    public function run(): void
    {
        ProductType::create([
            'name' => 'Elkris Oat Flour',
            'available_grammages' => [5000, 1300, 650],
        ]);

        ProductType::create([
            'name' => 'Elkris Plantain Flour',
            'available_grammages' => [1800, 900],
        ]);

        ProductType::create([
            'name' => 'Elkris Poundo Yam',
            'available_grammages' => [1800],
        ]);
    }
}

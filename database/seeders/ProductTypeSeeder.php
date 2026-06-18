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
            'available_grammages' => [
                ['grammage' => 5000, 'carton_quantity' => 3],
                ['grammage' => 1300, 'carton_quantity' => 12],
                ['grammage' => 650, 'carton_quantity' => 24],
            ],
        ]);

        ProductType::create([
            'name' => 'Elkris Plantain Flour',
            'available_grammages' => [
                ['grammage' => 1800, 'carton_quantity' => 10],
                ['grammage' => 900, 'carton_quantity' => 20],
            ],
        ]);

        ProductType::create([
            'name' => 'Elkris Poundo Yam',
            'available_grammages' => [
                ['grammage' => 1800, 'carton_quantity' => 12],
            ],
        ]);
    }
}

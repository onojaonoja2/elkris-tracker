<?php

namespace Tests\Feature\Sales;

use App\Models\Region;
use App\Models\State;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\WarehouseOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeState(string $name, string $code): State
    {
        return State::create([
            'name' => $name,
            'code' => $code,
            'region_id' => Region::create(['name' => $name.' Region', 'code' => strtoupper(substr($code, 0, 2))])->id,
        ]);
    }

    public function test_returns_all_active_warehouses_even_if_not_managed_by_user(): void
    {
        $user = User::factory()->sales()->create();

        $managed = Warehouse::factory()->create([
            'sales_person_id' => $user->id,
            'state_id' => null,
        ]);
        $other = Warehouse::factory()->create(['is_active' => true]);

        $options = WarehouseOptions::for();

        $this->assertArrayHasKey($managed->id, $options);
        $this->assertArrayHasKey($other->id, $options);
    }

    public function test_returns_all_active_warehouses_regardless_of_state(): void
    {
        $state = $this->makeState('Test State', 'TS');

        $user = User::factory()->sales()->create(['state_id' => $state->id]);

        $inState = Warehouse::factory()->create(['state_id' => $state->id]);
        $otherState = Warehouse::factory()->create(['state_id' => $this->makeState('Other State', 'OS')->id]);
        $inactive = Warehouse::factory()->create(['is_active' => false]);

        $options = WarehouseOptions::for();

        $this->assertArrayHasKey($inState->id, $options);
        $this->assertArrayHasKey($otherState->id, $options);
        $this->assertArrayNotHasKey($inactive->id, $options);
    }

    public function test_falls_back_to_all_warehouses_when_none_active(): void
    {
        $user = User::factory()->sales()->create(['state_id' => null]);

        $inactive = Warehouse::factory()->create(['is_active' => false]);

        $options = WarehouseOptions::for();

        $this->assertArrayHasKey($inactive->id, $options);
    }

    public function test_never_returns_empty_options(): void
    {
        User::factory()->sales()->create(['state_id' => null]);

        Warehouse::factory()->create(['is_active' => false]);

        $options = WarehouseOptions::for();

        $this->assertNotEmpty($options);
    }
}

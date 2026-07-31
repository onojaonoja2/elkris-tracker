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

    public function test_returns_warehouses_managed_by_user(): void
    {
        $user = User::factory()->sales()->create();

        $warehouse = Warehouse::factory()->create([
            'sales_person_id' => $user->id,
            'state_id' => null,
        ]);

        $options = WarehouseOptions::for($user);

        $this->assertSame($warehouse->name, $options[$warehouse->id]);
    }

    public function test_returns_warehouses_in_users_state(): void
    {
        $state = $this->makeState('Test State', 'TS');

        $user = User::factory()->sales()->create(['state_id' => $state->id]);

        $inState = Warehouse::factory()->create(['state_id' => $state->id]);
        $otherState = Warehouse::factory()->create(['state_id' => $this->makeState('Other State', 'OS')->id]);

        $options = WarehouseOptions::for($user);

        $this->assertArrayHasKey($inState->id, $options);
        $this->assertArrayNotHasKey($otherState->id, $options);
    }

    public function test_falls_back_to_active_warehouses_when_none_match(): void
    {
        $user = User::factory()->sales()->create(['state_id' => null]);

        $active = Warehouse::factory()->create(['is_active' => true]);
        $inactive = Warehouse::factory()->create(['is_active' => false]);

        $options = WarehouseOptions::for($user);

        $this->assertArrayHasKey($active->id, $options);
        $this->assertArrayNotHasKey($inactive->id, $options);
    }

    public function test_never_returns_empty_options(): void
    {
        $user = User::factory()->sales()->create(['state_id' => null]);

        Warehouse::factory()->create(['is_active' => false]);

        $options = WarehouseOptions::for($user);

        $this->assertNotEmpty($options);
    }
}

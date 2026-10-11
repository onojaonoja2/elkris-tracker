<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserSwapActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_swap_customers_between_two_reps(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $repA = User::factory()->rep()->create(['name' => 'Rep A']);
        $repB = User::factory()->rep()->create(['name' => 'Rep B']);

        $customerA = Customer::factory()->repId($repA)->create();
        $customerB = Customer::factory()->repId($repB)->create();

        Livewire::test(ListUsers::class)
            ->callAction('swapUsersData', data: [
                'role' => 'rep',
                'user_a_id' => $repA->id,
                'user_b_id' => $repB->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame($repB->id, $customerA->fresh()->rep_id);
        $this->assertSame($repA->id, $customerB->fresh()->rep_id);
    }

    public function test_swap_action_rejects_different_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $rep = User::factory()->rep()->create();
        $lead = User::factory()->lead()->create();

        Livewire::test(ListUsers::class)
            ->callAction('swapUsersData', data: [
                'role' => 'rep',
                'user_a_id' => $rep->id,
                'user_b_id' => $lead->id,
            ])
            ->assertHasActionErrors(['user_b_id']);
    }

    public function test_manager_cannot_see_swap_action(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(ListUsers::class)
            ->assertActionHidden('swapUsersData');
    }

    public function test_admin_can_see_swap_action(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertActionVisible('swapUsersData');
    }
}

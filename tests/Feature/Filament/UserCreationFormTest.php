<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ManagerDashboard;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Lga;
use App\Models\Region;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserCreationFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_user_form_includes_phone_and_sms_toggle(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->assertFormFieldVisible('phone')
            ->assertFormFieldVisible('sms_notifications')
            ->fillForm([
                'name' => 'New Agent',
                'email' => 'new-agent@example.com',
                'phone' => '+2348012345678',
                'sms_notifications' => true,
                'password' => 'secret-password',
                'role' => 'community_sales_representative',
                'assigned_cities' => ['Lagos'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'new-agent@example.com',
            'phone' => '+2348012345678',
            'sms_notifications' => true,
        ]);
    }

    public function test_create_user_form_succeeds_without_phone(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'No Phone Agent',
                'email' => 'no-phone@example.com',
                'password' => 'secret-password',
                'role' => 'community_sales_representative',
                'assigned_cities' => ['Lagos'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'no-phone@example.com',
            'phone' => null,
        ]);
    }

    public function test_edit_user_form_still_includes_phone_and_sms_toggle(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $agent->id])
            ->assertFormFieldVisible('phone')
            ->assertFormFieldVisible('sms_notifications');
    }

    public function test_manager_quick_create_action_accepts_phone(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $region = Region::create(['name' => 'South West', 'code' => 'SW']);
        $state = State::create(['name' => 'Lagos', 'code' => 'LA', 'region_id' => $region->id]);
        $lga = Lga::create(['name' => 'Ikeja', 'state_id' => $state->id]);

        Livewire::test(ManagerDashboard::class)
            ->callAction('create_user', data: [
                'name' => 'Market Agent',
                'email' => 'market-agent@example.com',
                'phone' => '+2348098765432',
                'role' => 'open_market',
                'state_id' => $state->id,
                'lga_id' => $lga->id,
                'password' => 'secret-password',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'market-agent@example.com',
            'phone' => '+2348098765432',
            'role' => 'open_market',
        ]);
    }
}

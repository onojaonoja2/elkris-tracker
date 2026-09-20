<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ManagerDashboard;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
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

    public function test_manager_dashboard_links_to_user_creation(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(ManagerDashboard::class)
            ->assertActionExists('addUser')
            ->assertActionDoesNotExist('create_user')
            ->assertActionHasUrl('addUser', UserResource::getUrl('create'));
    }

    public function test_manager_can_assign_csr_role(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $this->assertArrayHasKey('community_sales_representative', UserForm::getRoleOptions());
    }
}

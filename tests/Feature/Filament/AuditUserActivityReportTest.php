<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Audits\Pages\ManageAudits;
use App\Models\Customer;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class AuditUserActivityReportTest extends TestCase
{
    use RefreshDatabase;

    private function createAudit(User $user, array $overrides = []): Audit
    {
        return Audit::create(array_merge([
            'user_type' => User::class,
            'user_id' => $user->id,
            'event' => 'updated',
            'auditable_type' => Customer::class,
            'auditable_id' => 1,
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'approved'],
            'url' => 'http://localhost/admin/customers/1',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ], $overrides));
    }

    public function test_user_filter_shows_only_the_selected_users_activity(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $alice = User::factory()->create(['name' => 'Alice Audit']);
        $bob = User::factory()->create(['name' => 'Bob Builder']);

        $aliceAudit = $this->createAudit($alice);
        $bobAudit = $this->createAudit($bob);

        Livewire::test(ManageAudits::class)
            ->filterTable('user_id', $alice->id)
            ->assertCanSeeTableRecords([$aliceAudit])
            ->assertCanNotSeeTableRecords([$bobAudit]);
    }

    public function test_user_filter_combines_with_date_range_filter(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $alice = User::factory()->create(['name' => 'Alice Audit']);

        $recent = $this->createAudit($alice, ['created_at' => now()->subDay()]);
        $old = $this->createAudit($alice, ['created_at' => now()->subDays(60)]);

        Livewire::test(ManageAudits::class)
            ->filterTable('user_id', $alice->id)
            ->filterTable('created_at', [
                'created_from' => now()->subWeek()->toDateString(),
                'created_until' => now()->toDateString(),
            ])
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_report_action_exists_for_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageAudits::class)
            ->assertActionExists('userActivityReport');
    }

    public function test_report_action_requires_a_selected_user(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageAudits::class)
            ->callAction('userActivityReport')
            ->assertHasActionErrors(['user_id']);
    }

    public function test_report_action_notifies_when_user_has_no_activity(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $idle = User::factory()->create(['name' => 'Idle User']);

        Livewire::test(ManageAudits::class)
            ->callAction('userActivityReport', ['user_id' => $idle->id])
            ->assertNotified();
    }

    public function test_report_data_covers_users_lifetime_in_the_system(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $alice = User::factory()->create(['name' => 'Alice Audit']);

        $this->createAudit($alice, ['created_at' => now()->subDays(90)]);
        $this->createAudit($alice, ['created_at' => now()->subDay()]);
        $this->createAudit($alice, ['event' => 'created', 'old_values' => [], 'created_at' => now()]);

        $report = ManageAudits::buildReportData($alice);

        $this->assertSame(3, $report['total']);
        $this->assertEquals(now()->subDays(90)->startOfMinute(), $report['firstActivity']->startOfMinute());
        $this->assertEquals(now()->startOfMinute(), $report['lastActivity']->startOfMinute());
        $this->assertSame(2, $report['eventCounts']['updated']);
        $this->assertSame(1, $report['eventCounts']['created']);
    }

    public function test_report_data_respects_date_range_of_interest(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $alice = User::factory()->create(['name' => 'Alice Audit']);

        $recent = $this->createAudit($alice, ['created_at' => now()->subDay()]);
        $this->createAudit($alice, ['created_at' => now()->subDays(90)]);

        $report = ManageAudits::buildReportData(
            $alice,
            now()->subWeek()->toDateString(),
            now()->toDateString(),
        );

        $this->assertSame(1, $report['total']);
        $this->assertTrue($report['audits']->first()->is($recent));
    }

    public function test_pdf_report_shows_full_detail_of_everything_the_user_did(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $alice = User::factory()->create(['name' => 'Alice Audit']);

        $longUrl = 'https://elkris.example.com/admin/customers/123?status=active&sort=-created_at&page=2&filter=recently-updated';
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Elkris Test Agent/1.0';

        $this->createAudit($alice, [
            'event' => 'created',
            'old_values' => [],
            'new_values' => ['status' => 'active', 'state_id' => 3],
            'created_at' => now()->subDays(2),
        ]);
        $this->createAudit($alice, [
            'event' => 'updated',
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'approved'],
            'created_at' => now(),
            'url' => $longUrl,
            'user_agent' => $userAgent,
            'tags' => 'billing,urgent',
        ]);

        $report = ManageAudits::buildReportData($alice);

        $html = view('pdf.user-activity-report', $report)->render();

        $this->assertStringContainsString('User Activity Report', $html);
        $this->assertStringContainsString('Alice Audit', $html);
        $this->assertStringContainsString('Activity History (2 entries, chronological)', $html);
        $this->assertStringContainsString('Status:', $html);
        $this->assertStringContainsString('active', $html);
        $this->assertStringContainsString('was: pending', $html);
        $this->assertStringContainsString(now()->subDays(2)->format('d/m/Y'), $html);

        $this->assertStringContainsString(e($longUrl), $html);
        $this->assertStringContainsString('Agent: '.$userAgent, $html);
        $this->assertStringContainsString('Tags: billing,urgent', $html);
        $this->assertStringNotContainsString('…', $html);

        $pdf = Pdf::loadView('pdf.user-activity-report', $report)->output();

        $this->assertStringStartsWith('%PDF', $pdf);
    }
}

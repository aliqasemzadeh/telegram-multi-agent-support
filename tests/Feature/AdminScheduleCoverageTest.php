<?php

namespace Tests\Feature;

use App\Enums\SupportChannel;
use App\Models\AgentScheduleSlot;
use App\Models\User;
use App\Services\Schedule\WeekRange;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminScheduleCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_view_coverage_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.coverage'))
            ->assertOk();
    }

    public function test_admin_can_lock_week(): void
    {
        $admin = User::factory()->admin()->create();
        $weeks = app(WeekRange::class);
        $week = $weeks->findOrCreateWeek($weeks->upcomingWeek()['start']);

        $this->actingAs($admin);

        Livewire::test('pages::admin.weeks')
            ->call('lock', $week->id)
            ->assertHasNoErrors();

        $this->assertTrue($week->refresh()->is_locked);
    }

    public function test_admin_can_assign_agent_to_empty_slot(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create([
            'supports_phone' => true,
            'supports_chat' => false,
        ]);

        $weeks = app(WeekRange::class);
        $range = $weeks->upcomingWeek();
        $date = $range['start']->toDateString();

        $this->actingAs($admin);

        Livewire::test('pages::admin.coverage')
            ->set('channel', 'phone')
            ->set('weekMode', 'upcoming')
            ->set('assignDate', $date)
            ->set('assignHour', 14)
            ->set('assignUserId', $agent->id)
            ->call('assign')
            ->assertHasNoErrors();

        $this->assertTrue(
            AgentScheduleSlot::query()
                ->where('user_id', $agent->id)
                ->whereDate('slot_date', $date)
                ->where('hour', 14)
                ->where('channel', SupportChannel::Phone->value)
                ->where('source', 'admin')
                ->exists()
        );
    }

    public function test_agent_cannot_access_admin_coverage(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)
            ->get(route('admin.coverage'))
            ->assertForbidden();
    }
}

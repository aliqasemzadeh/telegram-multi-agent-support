<?php

namespace Tests\Feature;

use App\Enums\SupportChannel;
use App\Models\AgentScheduleSlot;
use App\Models\ScheduleWeek;
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

    public function test_admin_can_add_and_delete_empty_week(): void
    {
        $admin = User::factory()->admin()->create();
        $weeks = app(WeekRange::class);
        $weeks->findOrCreateWeek($weeks->currentWeek()['start']);
        $weeks->findOrCreateWeek($weeks->upcomingWeek()['start']);

        $before = ScheduleWeek::query()->count();

        $this->actingAs($admin);

        Livewire::test('pages::admin.weeks')
            ->call('addWeek')
            ->assertHasNoErrors();

        $this->assertSame($before + 1, ScheduleWeek::query()->count());

        $newest = ScheduleWeek::query()->orderByDesc('week_start_date')->first();

        Livewire::test('pages::admin.weeks')
            ->call('deleteWeek', $newest->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('schedule_weeks', ['id' => $newest->id]);
    }

    public function test_admin_cannot_delete_week_with_slots(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();
        $weeks = app(WeekRange::class);
        $week = $weeks->findOrCreateWeek($weeks->upcomingWeek()['start']);

        AgentScheduleSlot::query()->create([
            'schedule_week_id' => $week->id,
            'user_id' => $agent->id,
            'slot_date' => $week->week_start_date,
            'hour' => 10,
            'channel' => SupportChannel::Phone->value,
            'source' => 'self',
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::admin.weeks')
            ->call('deleteWeek', $week->id);

        $this->assertDatabaseHas('schedule_weeks', ['id' => $week->id]);
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
        $week = $weeks->findOrCreateWeek($range['start']);
        $date = $range['start']->toDateString();

        $this->actingAs($admin);

        Livewire::test('pages::admin.coverage', ['week' => $week->id])
            ->set('channel', 'phone')
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

    public function test_admin_can_see_agents_on_slot(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create([
            'supports_phone' => true,
        ]);

        $weeks = app(WeekRange::class);
        $range = $weeks->upcomingWeek();
        $week = $weeks->findOrCreateWeek($range['start']);
        $date = $range['start']->toDateString();

        AgentScheduleSlot::query()->create([
            'schedule_week_id' => $week->id,
            'user_id' => $agent->id,
            'slot_date' => $date,
            'hour' => 9,
            'channel' => SupportChannel::Phone->value,
            'source' => 'self',
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::admin.coverage', ['week' => $week->id])
            ->set('channel', 'phone')
            ->call('openSlot', $date, 9)
            ->assertSet('assignDate', $date)
            ->assertSet('assignHour', 9)
            ->assertSee($agent->name);
    }

    public function test_admin_can_view_agent_week(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();

        $this->actingAs($admin)
            ->get(route('admin.agents.week', ['user' => $agent->id]))
            ->assertOk();
    }

    public function test_agent_cannot_access_admin_coverage(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)
            ->get(route('admin.coverage'))
            ->assertForbidden();
    }

    public function test_agent_cannot_access_agent_week_page(): void
    {
        $agent = User::factory()->agent()->create();
        $other = User::factory()->agent()->create();

        $this->actingAs($agent)
            ->get(route('admin.agents.week', ['user' => $other->id]))
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ScheduleSlotSource;
use App\Enums\SupportChannel;
use App\Models\AgentScheduleSlot;
use App\Models\User;
use App\Services\Schedule\WeekRange;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('pages::dashboard')
            ->assertOk();
    }

    public function test_agent_sees_personal_slot_stats_for_selected_week(): void
    {
        $agent = User::factory()->agent()->create();
        $weeks = app(WeekRange::class);
        $week = $weeks->findOrCreateWeek($weeks->currentWeek()['start']);
        $date = $week->week_start_date->toDateString();

        AgentScheduleSlot::query()->create([
            'schedule_week_id' => $week->id,
            'user_id' => $agent->id,
            'slot_date' => $date,
            'hour' => 9,
            'channel' => SupportChannel::Phone,
            'source' => ScheduleSlotSource::Self,
        ]);
        AgentScheduleSlot::query()->create([
            'schedule_week_id' => $week->id,
            'user_id' => $agent->id,
            'slot_date' => $date,
            'hour' => 10,
            'channel' => SupportChannel::Phone,
            'source' => ScheduleSlotSource::Self,
        ]);

        $this->actingAs($agent);

        Livewire::test('pages::dashboard')
            ->assertSet('selectedWeekId', $week->id)
            ->assertSee(__('app.dashboard.phone_hours'))
            ->assertSee(__('app.dashboard.enter_week'));
    }

    public function test_admin_sees_agent_hours_table(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create(['name' => 'Dash Agent']);
        $weeks = app(WeekRange::class);
        $week = $weeks->findOrCreateWeek($weeks->currentWeek()['start']);

        AgentScheduleSlot::query()->create([
            'schedule_week_id' => $week->id,
            'user_id' => $agent->id,
            'slot_date' => $week->week_start_date->toDateString(),
            'hour' => 11,
            'channel' => SupportChannel::Phone,
            'source' => ScheduleSlotSource::Self,
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::dashboard')
            ->assertSee(__('app.dashboard.agents_hours'))
            ->assertSee('Dash Agent')
            ->assertDontSee(__('app.dashboard.enter_week'));
    }

    public function test_agent_can_open_week_plan_from_query(): void
    {
        $agent = User::factory()->agent()->create();
        $weeks = app(WeekRange::class);
        $week = $weeks->findOrCreateWeek($weeks->currentWeek()['start']);

        $this->actingAs($agent);

        Livewire::withQueryParams(['week' => $week->id])
            ->test('pages::user.week')
            ->assertSet('weekId', $week->id)
            ->assertOk();
    }
}

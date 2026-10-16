<?php

namespace Tests\Feature;

use App\Enums\ScheduleSlotSource;
use App\Enums\SupportChannel;
use App\Models\AgentScheduleSlot;
use App\Models\User;
use App\Services\Auth\ImpersonationService;
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

    public function test_roleless_user_gets_agent_role_on_dashboard_and_sees_shift_links(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->hasRole('agent'));

        $this->actingAs($user);

        Livewire::test('pages::dashboard')
            ->assertOk()
            ->assertSee(__('app.dashboard.quick_access'))
            ->assertSee(__('app.dashboard.set_upcoming_shifts'))
            ->assertSee(__('app.dashboard.view_current_week_shifts'))
            ->assertSee(__('app.dashboard.today_attendance'));

        $user->refresh();
        $this->assertTrue($user->hasRole('agent'));
        $this->assertTrue($user->can('schedule.manage-own'));
        $this->assertTrue($user->can('schedule.check-in'));

        $this->get(route('user.week'))->assertOk();
        $this->get(route('user.index'))->assertOk();
    }

    public function test_admin_can_impersonate_agent_from_agents_page_fill_shifts_and_return(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->create([
            'name' => 'Target Agent',
            'supports_phone' => false,
        ]);

        $weeks = app(WeekRange::class);
        $range = $weeks->upcomingWeek();
        $date = $range['start']->toDateString();

        $this->actingAs($admin);

        Livewire::test('pages::admin.agents')
            ->call('impersonate', $agent->id)
            ->assertRedirect(route('user.week'));

        $this->assertAuthenticatedAs($agent);
        $this->assertSame($admin->id, session(ImpersonationService::SESSION_KEY));
        $this->assertTrue($agent->fresh()->hasRole('agent'));
        $this->assertTrue($agent->fresh()->supports_phone);

        $this->get(route('user.week'))
            ->assertOk()
            ->assertSee(__('app.impersonation.banner', ['name' => $agent->name]));

        Livewire::test('pages::user.week')
            ->set('channel', 'phone')
            ->set('rangeDay', $date)
            ->set('fromHour', 9)
            ->set('toHour', 11)
            ->call('applyRange')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agent_schedule_slots', [
            'user_id' => $agent->id,
            'slot_date' => $date,
            'hour' => 9,
            'channel' => 'phone',
        ]);

        $this->post(route('impersonation.leave'))
            ->assertRedirect(route('admin.agents'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session(ImpersonationService::SESSION_KEY));
    }

    public function test_admin_can_impersonate_agent_from_dashboard_for_selected_week(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create(['name' => 'Week Agent']);
        $weeks = app(WeekRange::class);
        $upcoming = $weeks->findOrCreateWeek($weeks->upcomingWeek()['start']);

        $this->actingAs($admin);

        Livewire::test('pages::dashboard')
            ->set('selectedWeekId', $upcoming->id)
            ->call('impersonate', $agent->id)
            ->assertRedirect(route('user.week', ['week' => $upcoming->id]));

        $this->assertAuthenticatedAs($agent);
    }

    public function test_agent_cannot_impersonate_another_user(): void
    {
        $agent = User::factory()->agent()->create();
        $other = User::factory()->agent()->create();

        $this->actingAs($agent);

        Livewire::test('pages::dashboard')
            ->call('impersonate', $other->id)
            ->assertForbidden();

        $this->assertAuthenticatedAs($agent);
    }
}

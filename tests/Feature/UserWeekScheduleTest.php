<?php

namespace Tests\Feature;

use App\Enums\SupportChannel;
use App\Models\AgentScheduleSlot;
use App\Models\User;
use App\Services\Schedule\WeekRange;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class UserWeekScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_agent_can_view_week_page(): void
    {
        $user = User::factory()->agent()->create();

        $this->actingAs($user)
            ->get(route('user.week'))
            ->assertOk();
    }

    public function test_agent_can_save_weekly_slots(): void
    {
        $user = User::factory()->agent()->create();
        $weeks = app(WeekRange::class);
        $range = $weeks->upcomingWeek();
        $date = $range['start']->toDateString();

        $grid = [];
        foreach ($range['days'] as $day) {
            $grid[$day->toDateString()] = array_fill(0, 24, null);
        }
        $grid[$date][9] = SupportChannel::Phone->value;
        $grid[$date][10] = SupportChannel::Phone->value;

        $this->actingAs($user);

        Livewire::test('pages::user.week')
            ->set('grid', $grid)
            ->set('channel', 'phone')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('agent_schedule_slots', 2);
        $this->assertDatabaseHas('agent_schedule_slots', [
            'user_id' => $user->id,
            'slot_date' => $date,
            'hour' => 9,
            'channel' => 'phone',
        ]);
    }

    public function test_agent_cannot_save_when_week_is_locked(): void
    {
        $user = User::factory()->agent()->create();
        $admin = User::factory()->admin()->create();
        $weeks = app(WeekRange::class);
        $range = $weeks->upcomingWeek();
        $week = $weeks->findOrCreateWeek($range['start']);
        $weeks->lock($week, $admin);

        $grid = [];
        foreach ($range['days'] as $day) {
            $grid[$day->toDateString()] = array_fill(0, 24, null);
        }
        $grid[$range['start']->toDateString()][9] = SupportChannel::Phone->value;

        $this->actingAs($user);

        Livewire::test('pages::user.week')
            ->set('grid', $grid)
            ->call('save')
            ->assertHasErrors(['week']);

        $this->assertDatabaseCount('agent_schedule_slots', 0);
    }

    public function test_apply_range_fills_hours(): void
    {
        $user = User::factory()->agent()->create();
        $weeks = app(WeekRange::class);
        $range = $weeks->upcomingWeek();
        $date = $range['start']->toDateString();

        $this->actingAs($user);

        $component = Livewire::test('pages::user.week')
            ->set('channel', 'phone')
            ->set('rangeDay', $date)
            ->set('fromHour', 8)
            ->set('toHour', 10)
            ->call('applyRange')
            ->assertHasNoErrors();

        $this->assertSame('phone', $component->get('grid')[$date][8]);
        $this->assertSame('phone', $component->get('grid')[$date][9]);
        $this->assertSame('phone', $component->get('grid')[$date][10]);
    }

    public function test_agent_without_phone_support_cannot_save_slots(): void
    {
        $user = User::factory()->agent()->create([
            'supports_phone' => false,
        ]);
        $weeks = app(WeekRange::class);
        $range = $weeks->upcomingWeek();
        $date = $range['start']->toDateString();

        $this->actingAs($user);

        $component = Livewire::test('pages::user.week')
            ->assertSet('channel', '');

        $grid = $component->get('grid');
        $grid[$date][14] = SupportChannel::Phone->value;

        $component->set('grid', $grid)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('agent_schedule_slots', 0);
    }

    public function test_agent_can_navigate_adjacent_weeks(): void
    {
        $user = User::factory()->agent()->create();
        $weeks = app(WeekRange::class);
        $upcoming = $weeks->upcomingWeek();
        $currentWeek = $weeks->findOrCreateWeek($upcoming['start']);
        $nextWeek = $weeks->findOrCreateAdjacent($currentWeek, 1);

        $this->actingAs($user);

        Livewire::test('pages::user.week', ['week' => $currentWeek->id])
            ->call('goAdjacent', 1)
            ->assertRedirect(route('user.week', ['week' => $nextWeek->id]));
    }

    public function test_agent_sees_all_weeks_on_dashboard_without_prior_slots(): void
    {
        $user = User::factory()->agent()->create();
        $weeks = app(WeekRange::class);
        $current = $weeks->findOrCreateWeek($weeks->currentWeek()['start']);
        $upcoming = $weeks->findOrCreateWeek($weeks->upcomingWeek()['start']);
        $futureWeek = $weeks->findOrCreateAdjacent($upcoming, 1);

        $this->actingAs($user);

        Livewire::test('pages::dashboard')
            ->assertSee($weeks->dayLabel($futureWeek->week_start_date));
    }

    public function test_registered_user_gets_agent_role_and_can_view_week(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'New Agent User',
            'email' => 'newagent@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors();

        $newUser = User::where('email', 'newagent@example.com')->firstOrFail();
        $this->assertTrue($newUser->hasRole('agent'));
        $this->assertTrue($newUser->can('schedule.manage-own'));

        $newUser->markEmailAsVerified();

        $this->actingAs($newUser)
            ->get(route('user.week'))
            ->assertOk();
    }

    public function test_legacy_chat_channel_value_falls_back_to_phone_without_value_error(): void
    {
        $user = User::factory()->agent()->create();
        $weeks = app(WeekRange::class);
        $week = $weeks->findOrCreateWeek($weeks->upcomingWeek()['start']);

        DB::table('agent_schedule_slots')->insert([
            'schedule_week_id' => $week->id,
            'user_id' => $user->id,
            'slot_date' => $week->week_start_date->toDateString(),
            'hour' => 9,
            'channel' => 'chat',
            'source' => 'self',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $slot = AgentScheduleSlot::query()->firstOrFail();

        $this->assertSame(SupportChannel::Phone, $slot->channel);

        $this->actingAs($user)
            ->get(route('user.week'))
            ->assertOk();
    }
}

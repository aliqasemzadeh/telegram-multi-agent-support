<?php

namespace Tests\Feature;

use App\Enums\SupportChannel;
use App\Models\User;
use App\Services\Schedule\WeekRange;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $grid[$range['start']->toDateString()][9] = SupportChannel::Chat->value;

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
            ->set('channel', 'chat')
            ->set('rangeDay', $date)
            ->set('fromHour', 8)
            ->set('toHour', 10)
            ->call('applyRange')
            ->assertHasNoErrors();

        $this->assertSame('chat', $component->get('grid')[$date][8]);
        $this->assertSame('chat', $component->get('grid')[$date][9]);
        $this->assertSame('chat', $component->get('grid')[$date][10]);
    }
}

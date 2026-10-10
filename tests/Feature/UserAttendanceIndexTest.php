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

class UserAttendanceIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_agent_can_view_attendance_page(): void
    {
        $user = User::factory()->agent()->create();

        $this->actingAs($user)
            ->get(route('user.index'))
            ->assertOk();
    }

    public function test_agent_can_check_in_for_own_slot(): void
    {
        $user = User::factory()->agent()->create();
        $weeks = app(WeekRange::class);
        $week = $weeks->findOrCreateWeek($weeks->currentWeek()['start']);

        $slot = AgentScheduleSlot::query()->create([
            'schedule_week_id' => $week->id,
            'user_id' => $user->id,
            'slot_date' => now()->toDateString(),
            'hour' => now()->hour,
            'channel' => SupportChannel::Phone,
            'source' => ScheduleSlotSource::Self,
        ]);

        $this->actingAs($user);

        Livewire::test('pages::user.index')
            ->call('checkIn', $slot->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agent_checkins', [
            'agent_schedule_slot_id' => $slot->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_agent_cannot_check_in_for_another_users_slot(): void
    {
        $user = User::factory()->agent()->create();
        $other = User::factory()->agent()->create();
        $weeks = app(WeekRange::class);
        $week = $weeks->findOrCreateWeek($weeks->currentWeek()['start']);

        $slot = AgentScheduleSlot::query()->create([
            'schedule_week_id' => $week->id,
            'user_id' => $other->id,
            'slot_date' => now()->toDateString(),
            'hour' => 10,
            'channel' => SupportChannel::Chat,
            'source' => ScheduleSlotSource::Self,
        ]);

        $this->actingAs($user);

        Livewire::test('pages::user.index')
            ->call('checkIn', $slot->id)
            ->assertNotFound();

        $this->assertDatabaseCount('agent_checkins', 0);
    }
}

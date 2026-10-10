<?php

namespace Database\Factories;

use App\Enums\ScheduleSlotSource;
use App\Enums\SupportChannel;
use App\Models\AgentScheduleSlot;
use App\Models\ScheduleWeek;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentScheduleSlot>
 */
class AgentScheduleSlotFactory extends Factory
{
    protected $model = AgentScheduleSlot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $week = ScheduleWeek::factory()->create();

        return [
            'schedule_week_id' => $week->id,
            'user_id' => User::factory(),
            'slot_date' => $week->week_start_date,
            'hour' => fake()->numberBetween(0, 23),
            'channel' => SupportChannel::Phone,
            'source' => ScheduleSlotSource::Self,
        ];
    }
}

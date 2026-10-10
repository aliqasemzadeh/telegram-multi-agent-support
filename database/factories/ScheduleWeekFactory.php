<?php

namespace Database\Factories;

use App\Models\ScheduleWeek;
use App\Services\Schedule\WeekRange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleWeek>
 */
class ScheduleWeekFactory extends Factory
{
    protected $model = ScheduleWeek::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $range = app(WeekRange::class)->upcomingWeek();

        return [
            'week_start_date' => $range['start']->toDateString(),
            'week_end_date' => $range['end']->toDateString(),
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => true,
            'locked_at' => now(),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\AgentCheckin;
use App\Models\AgentScheduleSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentCheckin>
 */
class AgentCheckinFactory extends Factory
{
    protected $model = AgentCheckin::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slot = AgentScheduleSlot::factory()->create();

        return [
            'agent_schedule_slot_id' => $slot->id,
            'user_id' => $slot->user_id,
            'checked_in_at' => now(),
        ];
    }
}

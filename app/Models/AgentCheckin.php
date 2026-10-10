<?php

namespace App\Models;

use Database\Factories\AgentCheckinFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $agent_schedule_slot_id
 * @property int $user_id
 * @property Carbon $checked_in_at
 */
class AgentCheckin extends Model
{
    /** @use HasFactory<AgentCheckinFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'agent_schedule_slot_id',
        'user_id',
        'checked_in_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(AgentScheduleSlot::class, 'agent_schedule_slot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

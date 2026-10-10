<?php

namespace App\Models;

use App\Enums\ScheduleSlotSource;
use App\Enums\SupportChannel;
use Database\Factories\AgentScheduleSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $schedule_week_id
 * @property int $user_id
 * @property Carbon $slot_date
 * @property int $hour
 * @property SupportChannel $channel
 * @property ScheduleSlotSource $source
 */
class AgentScheduleSlot extends Model
{
    /** @use HasFactory<AgentScheduleSlotFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'schedule_week_id',
        'user_id',
        'slot_date',
        'hour',
        'channel',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_date' => 'date',
            'hour' => 'integer',
            'channel' => SupportChannel::class,
            'source' => ScheduleSlotSource::class,
        ];
    }

    public function scheduleWeek(): BelongsTo
    {
        return $this->belongsTo(ScheduleWeek::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkin(): HasOne
    {
        return $this->hasOne(AgentCheckin::class);
    }
}

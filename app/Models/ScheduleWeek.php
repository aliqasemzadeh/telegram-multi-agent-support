<?php

namespace App\Models;

use Database\Factories\ScheduleWeekFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $week_start_date
 * @property Carbon $week_end_date
 * @property bool $is_locked
 * @property Carbon|null $locked_at
 * @property int|null $locked_by
 */
class ScheduleWeek extends Model
{
    /** @use HasFactory<ScheduleWeekFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'week_start_date',
        'week_end_date',
        'is_locked',
        'locked_at',
        'locked_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'week_end_date' => 'date',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(AgentScheduleSlot::class);
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}

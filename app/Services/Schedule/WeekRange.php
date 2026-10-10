<?php

namespace App\Services\Schedule;

use App\Enums\SupportChannel;
use App\Models\AgentScheduleSlot;
use App\Models\ScheduleWeek;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;

class WeekRange
{
    /**
     * Resolve the Saturday–Friday week containing the given date (Jalali week).
     *
     * @return array{start: Carbon, end: Carbon, days: list<Carbon>}
     */
    public function forDate(CarbonInterface $date): array
    {
        $jalali = Jalalian::fromCarbon(Carbon::parse($date)->startOfDay());
        $dayOfWeek = $jalali->getDayOfWeek(); // 0 = Saturday … 6 = Friday
        $start = $jalali->subDays($dayOfWeek)->toCarbon()->startOfDay();
        $end = $start->copy()->addDays(6)->startOfDay();

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $start->copy()->addDays($i);
        }

        return [
            'start' => $start,
            'end' => $end,
            'days' => $days,
        ];
    }

    /**
     * Upcoming week relative to today (next Saturday–Friday if today is not Saturday start of planning;
     * uses the week that starts on the next Saturday when we want "next week").
     *
     * @return array{start: Carbon, end: Carbon, days: list<Carbon>}
     */
    public function upcomingWeek(?CarbonInterface $today = null): array
    {
        $today = Carbon::parse($today ?? now())->startOfDay();
        $current = $this->forDate($today);
        $nextSaturday = $current['start']->copy()->addWeek();

        return $this->forDate($nextSaturday);
    }

    /**
     * Current week containing today.
     *
     * @return array{start: Carbon, end: Carbon, days: list<Carbon>}
     */
    public function currentWeek(?CarbonInterface $today = null): array
    {
        return $this->forDate($today ?? now());
    }

    public function findOrCreateWeek(CarbonInterface $weekStart): ScheduleWeek
    {
        $range = $this->forDate($weekStart);

        return ScheduleWeek::query()->firstOrCreate(
            ['week_start_date' => $range['start']->toDateString()],
            [
                'week_end_date' => $range['end']->toDateString(),
                'is_locked' => false,
            ],
        );
    }

    public function assertUnlocked(ScheduleWeek $week): void
    {
        if ($week->is_locked) {
            throw ValidationException::withMessages([
                'week' => [__('app.schedule.week_locked')],
            ]);
        }
    }

    public function lock(ScheduleWeek $week, User $admin): ScheduleWeek
    {
        $week->fill([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $admin->id,
        ])->save();

        return $week->refresh();
    }

    public function unlock(ScheduleWeek $week): ScheduleWeek
    {
        $week->fill([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
        ])->save();

        return $week->refresh();
    }

    /**
     * Coverage matrix: date => hour => channel => count
     *
     * @return array<string, array<int, array<string, int>>>
     */
    public function coverageCounts(ScheduleWeek $week, ?SupportChannel $channel = null): array
    {
        $query = AgentScheduleSlot::query()
            ->where('schedule_week_id', $week->id)
            ->selectRaw('slot_date, hour, channel, count(*) as total')
            ->groupBy('slot_date', 'hour', 'channel');

        if ($channel !== null) {
            $query->where('channel', $channel->value);
        }

        /** @var Collection<int, object{slot_date: string, hour: int, channel: string, total: int}> $rows */
        $rows = $query->get();

        $matrix = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->slot_date)->toDateString();
            $matrix[$date][(int) $row->hour][$row->channel] = (int) $row->total;
        }

        return $matrix;
    }

    /**
     * @return list<array{date: string, hour: int, channel: string, count: int}>
     */
    public function emptySlots(ScheduleWeek $week, SupportChannel $channel): array
    {
        $range = $this->forDate($week->week_start_date);
        $counts = $this->coverageCounts($week, $channel);
        $empty = [];

        foreach ($range['days'] as $day) {
            $date = $day->toDateString();
            for ($hour = 0; $hour < 24; $hour++) {
                $count = $counts[$date][$hour][$channel->value] ?? 0;
                if ($count === 0) {
                    $empty[] = [
                        'date' => $date,
                        'hour' => $hour,
                        'channel' => $channel->value,
                        'count' => 0,
                    ];
                }
            }
        }

        return $empty;
    }

    public function dayLabel(CarbonInterface $date): string
    {
        return Jalalian::fromCarbon(Carbon::parse($date))->format('%A %Y/%m/%d');
    }

    public function hourLabel(int $hour): string
    {
        return sprintf('%02d:00', $hour);
    }
}

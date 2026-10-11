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
        $startDate = $range['start']->toDateString();

        $existing = ScheduleWeek::query()
            ->whereDate('week_start_date', $startDate)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return ScheduleWeek::query()->create([
            'week_start_date' => $startDate,
            'week_end_date' => $range['end']->toDateString(),
            'is_locked' => false,
        ]);
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
            $channel = $row->channel instanceof SupportChannel
                ? $row->channel->value
                : (string) $row->channel;
            $matrix[$date][(int) $row->hour][$channel] = (int) $row->total;
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

    /**
     * Jalali week-of-year (Saturday-start weeks; week 1 contains Farvardin 1).
     */
    public function weekNumber(CarbonInterface $date): int
    {
        $jalali = Jalalian::fromCarbon(Carbon::parse($date)->startOfDay());
        $yearStart = new Jalalian($jalali->getYear(), 1, 1);
        $offset = $yearStart->getDayOfWeek();

        return (int) floor(($jalali->getDayOfYear() - 1 + $offset) / 7) + 1;
    }

    public function weekDateRange(ScheduleWeek $week): string
    {
        $start = Jalalian::fromCarbon(Carbon::parse($week->week_start_date)->startOfDay())->format('Y/m/d');
        $end = Jalalian::fromCarbon(Carbon::parse($week->week_end_date)->startOfDay())->format('Y/m/d');

        return $start.' — '.$end;
    }

    public function weekLabel(ScheduleWeek $week): string
    {
        return __('app.schedule.week_number_label', [
            'number' => $this->weekNumber($week->week_start_date),
            'start' => Jalalian::fromCarbon(Carbon::parse($week->week_start_date)->startOfDay())->format('Y/m/d'),
            'end' => Jalalian::fromCarbon(Carbon::parse($week->week_end_date)->startOfDay())->format('Y/m/d'),
        ]);
    }

    public function addWeekAfterLatest(): ScheduleWeek
    {
        $latest = ScheduleWeek::query()
            ->orderByDesc('week_start_date')
            ->first();

        $base = $latest !== null
            ? $latest->week_start_date->copy()->addWeek()
            : $this->currentWeek()['start'];

        return $this->findOrCreateWeek($base);
    }

    public function findOrCreateAdjacent(ScheduleWeek $week, int $direction): ScheduleWeek
    {
        $direction = $direction >= 0 ? 1 : -1;
        $target = $week->week_start_date->copy()->addWeeks($direction);

        return $this->findOrCreateWeek($target);
    }

    public function deleteIfEmpty(ScheduleWeek $week): void
    {
        if ($week->slots()->exists()) {
            throw ValidationException::withMessages([
                'week' => [__('app.schedule.week_not_empty')],
            ]);
        }

        $week->delete();
    }

    /**
     * @return Collection<int, AgentScheduleSlot>
     */
    public function agentsOnSlot(
        ScheduleWeek $week,
        string $date,
        int $hour,
        SupportChannel $channel,
    ): Collection {
        return AgentScheduleSlot::query()
            ->with('user:id,name')
            ->where('schedule_week_id', $week->id)
            ->whereDate('slot_date', $date)
            ->where('hour', $hour)
            ->where('channel', $channel->value)
            ->orderBy('id')
            ->get();
    }
}

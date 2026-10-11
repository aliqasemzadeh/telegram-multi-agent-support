<?php

use App\Models\AgentScheduleSlot;
use App\Models\ScheduleWeek;
use App\Models\User;
use App\Services\Schedule\WeekRange;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Agent week')] class extends Component {
    public User $user;

    public ?int $week = null;

    /** @var array<string, array<int, string|null>> */
    public array $grid = [];

    public function mount(User $user, WeekRange $weeks, ?int $week = null): void
    {
        abort_unless(Auth::user()?->can('schedule.view-coverage'), 403);
        abort_unless($user->hasRole('agent'), 404);

        $this->user = $user;

        if ($week !== null) {
            ScheduleWeek::query()->findOrFail($week);
            $this->week = $week;
        } else {
            $created = $weeks->findOrCreateWeek($weeks->upcomingWeek()['start']);
            $this->week = $created->id;
        }

        $this->loadGrid($weeks);
    }

    protected function loadGrid(WeekRange $weeks): void
    {
        $week = ScheduleWeek::query()->findOrFail($this->week);
        $range = $weeks->forDate($week->week_start_date);

        $this->grid = [];
        foreach ($range['days'] as $day) {
            $this->grid[$day->toDateString()] = array_fill(0, 24, null);
        }

        $slots = AgentScheduleSlot::query()
            ->where('schedule_week_id', $week->id)
            ->where('user_id', $this->user->id)
            ->get();

        foreach ($slots as $slot) {
            $date = $slot->slot_date->toDateString();
            if (array_key_exists($date, $this->grid)) {
                $this->grid[$date][$slot->hour] = $slot->channel->value;
            }
        }
    }

    #[Computed]
    public function weekMeta(): array
    {
        $weeks = app(WeekRange::class);
        $week = ScheduleWeek::query()->findOrFail($this->week);
        $range = $weeks->forDate($week->week_start_date);

        return [
            'week' => $week,
            'days' => $range['days'],
            'label' => $weeks->weekLabel($week),
            'locked' => $week->is_locked,
        ];
    }

    public function goAdjacent(int $direction, WeekRange $weeks): void
    {
        abort_unless(Auth::user()?->can('schedule.view-coverage'), 403);

        $current = ScheduleWeek::query()->findOrFail($this->week);
        $adjacent = $weeks->findOrCreateAdjacent($current, $direction);

        $this->redirect(
            route('admin.agents.week', ['user' => $this->user->id, 'week' => $adjacent->id]),
            navigate: true,
        );
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('app.agents.week_title', ['name' => $user->name]) }}</flux:heading>
            <flux:text>{{ $this->weekMeta['label'] }}</flux:text>
        </div>

        <div class="flex gap-2">
            <flux:tooltip content="{{ __('app.schedule.prev_week') }}">
                <flux:button
                    size="sm"
                    variant="primary"
                    color="zinc"
                    icon="chevron-right"
                    wire:click="goAdjacent(-1)"
                />
            </flux:tooltip>
            <flux:tooltip content="{{ __('app.schedule.next_week') }}">
                <flux:button
                    size="sm"
                    variant="primary"
                    color="zinc"
                    icon="chevron-left"
                    wire:click="goAdjacent(1)"
                />
            </flux:tooltip>
            <flux:button
                size="sm"
                variant="primary"
                color="cyan"
                icon="chart-bar"
                :href="route('admin.coverage', ['week' => $week])"
                wire:navigate
            >
                {{ __('app.schedule.view_coverage') }}
            </flux:button>
        </div>
    </div>

    @if ($this->weekMeta['locked'])
        <flux:callout variant="warning" icon="lock">
            {{ __('app.schedule.week_locked_banner') }}
        </flux:callout>
    @endif

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full border-collapse text-xs">
            <thead>
                <tr class="bg-zinc-50 dark:bg-zinc-900">
                    <th class="sticky start-0 z-10 border-e border-b border-zinc-200 bg-zinc-50 px-2 py-2 text-start dark:border-zinc-700 dark:bg-zinc-900">
                        {{ __('app.schedule.hour') }}
                    </th>
                    @foreach ($this->weekMeta['days'] as $day)
                        <th class="border-b border-e border-zinc-200 px-2 py-2 text-center whitespace-nowrap dark:border-zinc-700">
                            {{ app(\App\Services\Schedule\WeekRange::class)->dayLabel($day) }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @for ($hour = 0; $hour < 24; $hour++)
                    <tr>
                        <td class="sticky start-0 z-10 border-e border-b border-zinc-200 bg-white px-2 py-1 font-medium dark:border-zinc-700 dark:bg-zinc-800">
                            {{ sprintf('%02d:00', $hour) }}
                        </td>
                        @foreach ($this->weekMeta['days'] as $day)
                            @php
                                $date = $day->toDateString();
                                $value = $grid[$date][$hour] ?? null;
                                $classes = match ($value) {
                                    'phone' => 'bg-sky-500 text-white',
                                    'chat' => 'bg-emerald-500 text-white',
                                    default => 'bg-zinc-100 dark:bg-zinc-800',
                                };
                            @endphp
                            <td class="border-b border-e border-zinc-200 p-0.5 dark:border-zinc-700">
                                <div
                                    class="flex h-8 w-full items-center justify-center rounded {{ $classes }}"
                                    title="{{ $value ? __('app.channel.'.$value) : __('app.schedule.empty_slot') }}"
                                >
                                    @if ($value === 'phone')
                                        <flux:icon.phone class="size-3.5" />
                                    @elseif ($value === 'chat')
                                        <flux:icon.message-circle class="size-3.5" />
                                    @endif
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>
</section>

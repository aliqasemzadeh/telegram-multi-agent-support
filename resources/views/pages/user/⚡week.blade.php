<?php

use App\Enums\ScheduleSlotSource;
use App\Enums\SupportChannel;
use App\Models\AgentScheduleSlot;
use App\Services\Schedule\WeekRange;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Weekly plan')] class extends Component {
    /** @var array<string, array<int, string|null>> date => hour => channel value or null */
    public array $grid = [];

    public string $channel = 'phone';

    public string $rangeDay = '';

    public int $fromHour = 9;

    public int $toHour = 17;

    public function mount(WeekRange $weeks): void
    {
        abort_unless(Auth::user()?->can('schedule.manage-own'), 403);

        $range = $weeks->upcomingWeek();
        $this->rangeDay = $range['start']->toDateString();

        $week = $weeks->findOrCreateWeek($range['start']);
        $userId = Auth::id();

        $slots = AgentScheduleSlot::query()
            ->where('schedule_week_id', $week->id)
            ->where('user_id', $userId)
            ->get();

        foreach ($range['days'] as $day) {
            $date = $day->toDateString();
            $this->grid[$date] = array_fill(0, 24, null);
        }

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
        $range = $weeks->upcomingWeek();
        $week = $weeks->findOrCreateWeek($range['start']);

        return [
            'range' => $range,
            'week' => $week,
            'locked' => $week->is_locked,
            'days' => $range['days'],
        ];
    }

    public function toggle(string $date, int $hour): void
    {
        $this->ensureEditable();

        $current = $this->grid[$date][$hour] ?? null;

        if ($current === $this->channel) {
            $this->grid[$date][$hour] = null;
        } else {
            $this->grid[$date][$hour] = $this->channel;
        }
    }

    public function applyRange(): void
    {
        $this->ensureEditable();

        $this->validate([
            'rangeDay' => ['required', 'date'],
            'fromHour' => ['required', 'integer', 'min:0', 'max:23'],
            'toHour' => ['required', 'integer', 'min:0', 'max:23', 'gte:fromHour'],
            'channel' => ['required', 'in:phone,chat'],
        ]);

        if (! array_key_exists($this->rangeDay, $this->grid)) {
            $this->addError('rangeDay', __('validation.exists', ['attribute' => 'day']));

            return;
        }

        for ($hour = $this->fromHour; $hour <= $this->toHour; $hour++) {
            $this->grid[$this->rangeDay][$hour] = $this->channel;
        }

        Flux::toast(variant: 'success', text: __('app.schedule.range_applied'));
    }

    public function save(WeekRange $weeks): void
    {
        $this->ensureEditable();

        $meta = $this->weekMeta;
        $week = $meta['week'];
        $user = Auth::user();

        AgentScheduleSlot::query()
            ->where('schedule_week_id', $week->id)
            ->where('user_id', $user->id)
            ->delete();

        $rows = [];
        $now = now();

        foreach ($this->grid as $date => $hours) {
            foreach ($hours as $hour => $channel) {
                if ($channel === null) {
                    continue;
                }

                if ($channel === SupportChannel::Phone->value && ! $user->supports_phone) {
                    continue;
                }

                if ($channel === SupportChannel::Chat->value && ! $user->supports_chat) {
                    continue;
                }

                $rows[] = [
                    'schedule_week_id' => $week->id,
                    'user_id' => $user->id,
                    'slot_date' => $date,
                    'hour' => $hour,
                    'channel' => $channel,
                    'source' => ScheduleSlotSource::Self->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            AgentScheduleSlot::query()->insert($rows);
        }

        Flux::toast(variant: 'success', text: __('app.schedule.saved'));
    }

    protected function ensureEditable(): void
    {
        abort_unless(Auth::user()?->can('schedule.manage-own'), 403);

        $week = $this->weekMeta['week'];
        app(WeekRange::class)->assertUnlocked($week);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('app.schedule.week_title') }}</flux:heading>
        <flux:text>{{ __('app.schedule.week_subtitle') }} — {{ __('app.schedule.upcoming_week') }}</flux:text>
    </div>

    @if ($this->weekMeta['locked'])
        <flux:callout variant="warning" icon="lock">
            {{ __('app.schedule.week_locked_banner') }}
        </flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div class="grid gap-4 md:grid-cols-4">
            <flux:select wire:model.live="channel" :label="__('app.schedule.channel')">
                <flux:select.option value="phone">{{ __('app.channel.phone') }}</flux:select.option>
                <flux:select.option value="chat">{{ __('app.channel.chat') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model="rangeDay" :label="__('app.schedule.day')">
                @foreach ($this->weekMeta['days'] as $day)
                    <flux:select.option value="{{ $day->toDateString() }}">
                        {{ app(\App\Services\Schedule\WeekRange::class)->dayLabel($day) }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="fromHour" :label="__('app.schedule.from_hour')">
                @for ($h = 0; $h < 24; $h++)
                    <flux:select.option value="{{ $h }}">{{ sprintf('%02d:00', $h) }}</flux:select.option>
                @endfor
            </flux:select>

            <flux:select wire:model="toHour" :label="__('app.schedule.to_hour')">
                @for ($h = 0; $h < 24; $h++)
                    <flux:select.option value="{{ $h }}">{{ sprintf('%02d:00', $h) }}</flux:select.option>
                @endfor
            </flux:select>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button
                type="button"
                variant="primary"
                color="teal"
                icon="calendar-days"
                wire:click="applyRange"
                :disabled="$this->weekMeta['locked']"
                class="w-full sm:w-auto"
            >
                {{ __('app.schedule.apply_range') }}
            </flux:button>

            <flux:button
                type="button"
                variant="primary"
                color="orange"
                wire:click="save"
                :disabled="$this->weekMeta['locked']"
                class="w-full sm:w-auto"
            >
                {{ __('app.schedule.save') }}
            </flux:button>
        </div>
    </flux:card>

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
                                    default => 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700',
                                };
                            @endphp
                            <td class="border-b border-e border-zinc-200 p-0.5 dark:border-zinc-700">
                                <button
                                    type="button"
                                    wire:click="toggle('{{ $date }}', {{ $hour }})"
                                    @disabled($this->weekMeta['locked'])
                                    class="flex h-8 w-full items-center justify-center rounded {{ $classes }} disabled:cursor-not-allowed disabled:opacity-60"
                                    title="{{ $value ? __('app.channel.'.$value) : __('app.schedule.empty_slot') }}"
                                >
                                    @if ($value === 'phone')
                                        <flux:icon.phone class="size-3.5" />
                                    @elseif ($value === 'chat')
                                        <flux:icon.message-circle class="size-3.5" />
                                    @endif
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>
</section>

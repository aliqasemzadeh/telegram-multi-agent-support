<?php

use App\Enums\ScheduleSlotSource;
use App\Enums\SupportChannel;
use App\Models\AgentScheduleSlot;
use App\Models\User;
use App\Services\Schedule\WeekRange;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Coverage')] class extends Component {
    public string $channel = 'phone';

    public string $weekMode = 'upcoming';

    public ?string $assignDate = null;

    public ?int $assignHour = null;

    public ?int $assignUserId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('schedule.view-coverage'), 403);
    }

    #[Computed]
    public function weekMeta(): array
    {
        $weeks = app(WeekRange::class);
        $range = $this->weekMode === 'current'
            ? $weeks->currentWeek()
            : $weeks->upcomingWeek();
        $week = $weeks->findOrCreateWeek($range['start']);

        return [
            'range' => $range,
            'week' => $week,
            'days' => $range['days'],
            'counts' => $weeks->coverageCounts($week, SupportChannel::from($this->channel)),
        ];
    }

    #[Computed]
    public function agentsForChannel()
    {
        $column = $this->channel === 'phone' ? 'supports_phone' : 'supports_chat';

        return User::query()
            ->role('agent')
            ->where($column, true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function openAssign(string $date, int $hour): void
    {
        abort_unless(Auth::user()?->can('schedule.assign'), 403);

        $this->assignDate = $date;
        $this->assignHour = $hour;
        $this->assignUserId = null;

        $this->modal('assign-agent')->show();
    }

    public function assign(WeekRange $weeks): void
    {
        abort_unless(Auth::user()?->can('schedule.assign'), 403);

        $this->validate([
            'assignDate' => ['required', 'date'],
            'assignHour' => ['required', 'integer', 'min:0', 'max:23'],
            'assignUserId' => ['required', 'integer', Rule::exists('users', 'id')],
            'channel' => ['required', 'in:phone,chat'],
        ]);

        $user = User::query()->findOrFail($this->assignUserId);
        $channel = SupportChannel::from($this->channel);

        if ($channel === SupportChannel::Phone && ! $user->supports_phone) {
            $this->addError('assignUserId', __('app.schedule.no_agents_for_channel'));

            return;
        }

        if ($channel === SupportChannel::Chat && ! $user->supports_chat) {
            $this->addError('assignUserId', __('app.schedule.no_agents_for_channel'));

            return;
        }

        $week = $this->weekMeta['week'];

        AgentScheduleSlot::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'slot_date' => $this->assignDate,
                'hour' => $this->assignHour,
                'channel' => $channel->value,
            ],
            [
                'schedule_week_id' => $week->id,
                'source' => ScheduleSlotSource::Admin->value,
            ],
        );

        unset($this->weekMeta);

        $this->modal('assign-agent')->close();

        Flux::toast(variant: 'success', text: __('app.schedule.assign_toast'));
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('app.schedule.coverage_title') }}</flux:heading>
        <flux:text>{{ __('app.schedule.coverage_subtitle') }}</flux:text>
    </div>

    <flux:card class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
            <flux:select wire:model.live="channel" :label="__('app.schedule.filter_channel')">
                <flux:select.option value="phone">{{ __('app.channel.phone') }}</flux:select.option>
                <flux:select.option value="chat">{{ __('app.channel.chat') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="weekMode" :label="__('app.schedule.day')">
                <flux:select.option value="current">{{ __('app.schedule.current_week') }}</flux:select.option>
                <flux:select.option value="upcoming">{{ __('app.schedule.upcoming_week') }}</flux:select.option>
            </flux:select>
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
                                $count = $this->weekMeta['counts'][$date][$hour][$channel] ?? 0;
                                $classes = $count > 0
                                    ? 'bg-emerald-500 text-white'
                                    : 'bg-rose-100 text-rose-800 hover:bg-rose-200 dark:bg-rose-900/40 dark:text-rose-100';
                            @endphp
                            <td class="border-b border-e border-zinc-200 p-0.5 dark:border-zinc-700">
                                <button
                                    type="button"
                                    wire:click="openAssign('{{ $date }}', {{ $hour }})"
                                    class="flex h-8 w-full items-center justify-center rounded {{ $classes }}"
                                    title="{{ $count > 0 ? __('app.schedule.covered') : __('app.schedule.empty_slot') }}"
                                >
                                    {{ $count }}
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>

    <flux:modal name="assign-agent" flyout position="right" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('app.schedule.assign') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ $assignDate }} — {{ $assignHour !== null ? sprintf('%02d:00', $assignHour) : '' }}
                    ({{ __('app.channel.'.$channel) }})
                </flux:text>
            </div>

            <form wire:submit="assign" class="space-y-4">
                <flux:select wire:model="assignUserId" :label="__('app.schedule.select_agent')" searchable>
                    @forelse ($this->agentsForChannel as $agent)
                        <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                    @empty
                        <flux:select.option value="" disabled>{{ __('app.schedule.no_agents_for_channel') }}</flux:select.option>
                    @endforelse
                </flux:select>
                <flux:error name="assignUserId" />

                <flux:button type="submit" variant="primary" color="orange" class="w-full">
                    {{ __('app.common.save') }}
                </flux:button>
            </form>
        </div>
    </flux:modal>
</section>

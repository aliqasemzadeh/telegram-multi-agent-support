<?php

use App\Enums\ScheduleSlotSource;
use App\Enums\SupportChannel;
use App\Models\AgentScheduleSlot;
use App\Models\ScheduleWeek;
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

    public ?int $week = null;

    public ?string $assignDate = null;

    public ?int $assignHour = null;

    public ?int $assignUserId = null;

    public function mount(WeekRange $weeks, ?int $week = null): void
    {
        abort_unless(Auth::user()?->can('schedule.view-coverage'), 403);

        if ($week !== null) {
            ScheduleWeek::query()->findOrFail($week);
            $this->week = $week;

            return;
        }

        $created = $weeks->findOrCreateWeek($weeks->upcomingWeek()['start']);
        $this->week = $created->id;
    }

    #[Computed]
    public function weekMeta(): array
    {
        $weeks = app(WeekRange::class);
        $week = ScheduleWeek::query()->findOrFail($this->week);
        $range = $weeks->forDate($week->week_start_date);

        return [
            'range' => $range,
            'week' => $week,
            'days' => $range['days'],
            'label' => $weeks->weekLabel($week),
            'number' => $weeks->weekNumber($week->week_start_date),
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

    #[Computed]
    public function slotAgents()
    {
        if ($this->assignDate === null || $this->assignHour === null || $this->week === null) {
            return collect();
        }

        return app(WeekRange::class)->agentsOnSlot(
            ScheduleWeek::query()->findOrFail($this->week),
            $this->assignDate,
            $this->assignHour,
            SupportChannel::from($this->channel),
        );
    }

    public function goAdjacent(int $direction, WeekRange $weeks): void
    {
        abort_unless(Auth::user()?->can('schedule.view-coverage'), 403);

        $current = ScheduleWeek::query()->findOrFail($this->week);
        $adjacent = $weeks->findOrCreateAdjacent($current, $direction);

        $this->redirect(route('admin.coverage', ['week' => $adjacent->id]), navigate: true);
    }

    public function openSlot(string $date, int $hour): void
    {
        abort_unless(Auth::user()?->can('schedule.view-coverage'), 403);

        $this->assignDate = $date;
        $this->assignHour = $hour;
        $this->assignUserId = null;
        unset($this->slotAgents);

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

        unset($this->weekMeta, $this->slotAgents);
        $this->assignUserId = null;

        Flux::toast(variant: 'success', text: __('app.schedule.assign_toast'));
    }

    public function unassign(int $slotId): void
    {
        abort_unless(Auth::user()?->can('schedule.assign'), 403);

        $slot = AgentScheduleSlot::query()
            ->where('schedule_week_id', $this->week)
            ->whereKey($slotId)
            ->firstOrFail();

        $slot->delete();

        unset($this->weekMeta, $this->slotAgents);

        Flux::toast(variant: 'success', text: __('app.schedule.unassign_toast'));
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('app.schedule.coverage_title') }}</flux:heading>
        <flux:text>{{ __('app.schedule.coverage_subtitle') }}</flux:text>
    </div>

    <flux:card class="space-y-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="grid flex-1 gap-4 md:grid-cols-2">
                <flux:select wire:model.live="channel" :label="__('app.schedule.filter_channel')">
                    <flux:select.option value="phone">{{ __('app.channel.phone') }}</flux:select.option>
                    <flux:select.option value="chat">{{ __('app.channel.chat') }}</flux:select.option>
                </flux:select>

                <div>
                    <flux:text class="mb-1 text-sm font-medium">{{ __('app.schedule.week_number') }}</flux:text>
                    <flux:text>{{ $this->weekMeta['label'] }}</flux:text>
                </div>
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
            </div>
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
                                    wire:click="openSlot('{{ $date }}', {{ $hour }})"
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
                <flux:heading size="lg">{{ __('app.schedule.agents_on_slot') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ $assignDate }} — {{ $assignHour !== null ? sprintf('%02d:00', $assignHour) : '' }}
                    ({{ __('app.channel.'.$channel) }})
                </flux:text>
            </div>

            <div class="space-y-2">
                @forelse ($this->slotAgents as $slot)
                    <div class="flex items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700" wire:key="slot-agent-{{ $slot->id }}">
                        <div class="min-w-0">
                            <flux:text class="font-medium">{{ $slot->user?->name }}</flux:text>
                            <flux:text class="text-xs">{{ __('app.channel.'.$slot->channel->value) }}</flux:text>
                        </div>
                        <div class="flex gap-1">
                            @can('schedule.view-coverage')
                                <flux:tooltip content="{{ __('app.agents.view_week') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="sky"
                                        icon="calendar"
                                        :href="route('admin.agents.week', ['user' => $slot->user_id, 'week' => $week])"
                                        wire:navigate
                                    />
                                </flux:tooltip>
                            @endcan
                            @can('schedule.assign')
                                <flux:tooltip content="{{ __('app.common.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="red"
                                        icon="trash"
                                        wire:click="unassign({{ $slot->id }})"
                                        wire:confirm="{{ __('app.common.are_you_sure') }}"
                                    />
                                </flux:tooltip>
                            @endcan
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('app.schedule.no_agents_on_slot') }}</flux:text>
                @endforelse
            </div>

            @can('schedule.assign')
                <form wire:submit="assign" class="space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:heading size="md">{{ __('app.schedule.assign') }}</flux:heading>

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
            @endcan
        </div>
    </flux:modal>
</section>

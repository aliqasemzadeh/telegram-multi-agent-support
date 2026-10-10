<?php

use App\Models\AgentCheckin;
use App\Models\AgentScheduleSlot;
use App\Services\Schedule\WeekRange;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Attendance')] class extends Component {
    public function mount(): void
    {
        abort_unless(Auth::user()?->can('schedule.check-in'), 403);
    }

    #[Computed]
    public function weekMeta(): array
    {
        $weeks = app(WeekRange::class);
        $range = $weeks->currentWeek();
        $week = $weeks->findOrCreateWeek($range['start']);

        return [
            'range' => $range,
            'week' => $week,
            'days' => $range['days'],
        ];
    }

    #[Computed]
    public function todaySlots()
    {
        $today = now()->toDateString();

        return AgentScheduleSlot::query()
            ->with('checkin')
            ->where('user_id', Auth::id())
            ->whereDate('slot_date', $today)
            ->orderBy('hour')
            ->orderBy('channel')
            ->get();
    }

    #[Computed]
    public function weekSlots()
    {
        $week = $this->weekMeta['week'];

        return AgentScheduleSlot::query()
            ->with('checkin')
            ->where('user_id', Auth::id())
            ->where('schedule_week_id', $week->id)
            ->orderBy('slot_date')
            ->orderBy('hour')
            ->get()
            ->groupBy(fn (AgentScheduleSlot $slot) => $slot->slot_date->toDateString());
    }

    public function checkIn(int $slotId): void
    {
        abort_unless(Auth::user()?->can('schedule.check-in'), 403);

        $slot = AgentScheduleSlot::query()
            ->where('user_id', Auth::id())
            ->whereKey($slotId)
            ->firstOrFail();

        if ($slot->checkin()->exists()) {
            Flux::toast(variant: 'warning', text: __('app.schedule.already_checked_in'));

            return;
        }

        AgentCheckin::query()->create([
            'agent_schedule_slot_id' => $slot->id,
            'user_id' => Auth::id(),
            'checked_in_at' => now(),
        ]);

        unset($this->todaySlots, $this->weekSlots);

        Flux::toast(variant: 'success', text: __('app.schedule.checked_in'));
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('app.schedule.attendance_title') }}</flux:heading>
        <flux:text>{{ __('app.schedule.attendance_subtitle') }} — {{ __('app.schedule.current_week') }}</flux:text>
    </div>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('app.schedule.attendance_title') }} ({{ now()->toDateString() }})</flux:heading>

        @if ($this->todaySlots->isEmpty())
            <flux:text>{{ __('app.schedule.no_slots_today') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('app.schedule.hour') }}</flux:table.column>
                    <flux:table.column>{{ __('app.schedule.channel') }}</flux:table.column>
                    <flux:table.column>{{ __('app.agents.actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->todaySlots as $slot)
                        <flux:table.row wire:key="today-slot-{{ $slot->id }}">
                            <flux:table.cell>{{ sprintf('%02d:00', $slot->hour) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$slot->channel->value === 'phone' ? 'sky' : 'emerald'" size="sm">
                                    {{ $slot->channel->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($slot->checkin)
                                    <flux:badge color="green" size="sm">{{ __('app.schedule.checked_in_label') }}</flux:badge>
                                @else
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        color="teal"
                                        icon="check"
                                        wire:click="checkIn({{ $slot->id }})"
                                    >
                                        {{ __('app.schedule.check_in') }}
                                    </flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('app.schedule.week_title') }}</flux:heading>

        @forelse ($this->weekSlots as $date => $slots)
            <div class="space-y-2" wire:key="week-day-{{ $date }}">
                <flux:heading size="sm">
                    {{ app(\App\Services\Schedule\WeekRange::class)->dayLabel(\Illuminate\Support\Carbon::parse($date)) }}
                </flux:heading>
                <div class="flex flex-wrap gap-2">
                    @foreach ($slots as $slot)
                        <flux:badge
                            :color="$slot->checkin ? 'green' : ($slot->channel->value === 'phone' ? 'sky' : 'emerald')"
                            size="sm"
                        >
                            {{ sprintf('%02d:00', $slot->hour) }} — {{ $slot->channel->label() }}
                            @if ($slot->checkin)
                                ✓
                            @endif
                        </flux:badge>
                    @endforeach
                </div>
            </div>
        @empty
            <flux:text>{{ __('app.schedule.no_slots_today') }}</flux:text>
        @endforelse
    </flux:card>
</section>

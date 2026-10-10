<?php

use App\Models\ScheduleWeek;
use App\Services\Schedule\WeekRange;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Week management')] class extends Component {
    public function mount(): void
    {
        abort_unless(Auth::user()?->can('schedule.lock-week'), 403);
    }

    #[Computed]
    public function weeks()
    {
        $weeks = app(WeekRange::class);
        $current = $weeks->findOrCreateWeek($weeks->currentWeek()['start']);
        $upcoming = $weeks->findOrCreateWeek($weeks->upcomingWeek()['start']);

        return ScheduleWeek::query()
            ->with('lockedByUser')
            ->whereIn('id', [$current->id, $upcoming->id])
            ->orderBy('week_start_date')
            ->get();
    }

    public function lock(int $weekId, WeekRange $weeks): void
    {
        abort_unless(Auth::user()?->can('schedule.lock-week'), 403);

        $week = ScheduleWeek::query()->findOrFail($weekId);
        $weeks->lock($week, Auth::user());

        unset($this->weeks);

        Flux::toast(variant: 'success', text: __('app.schedule.locked_toast'));
    }

    public function unlock(int $weekId, WeekRange $weeks): void
    {
        abort_unless(Auth::user()?->can('schedule.lock-week'), 403);

        $week = ScheduleWeek::query()->findOrFail($weekId);
        $weeks->unlock($week);

        unset($this->weeks);

        Flux::toast(variant: 'success', text: __('app.schedule.unlocked_toast'));
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('app.schedule.weeks_title') }}</flux:heading>
        <flux:text>{{ __('app.schedule.weeks_subtitle') }}</flux:text>
    </div>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('app.schedule.day') }}</flux:table.column>
                <flux:table.column>{{ __('app.schedule.locked') }}</flux:table.column>
                <flux:table.column>{{ __('app.agents.actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->weeks as $week)
                    <flux:table.row wire:key="week-{{ $week->id }}">
                        <flux:table.cell>
                            {{ $week->week_start_date->toDateString() }}
                            —
                            {{ $week->week_end_date->toDateString() }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$week->is_locked ? 'red' : 'green'" size="sm">
                                {{ $week->is_locked ? __('app.schedule.locked') : __('app.schedule.unlocked') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($week->is_locked)
                                <flux:tooltip content="{{ __('app.schedule.unlock') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="amber"
                                        icon="lock-open"
                                        wire:click="unlock({{ $week->id }})"
                                        wire:confirm="{{ __('app.common.are_you_sure') }}"
                                    />
                                </flux:tooltip>
                            @else
                                <flux:tooltip content="{{ __('app.schedule.lock') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="red"
                                        icon="lock"
                                        wire:click="lock({{ $week->id }})"
                                        wire:confirm="{{ __('app.common.are_you_sure') }}"
                                    />
                                </flux:tooltip>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</section>

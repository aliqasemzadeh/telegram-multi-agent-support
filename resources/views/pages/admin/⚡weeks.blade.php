<?php

use App\Models\ScheduleWeek;
use App\Services\Schedule\WeekRange;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Week management')] class extends Component {
    use WithPagination;

    public function mount(WeekRange $weeks): void
    {
        abort_unless(Auth::user()?->can('schedule.lock-week'), 403);

        $weeks->findOrCreateWeek($weeks->currentWeek()['start']);
        $weeks->findOrCreateWeek($weeks->upcomingWeek()['start']);
    }

    #[Computed]
    public function weeks()
    {
        return ScheduleWeek::query()
            ->with('lockedByUser')
            ->withCount('slots')
            ->orderByDesc('week_start_date')
            ->paginate(10);
    }

    public function addWeek(WeekRange $weeks): void
    {
        abort_unless(Auth::user()?->can('schedule.lock-week'), 403);

        $weeks->addWeekAfterLatest();
        unset($this->weeks);

        Flux::toast(variant: 'success', text: __('app.schedule.week_added_toast'));
    }

    public function deleteWeek(int $weekId, WeekRange $weeks): void
    {
        abort_unless(Auth::user()?->can('schedule.lock-week'), 403);

        $week = ScheduleWeek::query()->findOrFail($weekId);

        try {
            $weeks->deleteIfEmpty($week);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: $exception->errors()['week'][0] ?? __('app.schedule.week_not_empty'));

            return;
        }

        unset($this->weeks);

        Flux::toast(variant: 'success', text: __('app.schedule.week_deleted_toast'));
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
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('app.schedule.weeks_title') }}</flux:heading>
            <flux:text>{{ __('app.schedule.weeks_subtitle') }}</flux:text>
        </div>

        <flux:button variant="primary" color="teal" icon="calendar-plus" wire:click="addWeek" class="w-full sm:w-auto">
            {{ __('app.schedule.add_week') }}
        </flux:button>
    </div>

    <flux:card>
        <flux:table :paginate="$this->weeks">
            <flux:table.columns>
                <flux:table.column>{{ __('app.schedule.week_number') }}</flux:table.column>
                <flux:table.column>{{ __('app.schedule.day') }}</flux:table.column>
                <flux:table.column>{{ __('app.schedule.locked') }}</flux:table.column>
                <flux:table.column>{{ __('app.agents.actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->weeks as $week)
                    @php
                        $weekRange = app(\App\Services\Schedule\WeekRange::class);
                    @endphp
                    <flux:table.row wire:key="week-{{ $week->id }}">
                        <flux:table.cell>
                            <flux:badge color="sky" size="sm">
                                {{ __('app.schedule.week_number_short', ['number' => $weekRange->weekNumber($week->week_start_date)]) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $weekRange->weekDateRange($week) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$week->is_locked ? 'red' : 'green'" size="sm">
                                {{ $week->is_locked ? __('app.schedule.locked') : __('app.schedule.unlocked') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                <flux:tooltip content="{{ __('app.schedule.view_coverage') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="cyan"
                                        icon="chart-bar"
                                        :href="route('admin.coverage', ['week' => $week->id])"
                                        wire:navigate
                                    />
                                </flux:tooltip>

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

                                @if ($week->slots_count === 0)
                                    <flux:tooltip content="{{ __('app.common.delete') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="rose"
                                            icon="trash"
                                            wire:click="deleteWeek({{ $week->id }})"
                                            wire:confirm="{{ __('app.common.are_you_sure') }}"
                                        />
                                    </flux:tooltip>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</section>

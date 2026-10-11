<?php

use App\Enums\SupportChannel;
use App\Models\AgentScheduleSlot;
use App\Models\ScheduleWeek;
use App\Models\User;
use App\Services\Schedule\WeekRange;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Dashboard')] class extends Component {
    use WithPagination;

    public ?int $selectedWeekId = null;

    public string $search = '';

    public function mount(WeekRange $weeks): void
    {
        $current = $weeks->findOrCreateWeek($weeks->currentWeek()['start']);
        $weeks->findOrCreateWeek($weeks->upcomingWeek()['start']);

        $this->selectedWeekId = $current->id;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function selectWeek(int $weekId): void
    {
        ScheduleWeek::query()->findOrFail($weekId);
        $this->selectedWeekId = $weekId;
        $this->resetPage();
    }

    public function updatedSelectedWeekId(mixed $value): void
    {
        $this->selectedWeekId = $value !== null && $value !== '' ? (int) $value : null;
        $this->resetPage();
    }

    #[Computed]
    public function isAdmin(): bool
    {
        return Auth::user()?->hasRole('admin') ?? false;
    }

    #[Computed]
    public function isAgent(): bool
    {
        return Auth::user()?->hasRole('agent') ?? false;
    }

    #[Computed]
    public function weeks()
    {
        $service = app(WeekRange::class);
        $ensureIds = [
            $service->findOrCreateWeek($service->currentWeek()['start'])->id,
            $service->findOrCreateWeek($service->upcomingWeek()['start'])->id,
        ];

        if ($this->isAgent) {
            $slotWeekIds = AgentScheduleSlot::query()
                ->where('user_id', Auth::id())
                ->distinct()
                ->pluck('schedule_week_id')
                ->all();

            $ids = array_values(array_unique([...$ensureIds, ...$slotWeekIds]));

            return ScheduleWeek::query()
                ->whereIn('id', $ids)
                ->orderByDesc('week_start_date')
                ->get();
        }

        return ScheduleWeek::query()
            ->orderByDesc('week_start_date')
            ->get();
    }

    /**
     * @return array{phone: int, chat: int, total: int}
     */
    #[Computed]
    public function stats(): array
    {
        if (! $this->isAdmin && ! $this->isAgent) {
            return ['phone' => 0, 'chat' => 0, 'total' => 0];
        }

        $query = AgentScheduleSlot::query()
            ->where('schedule_week_id', $this->selectedWeekId);

        if (! $this->isAdmin) {
            $query->where('user_id', Auth::id());
        }

        $rows = $query
            ->selectRaw('channel, count(*) as total')
            ->groupBy('channel')
            ->pluck('total', 'channel');

        $phone = (int) ($rows[SupportChannel::Phone->value] ?? 0);
        $chat = (int) ($rows[SupportChannel::Chat->value] ?? 0);

        return [
            'phone' => $phone,
            'chat' => $chat,
            'total' => $phone + $chat,
        ];
    }

    #[Computed]
    public function agents()
    {
        abort_unless($this->isAdmin, 403);

        $weekId = $this->selectedWeekId;

        return User::role('agent')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->withCount([
                'scheduleSlots as phone_hours' => fn ($q) => $q
                    ->where('schedule_week_id', $weekId)
                    ->where('channel', SupportChannel::Phone->value),
                'scheduleSlots as chat_hours' => fn ($q) => $q
                    ->where('schedule_week_id', $weekId)
                    ->where('channel', SupportChannel::Chat->value),
            ])
            ->orderBy('name')
            ->paginate(10);
    }

    public function weekLabel(ScheduleWeek $week): string
    {
        $service = app(WeekRange::class);

        return $service->dayLabel($week->week_start_date).' — '.$service->dayLabel($week->week_end_date);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('app.dashboard.title') }}</flux:heading>
        <flux:text>
            @if ($this->isAdmin)
                {{ __('app.dashboard.subtitle_admin') }}
            @elseif ($this->isAgent)
                {{ __('app.dashboard.subtitle_agent') }}
            @endif
        </flux:text>
    </div>

    @if ($this->isAdmin || $this->isAgent)
        <div class="grid gap-4 md:grid-cols-3">
            <flux:card class="space-y-2">
                <flux:text class="text-sm">{{ __('app.dashboard.phone_hours') }}</flux:text>
                <flux:heading size="xl" class="text-sky-600 dark:text-sky-400">{{ $this->stats['phone'] }}</flux:heading>
            </flux:card>

            <flux:card class="space-y-2">
                <flux:text class="text-sm">{{ __('app.dashboard.chat_hours') }}</flux:text>
                <flux:heading size="xl" class="text-emerald-600 dark:text-emerald-400">{{ $this->stats['chat'] }}</flux:heading>
            </flux:card>

            <flux:card class="space-y-2">
                <flux:text class="text-sm">{{ __('app.dashboard.total_hours') }}</flux:text>
                <flux:heading size="xl" class="text-orange-600 dark:text-orange-400">{{ $this->stats['total'] }}</flux:heading>
            </flux:card>
        </div>

        <flux:card class="space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <flux:heading size="lg">{{ __('app.dashboard.weeks') }}</flux:heading>

                <div class="w-full sm:max-w-md">
                    <flux:select
                        wire:model.live="selectedWeekId"
                        :label="__('app.dashboard.select_week')"
                        searchable
                    >
                        @foreach ($this->weeks as $week)
                            <flux:select.option value="{{ $week->id }}">
                                {{ $this->weekLabel($week) }}
                                @if ($week->is_locked)
                                    ({{ __('app.schedule.locked') }})
                                @endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            @if ($this->weeks->isEmpty())
                <flux:text>{{ __('app.dashboard.no_weeks') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('app.schedule.day') }}</flux:table.column>
                        <flux:table.column>{{ __('app.schedule.locked') }}</flux:table.column>
                        @if ($this->isAgent)
                            <flux:table.column>{{ __('app.agents.actions') }}</flux:table.column>
                        @endif
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->weeks as $week)
                            <flux:table.row
                                wire:key="dash-week-{{ $week->id }}"
                                class="{{ $week->id === $this->selectedWeekId ? 'bg-zinc-50 dark:bg-zinc-900/50' : '' }}"
                            >
                                <flux:table.cell>
                                    <button
                                        type="button"
                                        wire:click="selectWeek({{ $week->id }})"
                                        class="text-start hover:underline"
                                    >
                                        {{ $this->weekLabel($week) }}
                                    </button>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$week->is_locked ? 'red' : 'green'" size="sm">
                                        {{ $week->is_locked ? __('app.schedule.locked') : __('app.schedule.unlocked') }}
                                    </flux:badge>
                                </flux:table.cell>
                                @if ($this->isAgent)
                                    <flux:table.cell>
                                        <flux:tooltip content="{{ __('app.dashboard.enter_week') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="teal"
                                                icon="calendar"
                                                :href="route('user.week', ['week' => $week->id])"
                                                wire:navigate
                                            />
                                        </flux:tooltip>
                                    </flux:table.cell>
                                @endif
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif

    @if ($this->isAdmin)
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('app.dashboard.agents_hours') }}</flux:heading>

            <flux:input
                wire:model.live.debounce.300ms="search"
                :placeholder="__('app.agents.search')"
                icon="magnifying-glass"
            />

            <flux:table :paginate="$this->agents">
                <flux:table.columns>
                    <flux:table.column>{{ __('app.agents.name') }}</flux:table.column>
                    <flux:table.column>{{ __('app.agents.email') }}</flux:table.column>
                    <flux:table.column>{{ __('app.dashboard.phone_hours') }}</flux:table.column>
                    <flux:table.column>{{ __('app.dashboard.chat_hours') }}</flux:table.column>
                    <flux:table.column>{{ __('app.dashboard.total_hours') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->agents as $agent)
                        <flux:table.row wire:key="dash-agent-{{ $agent->id }}">
                            <flux:table.cell>{{ $agent->name }}</flux:table.cell>
                            <flux:table.cell>{{ $agent->email }}</flux:table.cell>
                            <flux:table.cell>{{ $agent->phone_hours }}</flux:table.cell>
                            <flux:table.cell>{{ $agent->chat_hours }}</flux:table.cell>
                            <flux:table.cell>{{ $agent->phone_hours + $agent->chat_hours }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">{{ __('app.dashboard.no_agents') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</section>

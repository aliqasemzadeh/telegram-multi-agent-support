<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        @if (session()->has(\App\Services\Auth\ImpersonationService::SESSION_KEY))
            <div class="mb-6 flex flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950 sm:flex-row sm:items-center sm:justify-between dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-100">
                <div class="flex items-center gap-3">
                    <flux:icon.user class="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div class="text-sm font-medium">
                        {{ __('app.impersonation.banner', ['name' => auth()->user()?->name]) }}
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:button
                        size="xs"
                        variant="primary"
                        color="teal"
                        icon="calendar"
                        :href="route('user.week')"
                        wire:navigate
                    >
                        {{ __('app.nav.week_plan') }}
                    </flux:button>

                    <flux:button
                        size="xs"
                        variant="primary"
                        color="sky"
                        icon="clock"
                        :href="route('user.index')"
                        wire:navigate
                    >
                        {{ __('app.nav.attendance') }}
                    </flux:button>

                    <form method="POST" action="{{ route('impersonation.leave') }}">
                        @csrf
                        <flux:button
                            type="submit"
                            size="xs"
                            variant="primary"
                            color="amber"
                            icon="arrow-uturn-left"
                        >
                            {{ __('app.impersonation.leave') }}
                        </flux:button>
                    </form>
                </div>
            </div>
        @endif

        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>

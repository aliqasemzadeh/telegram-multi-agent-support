<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('app.nav.platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('app.nav.dashboard') }}
                    </flux:sidebar.item>

                    @can('schedule.check-in')
                        <flux:sidebar.item icon="calendar-days" :href="route('user.index')" :current="request()->routeIs('user.index')" wire:navigate>
                            {{ __('app.nav.attendance') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('schedule.manage-own')
                        <flux:sidebar.item icon="calendar" :href="route('user.week')" :current="request()->routeIs('user.week')" wire:navigate>
                            {{ __('app.nav.week_plan') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                @canany(['schedule.view-coverage', 'schedule.lock-week', 'agents.manage'])
                    <flux:sidebar.group :heading="__('app.nav.admin')" class="grid">
                        @can('schedule.view-coverage')
                            <flux:sidebar.item icon="chart-bar" :href="route('admin.coverage')" :current="request()->routeIs('admin.coverage')" wire:navigate>
                                {{ __('app.nav.coverage') }}
                            </flux:sidebar.item>
                        @endcan

                        @can('schedule.lock-week')
                            <flux:sidebar.item icon="lock" :href="route('admin.weeks')" :current="request()->routeIs('admin.weeks')" wire:navigate>
                                {{ __('app.nav.weeks') }}
                            </flux:sidebar.item>
                        @endcan

                        @can('agents.manage')
                            <flux:sidebar.item icon="users" :href="route('admin.agents')" :current="request()->routeIs('admin.agents')" wire:navigate>
                                {{ __('app.nav.agents') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>

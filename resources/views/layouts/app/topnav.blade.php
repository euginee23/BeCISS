<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-950 antialiased">
        {{-- Decorative Background --}}
        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-0 right-0 w-64 sm:w-96 h-64 sm:h-96 bg-emerald-200 dark:bg-emerald-900/20 rounded-full blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-64 sm:w-96 h-64 sm:h-96 bg-lime-200 dark:bg-lime-900/20 rounded-full blur-3xl opacity-20 translate-y-1/2 -translate-x-1/2"></div>
        </div>

        {{-- Top Navigation --}}
        <nav class="sticky top-0 z-50 border-b bg-white/80 dark:bg-zinc-900/80 backdrop-blur-lg border-zinc-200 dark:border-zinc-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    {{-- Logo --}}
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
                        <div class="flex items-center justify-center size-10 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 shadow-lg shadow-emerald-500/20">
                            <x-app-logo-icon class="size-6 text-white" />
                        </div>
                        <div>
                            <span class="text-lg font-bold text-zinc-900 dark:text-white">BeCISS</span>
                            <span class="hidden sm:inline text-xs text-zinc-500 dark:text-zinc-400 ml-2">Barangay e-Connect</span>
                        </div>
                    </a>

                    {{-- Desktop Nav Links --}}
                    <div class="hidden md:flex items-center gap-1">
                        <a href="{{ route('dashboard') }}" wire:navigate
                           class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30' : 'text-zinc-600 dark:text-zinc-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                            {{ __('Dashboard') }}
                        </a>
                        <a href="{{ route('resident.certificates.index') }}" wire:navigate
                           class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('resident.certificates.*') ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30' : 'text-zinc-600 dark:text-zinc-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                            {{ __('My Certificates') }}
                        </a>
                        <a href="{{ route('resident.appointments.index') }}" wire:navigate
                           class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('resident.appointments.*') ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30' : 'text-zinc-600 dark:text-zinc-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                            {{ __('My Appointments') }}
                        </a>
                    </div>

                    {{-- User Menu --}}
                    <div class="flex items-center gap-3">
                        {{-- Mobile Menu Button --}}
                        <flux:modal.trigger name="mobile-nav" class="md:hidden">
                            <flux:button variant="ghost" icon="bars-3" size="sm" />
                        </flux:modal.trigger>

                        {{-- Notification Bell --}}
                        <livewire:notification-bell />

                        {{-- Profile Dropdown --}}
                        <flux:dropdown position="bottom" align="end">
                            <flux:profile
                                :initials="auth()->user()->initials()"
                                icon-trailing="chevron-down"
                            />
                            <flux:menu>
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
                                <flux:menu.separator />
                                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                                    {{ __('Settings') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <form method="POST" action="{{ route('logout') }}" class="w-full">
                                    @csrf
                                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                                        {{ __('Log out') }}
                                    </flux:menu.item>
                                </form>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            </div>
        </nav>

        {{-- Mobile Navigation Modal --}}
        <flux:modal name="mobile-nav" class="md:hidden" variant="flyout" position="left">
            <div class="flex flex-col gap-1 p-2">
                <flux:heading size="sm" class="px-3 py-2 text-zinc-400">{{ __('Navigation') }}</flux:heading>
                <a href="{{ route('dashboard') }}" wire:navigate
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                    <flux:icon name="home" class="size-5" />
                    {{ __('Dashboard') }}
                </a>
                <a href="{{ route('resident.certificates.index') }}" wire:navigate
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('resident.certificates.*') ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                    <flux:icon name="document-text" class="size-5" />
                    {{ __('My Certificates') }}
                </a>
                <a href="{{ route('resident.appointments.index') }}" wire:navigate
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('resident.appointments.*') ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                    <flux:icon name="calendar" class="size-5" />
                    {{ __('My Appointments') }}
                </a>
                <a href="{{ route('resident.notifications') }}" wire:navigate
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('resident.notifications') ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                    <flux:icon name="bell" class="size-5" />
                    {{ __('Notifications') }}
                </a>
                <flux:separator class="my-2" />
                <a href="{{ route('profile.edit') }}" wire:navigate
                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors">
                    <flux:icon name="cog" class="size-5" />
                    {{ __('Settings') }}
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 px-3 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors cursor-pointer">
                        <flux:icon name="arrow-right-start-on-rectangle" class="size-5" />
                        {{ __('Log out') }}
                    </button>
                </form>
            </div>
        </flux:modal>

        {{-- Main Content --}}
        <main class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            {{ $slot }}
        </main>

        @fluxScripts
    </body>
</html>

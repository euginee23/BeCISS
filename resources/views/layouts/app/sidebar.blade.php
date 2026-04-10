<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-emerald-100 bg-emerald-50/50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if(auth()->user()->hasRole(['admin', 'staff']))
                @php
                    $pendingResidents    = \App\Models\Resident::pending()->count();
                    $pendingCertificates = \App\Models\Certificate::where('status', 'pending')->count();
                    $scheduledAppointments = \App\Models\Appointment::where('status', 'scheduled')->count();
                    $pendingBlotters = \App\Models\Blotter::where('status', 'pending')->count();
                @endphp
                <flux:sidebar.group :heading="__('Management')" class="grid">
                    @if(auth()->user()->hasPermission('residents'))
                    <flux:sidebar.item icon="users" :href="route('residents.index')" :current="request()->routeIs('residents.*')" wire:navigate :badge="$pendingResidents ?: null" badge:color="amber">
                        {{ __('Residents') }}
                    </flux:sidebar.item>
                    @endif
                    @if(auth()->user()->hasPermission('certificates'))
                    <flux:sidebar.item icon="document-text" :href="route('certificates.index')" :current="request()->routeIs('certificates.*')" wire:navigate :badge="$pendingCertificates ?: null" badge:color="amber">
                        {{ __('Certificates') }}
                    </flux:sidebar.item>
                    @endif
                    @if(auth()->user()->hasPermission('appointments'))
                    <flux:sidebar.item icon="calendar" :href="route('appointments.index')" :current="request()->routeIs('appointments.*')" wire:navigate :badge="$scheduledAppointments ?: null" badge:color="amber">
                        {{ __('Appointments') }}
                    </flux:sidebar.item>
                    @endif
                    @if(auth()->user()->hasPermission('blotters'))
                    <flux:sidebar.item icon="shield-exclamation" :href="route('blotters.index')" :current="request()->routeIs('blotters.*')" wire:navigate :badge="$pendingBlotters ?: null" badge:color="amber">
                        {{ __('Blotters') }}
                    </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
                @endif

                @if(auth()->user()->isAdmin())
                <flux:sidebar.group :heading="__('Administration')" class="grid">
                    <flux:sidebar.item icon="user-group" :href="route('staff.index')" :current="request()->routeIs('staff.*')" wire:navigate>
                        {{ __('Staff') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="building-office-2" :href="route('admin.settings.barangay')" :current="request()->routeIs('admin.settings.barangay')" wire:navigate>
                        {{ __('Barangay Settings') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="banknotes" :href="route('admin.settings.service-fees')" :current="request()->routeIs('admin.settings.service-fees')" wire:navigate>
                        {{ __('Service Fees') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endif

                @if(auth()->user()->isResident())
                @php
                    $myResidentRecord = auth()->user()->resident;
                    $myPendingCertificates  = $myResidentRecord ? \App\Models\Certificate::where('resident_id', $myResidentRecord->id)->where('status', 'pending')->count() : 0;
                    $myScheduledAppointments = $myResidentRecord ? \App\Models\Appointment::where('resident_id', $myResidentRecord->id)->where('status', 'scheduled')->count() : 0;
                    $myPendingBlotters = $myResidentRecord ? \App\Models\Blotter::where('resident_id', $myResidentRecord->id)->where('status', 'pending')->count() : 0;
                @endphp
                <flux:sidebar.group :heading="__('My Services')" class="grid">
                    <flux:sidebar.item icon="document-text" :href="route('resident.certificates.index')" :current="request()->routeIs('resident.certificates.*')" wire:navigate :badge="$myPendingCertificates ?: null" badge:color="amber">
                        {{ __('My Certificates') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="calendar" :href="route('resident.appointments.index')" :current="request()->routeIs('resident.appointments.*')" wire:navigate :badge="$myScheduledAppointments ?: null" badge:color="amber">
                        {{ __('My Appointments') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="shield-exclamation" :href="route('resident.blotters.index')" :current="request()->routeIs('resident.blotters.*')" wire:navigate :badge="$myPendingBlotters ?: null" badge:color="amber">
                        {{ __('My Blotters') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endif
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

        @fluxScripts
    </body>
</html>

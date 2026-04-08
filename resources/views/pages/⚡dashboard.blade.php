<?php

use App\Models\Appointment;
use App\Models\Certificate;
use App\Models\Resident;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Dashboard')]
#[Layout('layouts::app')]
class extends Component
{
    #[Computed]
    public function totalResidents(): int
    {
        return Resident::approved()->count();
    }

    #[Computed]
    public function pendingRegistrations(): int
    {
        return Resident::pending()->count();
    }

    #[Computed]
    public function pendingCertificates(): int
    {
        $user = auth()->user();

        if ($user->isResident()) {
            return $user->resident?->certificates()->whereIn('status', ['pending', 'processing'])->count() ?? 0;
        }

        return Certificate::query()->whereIn('status', ['pending', 'processing'])->count();
    }

    #[Computed]
    public function todaysAppointments(): int
    {
        $user = auth()->user();

        if ($user->isResident()) {
            return $user->resident?->appointments()->today()->count() ?? 0;
        }

        return Appointment::query()->today()->count();
    }

    #[Computed]
    public function completedThisMonth(): int
    {
        return Certificate::query()
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->count();
    }

    #[Computed]
    public function recentCertificates()
    {
        $user = auth()->user();

        if ($user->isResident()) {
            return $user->resident?->certificates()->latest()->limit(5)->get() ?? collect();
        }

        return Certificate::query()->with('resident')->latest()->limit(5)->get();
    }

    #[Computed]
    public function upcomingAppointments()
    {
        $user = auth()->user();

        if ($user->isResident()) {
            return $user->resident?->appointments()->upcoming()->limit(5)->get() ?? collect();
        }

        return Appointment::query()->with('resident')->upcoming()->limit(5)->get();
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Welcome Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ auth()->user()->isResident() ? 'My Dashboard' : 'Dashboard' }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">
                Welcome back, <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ auth()->user()->name }}</span>.
                @if(auth()->user()->isAdmin())
                    Here's an overview of the barangay system.
                @elseif(auth()->user()->isStaff())
                    Here's today's overview.
                @else
                    Here's a summary of your services.
                @endif
            </flux:text>
        </div>

        {{-- Quick actions --}}
        @if(auth()->user()->isAdmin() || auth()->user()->isStaff())
            <div class="hidden sm:flex items-center gap-2">
                <flux:button :href="route('residents.create')" variant="outline" size="sm" icon="user-plus" wire:navigate>
                    New Resident
                </flux:button>
                <flux:button :href="route('appointments.create')" variant="primary" size="sm" icon="calendar" wire:navigate>
                    New Appointment
                </flux:button>
            </div>
        @elseif(auth()->user()->isResident() && auth()->user()->resident?->isApproved())
            <div class="hidden sm:flex items-center gap-2">
                <flux:button :href="route('resident.certificates.index')" variant="outline" size="sm" icon="document-text" wire:navigate>
                    My Certificates
                </flux:button>
                <flux:button :href="route('resident.appointments.index')" variant="primary" size="sm" icon="calendar" wire:navigate>
                    My Appointments
                </flux:button>
            </div>
        @endif
    </div>

    {{-- ===== ADMIN / STAFF STATS ===== --}}
    @if(auth()->user()->isAdmin() || auth()->user()->isStaff())

        @if($this->pendingRegistrations > 0)
            <a href="{{ route('residents.index', ['tab' => 'pending']) }}" wire:navigate class="block rounded-xl border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-4 hover:bg-amber-100 dark:hover:bg-amber-900/30 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-lg bg-amber-100 dark:bg-amber-800/50 flex items-center justify-center shrink-0">
                        <flux:icon name="user-plus" class="size-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading class="text-amber-900 dark:text-amber-200">{{ $this->pendingRegistrations }} Pending {{ Str::plural('Registration', $this->pendingRegistrations) }}</flux:heading>
                        <flux:text class="text-amber-700 dark:text-amber-300 text-sm">New residents are awaiting approval. Click to review.</flux:text>
                    </div>
                    <flux:icon name="chevron-right" class="size-5 text-amber-500" />
                </div>
            </a>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Total Residents --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Residents</flux:text>
                    <div class="size-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                        <flux:icon name="users" class="size-4 text-emerald-600 dark:text-emerald-400" />
                    </div>
                </div>
                <flux:heading size="2xl" class="text-zinc-900 dark:text-white">{{ number_format($this->totalResidents) }}</flux:heading>
                <flux:text class="text-xs text-zinc-400">Registered in the barangay</flux:text>
            </div>

            {{-- Pending Certificates --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Pending Requests</flux:text>
                    <div class="size-9 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                        <flux:icon name="document-text" class="size-4 text-amber-600 dark:text-amber-400" />
                    </div>
                </div>
                <flux:heading size="2xl" class="text-zinc-900 dark:text-white">{{ number_format($this->pendingCertificates) }}</flux:heading>
                <flux:text class="text-xs text-zinc-400">Certificates awaiting action</flux:text>
            </div>

            {{-- Today's Appointments --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Today's Appointments</flux:text>
                    <div class="size-9 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <flux:icon name="calendar" class="size-4 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
                <flux:heading size="2xl" class="text-zinc-900 dark:text-white">{{ number_format($this->todaysAppointments) }}</flux:heading>
                <flux:text class="text-xs text-zinc-400">Scheduled for today</flux:text>
            </div>

            {{-- Completed This Month --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Completed This Month</flux:text>
                    <div class="size-9 rounded-lg bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center">
                        <flux:icon name="check-circle" class="size-4 text-teal-600 dark:text-teal-400" />
                    </div>
                </div>
                <flux:heading size="2xl" class="text-zinc-900 dark:text-white">{{ number_format($this->completedThisMonth) }}</flux:heading>
                <flux:text class="text-xs text-zinc-400">Certificates issued</flux:text>
            </div>
        </div>

        {{-- Recent Data Tables --}}
        <div class="grid lg:grid-cols-2 gap-6">

            {{-- Recent Certificate Requests --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 flex flex-col">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                    <flux:heading>Recent Certificate Requests</flux:heading>
                    <flux:button :href="route('certificates.index')" variant="ghost" size="sm" wire:navigate>View all</flux:button>
                </div>

                @if($this->recentCertificates->isEmpty())
                    <div class="flex items-center justify-center py-12 text-zinc-400">
                        <div class="text-center">
                            <flux:icon name="document-text" class="size-10 mx-auto mb-2 opacity-30" />
                            <flux:text>No certificate requests yet</flux:text>
                        </div>
                    </div>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->recentCertificates as $cert)
                            <div class="flex items-center gap-3 px-5 py-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ $cert->resident?->full_name ?? 'Unknown' }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ $cert->type_label }} &middot; {{ $cert->created_at->diffForHumans() }}</p>
                                </div>
                                <flux:badge :color="match($cert->status) { 'pending' => 'yellow', 'processing' => 'blue', 'completed' => 'green', 'rejected' => 'red', default => 'zinc' }" size="sm">
                                    {{ $cert->status_label }}
                                </flux:badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Upcoming Appointments --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 flex flex-col">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                    <flux:heading>Upcoming Appointments</flux:heading>
                    <flux:button :href="route('appointments.index')" variant="ghost" size="sm" wire:navigate>View all</flux:button>
                </div>

                @if($this->upcomingAppointments->isEmpty())
                    <div class="flex items-center justify-center py-12 text-zinc-400">
                        <div class="text-center">
                            <flux:icon name="calendar" class="size-10 mx-auto mb-2 opacity-30" />
                            <flux:text>No upcoming appointments</flux:text>
                        </div>
                    </div>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->upcomingAppointments as $appt)
                            <div class="flex items-center gap-3 px-5 py-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ $appt->resident?->full_name ?? 'Unknown' }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ $appt->service_type_label }} &middot; {{ $appt->appointment_date->format('M d') }} at {{ $appt->appointment_time->format('h:i A') }}</p>
                                </div>
                                <flux:badge :color="match($appt->status) { 'scheduled' => 'zinc', 'confirmed' => 'blue', 'in_progress' => 'yellow', default => 'zinc' }" size="sm">
                                    {{ $appt->status_label }}
                                </flux:badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    {{-- ===== RESIDENT DASHBOARD ===== --}}
    @elseif(auth()->user()->isResident())

        @php $resident = auth()->user()->resident; @endphp

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 gap-4">
                <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 transition-all hover:shadow-lg hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-50 to-transparent dark:from-amber-950/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Pending Certificates') }}</flux:text>
                            <div class="size-9 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                <flux:icon name="document-text" class="size-4 text-amber-600 dark:text-amber-400" />
                            </div>
                        </div>
                        <flux:heading size="2xl" class="text-zinc-900 dark:text-white">{{ number_format($this->pendingCertificates) }}</flux:heading>
                        <flux:text class="text-xs text-zinc-400">{{ __('Awaiting processing') }}</flux:text>
                    </div>
                </div>

                <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 transition-all hover:shadow-lg hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-transparent dark:from-blue-950/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __("Today's Appointments") }}</flux:text>
                            <div class="size-9 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <flux:icon name="calendar" class="size-4 text-blue-600 dark:text-blue-400" />
                            </div>
                        </div>
                        <flux:heading size="2xl" class="text-zinc-900 dark:text-white">{{ number_format($this->todaysAppointments) }}</flux:heading>
                        <flux:text class="text-xs text-zinc-400">{{ __('Scheduled for today') }}</flux:text>
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="grid grid-cols-2 gap-4">
                <a href="{{ route('resident.certificates.index') }}" wire:navigate class="group relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-transparent dark:from-emerald-950/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative flex flex-col items-center text-center gap-3">
                        <div class="size-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center group-hover:scale-110 transition-transform">
                            <flux:icon name="document-text" class="size-6 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <flux:heading size="sm">{{ __('My Certificates') }}</flux:heading>
                        <flux:text class="text-xs text-zinc-500">{{ __('View & request certificates') }}</flux:text>
                    </div>
                </a>
                <a href="{{ route('resident.appointments.index') }}" wire:navigate class="group relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-teal-50 to-transparent dark:from-teal-950/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative flex flex-col items-center text-center gap-3">
                        <div class="size-12 rounded-xl bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center group-hover:scale-110 transition-transform">
                            <flux:icon name="calendar" class="size-6 text-teal-600 dark:text-teal-400" />
                        </div>
                        <flux:heading size="sm">{{ __('My Appointments') }}</flux:heading>
                        <flux:text class="text-xs text-zinc-500">{{ __('Schedule & manage appointments') }}</flux:text>
                    </div>
                </a>
            </div>

            {{-- Recent Certificates --}}
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <flux:heading>{{ __('Recent Certificates') }}</flux:heading>
                    <flux:button :href="route('resident.certificates.index')" variant="ghost" size="sm" wire:navigate>{{ __('View all') }}</flux:button>
                </div>

                @if($this->recentCertificates->isEmpty())
                    <div class="flex flex-col items-center justify-center py-10 text-zinc-400">
                        <flux:icon name="document-text" class="size-10 mb-2 opacity-30" />
                        <flux:text>{{ __('No certificate requests yet') }}</flux:text>
                    </div>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->recentCertificates as $cert)
                            <div wire:key="cert-{{ $cert->id }}" class="flex items-center gap-3 px-5 py-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ $cert->type_label }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ $cert->purpose }} &middot; {{ $cert->created_at->diffForHumans() }}</p>
                                </div>
                                <flux:badge :color="match($cert->status) { 'pending' => 'yellow', 'processing' => 'blue', 'ready_for_pickup' => 'lime', 'completed' => 'green', 'rejected' => 'red', default => 'zinc' }" size="sm">
                                    {{ $cert->status_label }}
                                </flux:badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Upcoming Appointments --}}
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <flux:heading>{{ __('Upcoming Appointments') }}</flux:heading>
                    <flux:button :href="route('resident.appointments.index')" variant="ghost" size="sm" wire:navigate>{{ __('View all') }}</flux:button>
                </div>

                @if($this->upcomingAppointments->isEmpty())
                    <div class="flex flex-col items-center justify-center py-10 text-zinc-400">
                        <flux:icon name="calendar" class="size-10 mb-2 opacity-30" />
                        <flux:text>{{ __('No upcoming appointments') }}</flux:text>
                    </div>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->upcomingAppointments as $appt)
                            <div wire:key="appt-{{ $appt->id }}" class="flex items-center gap-3 px-5 py-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ $appt->service_type_label }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $appt->appointment_date->format('F d, Y') }} at {{ $appt->appointment_time->format('h:i A') }}</p>
                                </div>
                                <flux:badge :color="match($appt->status) { 'scheduled' => 'zinc', 'confirmed' => 'blue', 'in_progress' => 'yellow', default => 'zinc' }" size="sm">
                                    {{ $appt->status_label }}
                                </flux:badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
    @endif
</div>

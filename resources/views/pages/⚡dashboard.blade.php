<?php

use App\Models\Appointment;
use App\Models\Certificate;
use App\Models\Resident;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new
#[Title('Dashboard')]
#[Layout('layouts::app')]
class extends Component
{
    // Resident profile form fields
    #[Validate('required|string|max:255')]
    public string $first_name = '';

    #[Validate('nullable|string|max:255')]
    public ?string $middle_name = '';

    #[Validate('required|string|max:255')]
    public string $last_name = '';

    #[Validate('nullable|string|max:10')]
    public ?string $suffix = '';

    #[Validate('required|date|before:today')]
    public string $birthdate = '';

    #[Validate('required|in:male,female')]
    public string $gender = '';

    #[Validate('required|in:single,married,widowed,separated,divorced')]
    public string $civil_status = 'single';

    #[Validate('required|string|max:20')]
    public string $contact_number = '';

    #[Validate('required|string|max:500')]
    public string $address = '';

    #[Validate('nullable|string|max:100')]
    public ?string $purok = '';

    public bool $showProfileModal = false;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->isResident()) {
            $resident = $user->resident;

            // Auto-open modal for new residents (no profile) or rejected residents
            if (! $resident || $resident->isRejected()) {
                $this->showProfileModal = true;

                if ($resident?->isRejected()) {
                    $this->first_name = $resident->first_name;
                    $this->middle_name = $resident->middle_name ?? '';
                    $this->last_name = $resident->last_name;
                    $this->suffix = $resident->suffix ?? '';
                    $this->birthdate = $resident->birthdate->format('Y-m-d');
                    $this->gender = $resident->gender;
                    $this->civil_status = $resident->civil_status;
                    $this->contact_number = $resident->contact_number ?? '';
                    $this->address = $resident->address;
                    $this->purok = $resident->purok ?? '';
                }
            }
        }
    }

    public function submitProfile(): void
    {
        $this->validate();

        $user = auth()->user();
        $resident = $user->resident;

        $data = [
            'user_id' => $user->id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name ?: null,
            'last_name' => $this->last_name,
            'suffix' => $this->suffix ?: null,
            'birthdate' => $this->birthdate,
            'gender' => $this->gender,
            'civil_status' => $this->civil_status,
            'contact_number' => $this->contact_number,
            'address' => $this->address,
            'purok' => $this->purok ?: null,
            'status' => 'pending',
            'rejection_reason' => null,
        ];

        if ($resident) {
            $resident->update($data);
        } else {
            Resident::create($data);
        }

        $this->showProfileModal = false;

        $this->redirect(route('pending-approval'), navigate: true);
    }

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

        {{-- Rejection banner --}}
        @if($resident?->isRejected())
            <div class="rounded-2xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-5">
                <div class="flex items-start gap-4">
                    <div class="size-10 rounded-xl bg-red-100 dark:bg-red-800/50 flex items-center justify-center shrink-0">
                        <flux:icon name="x-circle" class="size-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading class="text-red-900 dark:text-red-200">Registration Not Approved</flux:heading>
                        <flux:text class="text-red-700 dark:text-red-300 mt-1">
                            {{ $resident->rejection_reason }}
                        </flux:text>
                        <flux:button variant="primary" size="sm" class="mt-3" wire:click="$set('showProfileModal', true)">
                            Update & Resubmit
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif

        @if(! $resident || $resident->isRejected())
            {{-- Welcome card for new/rejected residents --}}
            <div class="text-center py-8">
                <div class="inline-flex items-center justify-center size-20 rounded-2xl bg-gradient-to-br from-emerald-100 to-emerald-200 dark:from-emerald-900/30 dark:to-emerald-800/20 mb-6">
                    <flux:icon name="user-plus" class="size-10 text-emerald-600 dark:text-emerald-400" />
                </div>
                <flux:heading size="xl" class="text-zinc-900 dark:text-white">
                    {{ __('Welcome to BeCISS!') }}
                </flux:heading>
                <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400 max-w-md mx-auto">
                    {{ __('Complete your resident profile to get started with barangay services like certificate requests and appointment scheduling.') }}
                </flux:text>
                @if(! $resident)
                    <flux:button variant="primary" class="mt-6" wire:click="$set('showProfileModal', true)">
                        {{ __('Complete My Profile') }}
                    </flux:button>
                @endif
            </div>
        @else
            {{-- ===== APPROVED RESIDENT — Stats & Recent Activity ===== --}}

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

        {{-- Profile Completion Modal --}}
        <flux:modal wire:model="showProfileModal" class="max-w-lg" :closable="auth()->user()->resident?->isRejected() ?? true">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ auth()->user()->resident?->isRejected() ? __('Update Your Information') : __('Complete Your Profile') }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-500">
                        {{ __('Please provide your personal information for barangay registration.') }}
                    </flux:text>
                </div>

                <form wire:submit="submitProfile" class="space-y-4">
                    {{-- Name Fields --}}
                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>{{ __('First Name') }}</flux:label>
                            <flux:input wire:model="first_name" required />
                            <flux:error name="first_name" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Last Name') }}</flux:label>
                            <flux:input wire:model="last_name" required />
                            <flux:error name="last_name" />
                        </flux:field>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>{{ __('Middle Name') }}</flux:label>
                            <flux:input wire:model="middle_name" placeholder="{{ __('Optional') }}" />
                            <flux:error name="middle_name" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Suffix') }}</flux:label>
                            <flux:input wire:model="suffix" placeholder="{{ __('Jr., Sr., III') }}" />
                            <flux:error name="suffix" />
                        </flux:field>
                    </div>

                    {{-- Personal Info --}}
                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>{{ __('Birthdate') }}</flux:label>
                            <flux:input type="date" wire:model="birthdate" required />
                            <flux:error name="birthdate" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Gender') }}</flux:label>
                            <flux:select wire:model="gender" required>
                                <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                                <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                                <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                            </flux:select>
                            <flux:error name="gender" />
                        </flux:field>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>{{ __('Civil Status') }}</flux:label>
                            <flux:select wire:model="civil_status" required>
                                <flux:select.option value="single">{{ __('Single') }}</flux:select.option>
                                <flux:select.option value="married">{{ __('Married') }}</flux:select.option>
                                <flux:select.option value="widowed">{{ __('Widowed') }}</flux:select.option>
                                <flux:select.option value="separated">{{ __('Separated') }}</flux:select.option>
                                <flux:select.option value="divorced">{{ __('Divorced') }}</flux:select.option>
                            </flux:select>
                            <flux:error name="civil_status" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Contact Number') }}</flux:label>
                            <flux:input wire:model="contact_number" required placeholder="09XX XXX XXXX" />
                            <flux:error name="contact_number" />
                        </flux:field>
                    </div>

                    {{-- Address --}}
                    <flux:field>
                        <flux:label>{{ __('Address') }}</flux:label>
                        <flux:textarea wire:model="address" required rows="2" placeholder="{{ __('Street, Barangay, City/Municipality') }}" />
                        <flux:error name="address" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Purok / Zone') }}</flux:label>
                        <flux:input wire:model="purok" placeholder="{{ __('Optional') }}" />
                        <flux:error name="purok" />
                    </flux:field>

                    {{-- Submit --}}
                    <div class="flex justify-end gap-2 pt-2">
                        <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="submitProfile">{{ __('Submit for Approval') }}</span>
                            <span wire:loading wire:target="submitProfile">{{ __('Submitting...') }}</span>
                        </flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

    @endif
</div>

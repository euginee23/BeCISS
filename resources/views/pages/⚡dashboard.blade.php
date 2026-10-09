<?php

use App\Models\Appointment;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Payment;
use App\Models\Resident;
use Carbon\CarbonInterface;
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
            return $user->resident?->certificates()->whereIn('status', ['pending', 'awaiting_payment', 'processing'])->count() ?? 0;
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
            return $user->resident?->certificates()->with('certificateType')->latest()->limit(5)->get() ?? collect();
        }

        return Certificate::query()->with('resident', 'certificateType')->latest()->limit(5)->get();
    }

    #[Computed]
    public function upcomingAppointments()
    {
        $user = auth()->user();

        if ($user->isResident()) {
            return $user->resident?->appointments()->upcoming()->limit(5)->get() ?? collect();
        }

        return Appointment::query()->with('resident', 'service')->upcoming()->limit(5)->get();
    }

    /**
     * @return array{today: float, month: float, awaiting_count: int, awaiting_amount: float}
     */
    #[Computed]
    public function collections(): array
    {
        $awaiting = Certificate::query()->where('status', 'awaiting_payment');

        return [
            'today' => (float) Payment::query()->paidBetween(now(), now())->sum('amount'),
            'month' => (float) Payment::query()->paidBetween(now()->startOfMonth(), now())->sum('amount'),
            'awaiting_count' => (clone $awaiting)->count(),
            'awaiting_amount' => (float) (clone $awaiting)->sum('fee'),
        ];
    }

    /**
     * The last twelve months, oldest first, keyed "Y-m".
     *
     * @return array<string, string>
     */
    private function lastTwelveMonths(): array
    {
        return collect(range(11, 0))
            ->mapWithKeys(fn (int $monthsAgo): array => [
                now()->startOfMonth()->subMonths($monthsAgo)->format('Y-m') => now()->startOfMonth()->subMonths($monthsAgo)->format('M Y'),
            ])
            ->all();
    }

    /**
     * Certificate requests and collections per month for the trend charts.
     *
     * @return array{labels: list<string>, requests: list<int>, collections: list<float>}
     */
    #[Computed]
    public function monthlyTrends(): array
    {
        $months = $this->lastTwelveMonths();
        $since = now()->startOfMonth()->subMonths(11);

        $requests = Certificate::query()
            ->where('created_at', '>=', $since)
            ->pluck('created_at')
            ->countBy(fn (CarbonInterface $date): string => $date->format('Y-m'));

        $collections = Payment::query()
            ->where('paid_at', '>=', $since)
            ->get(['paid_at', 'amount'])
            ->groupBy(fn (Payment $payment): string => $payment->paid_at->format('Y-m'))
            ->map(fn ($payments): float => (float) $payments->sum('amount'));

        return [
            'labels' => array_values($months),
            'requests' => array_map(fn (string $key): int => (int) ($requests[$key] ?? 0), array_keys($months)),
            'collections' => array_map(fn (string $key): float => (float) ($collections[$key] ?? 0), array_keys($months)),
        ];
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    #[Computed]
    public function certificatesByType(): array
    {
        $labels = CertificateType::labels();

        $counts = Certificate::query()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type');

        return [
            'labels' => $counts->keys()->map(fn (string $type): string => $labels[$type] ?? $type)->all(),
            'counts' => $counts->values()->map(fn ($total): int => (int) $total)->all(),
        ];
    }

    /**
     * Population figures for approved residents.
     *
     * @return array{total: int, male: int, female: int, voters: int, seniors: int, pwd: int, solo_parents: int, four_ps: int, by_purok: array{labels: list<string>, counts: list<int>}}
     */
    #[Computed]
    public function demographics(): array
    {
        $residents = Resident::approved();

        $byPurok = (clone $residents)
            ->selectRaw('purok, count(*) as total')
            ->groupBy('purok')
            ->pluck('total', 'purok');

        return [
            'total' => (clone $residents)->count(),
            'male' => (clone $residents)->where('gender', 'male')->count(),
            'female' => (clone $residents)->where('gender', 'female')->count(),
            'voters' => (clone $residents)->where('is_voter', true)->count(),
            'seniors' => (clone $residents)->ageGroup('senior')->count(),
            'pwd' => (clone $residents)->where('is_pwd', true)->count(),
            'solo_parents' => (clone $residents)->where('is_solo_parent', true)->count(),
            'four_ps' => (clone $residents)->where('is_4ps_beneficiary', true)->count(),
            'by_purok' => [
                'labels' => Resident::PUROKS,
                'counts' => array_map(fn (string $purok): int => (int) ($byPurok[$purok] ?? 0), Resident::PUROKS),
            ],
        ];
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Welcome Header --}}
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

    {{-- ===== ADMIN / STAFF STATS ===== --}}
    @if(auth()->user()->isAdmin() || auth()->user()->isStaff())

        @if(auth()->user()->hasPermission('residents') && $this->pendingRegistrations > 0)
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

        {{-- Collections --}}
        @if(auth()->user()->hasPermission('payments'))
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="rounded-xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-900/20 p-5">
                    <flux:text class="text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ __('Collected Today') }}</flux:text>
                    <flux:heading size="2xl" class="mt-2 text-emerald-700 dark:text-emerald-300">&#8369;{{ number_format($this->collections['today'], 2) }}</flux:heading>
                </div>
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Collected This Month') }}</flux:text>
                    <flux:heading size="2xl" class="mt-2 text-zinc-900 dark:text-white">&#8369;{{ number_format($this->collections['month'], 2) }}</flux:heading>
                    <flux:link href="{{ route('reports.collections', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]) }}" wire:navigate class="text-xs">{{ __('View collection report') }}</flux:link>
                </div>
                <div class="rounded-xl border border-orange-200 dark:border-orange-900 bg-orange-50 dark:bg-orange-900/20 p-5">
                    <flux:text class="text-sm font-medium text-orange-800 dark:text-orange-300">{{ __('Awaiting Payment') }}</flux:text>
                    <flux:heading size="2xl" class="mt-2 text-orange-700 dark:text-orange-300">{{ number_format($this->collections['awaiting_count']) }}</flux:heading>
                    <flux:text class="text-xs text-orange-700 dark:text-orange-300">&#8369;{{ number_format($this->collections['awaiting_amount'], 2) }} {{ __('to collect') }}</flux:text>
                </div>
            </div>
        @endif

        {{-- Charts --}}
        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
                <flux:heading class="mb-4">{{ __('Certificate Requests — Last 12 Months') }}</flux:heading>
                <div class="h-64" wire:ignore x-data="chart(@js(['type' => 'bar', 'labels' => $this->monthlyTrends['labels'], 'datasets' => [['label' => __('Requests'), 'data' => $this->monthlyTrends['requests'], 'color' => '#059669']]]))">
                    <canvas x-ref="canvas"></canvas>
                </div>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
                <flux:heading class="mb-4">{{ __('Certificates by Type') }}</flux:heading>
                @if (empty($this->certificatesByType['counts']))
                    <flux:text class="py-16 text-center text-zinc-400">{{ __('No certificate requests yet') }}</flux:text>
                @else
                    <div class="h-64" wire:ignore x-data="chart(@js(['type' => 'doughnut', 'labels' => $this->certificatesByType['labels'], 'datasets' => [['label' => __('Certificates'), 'data' => $this->certificatesByType['counts']]]]))">
                        <canvas x-ref="canvas"></canvas>
                    </div>
                @endif
            </div>
            @if(auth()->user()->hasPermission('payments'))
                <div class="lg:col-span-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
                    <flux:heading class="mb-4">{{ __('Collections — Last 12 Months (₱)') }}</flux:heading>
                    <div class="h-56" wire:ignore x-data="chart(@js(['type' => 'line', 'labels' => $this->monthlyTrends['labels'], 'datasets' => [['label' => __('Collections'), 'data' => $this->monthlyTrends['collections'], 'color' => '#0ea5e9']]]))">
                        <canvas x-ref="canvas"></canvas>
                    </div>
                </div>
            @endif
        </div>

        {{-- Demographics --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading>{{ __('Population') }}</flux:heading>
                @if(auth()->user()->hasPermission('residents'))
                    <flux:button :href="route('residents.index')" variant="ghost" size="sm" wire:navigate>{{ __('Open registry') }}</flux:button>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mb-6">
                @foreach ([
                    ['label' => __('Total'), 'value' => $this->demographics['total'], 'filter' => []],
                    ['label' => __('Male'), 'value' => $this->demographics['male'], 'filter' => ['gender' => 'male']],
                    ['label' => __('Female'), 'value' => $this->demographics['female'], 'filter' => ['gender' => 'female']],
                    ['label' => __('Voters'), 'value' => $this->demographics['voters'], 'filter' => ['voter' => 'yes']],
                    ['label' => __('Seniors (60+)'), 'value' => $this->demographics['seniors'], 'filter' => ['sector' => 'senior']],
                    ['label' => __('PWD'), 'value' => $this->demographics['pwd'], 'filter' => ['sector' => 'is_pwd']],
                    ['label' => __('Solo Parents'), 'value' => $this->demographics['solo_parents'], 'filter' => ['sector' => 'is_solo_parent']],
                    ['label' => __('4Ps'), 'value' => $this->demographics['four_ps'], 'filter' => ['sector' => 'is_4ps_beneficiary']],
                ] as $stat)
                    @php($statClasses = 'block rounded-lg bg-zinc-50 dark:bg-zinc-800/60 p-3')
                    @if(auth()->user()->hasPermission('residents'))
                        <a href="{{ route('residents.index', $stat['filter']) }}" wire:navigate class="{{ $statClasses }} hover:bg-zinc-100 dark:hover:bg-zinc-800">
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $stat['label'] }}</div>
                            <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ number_format($stat['value']) }}</div>
                        </a>
                    @else
                        <div class="{{ $statClasses }}">
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $stat['label'] }}</div>
                            <div class="text-xl font-semibold text-zinc-900 dark:text-white">{{ number_format($stat['value']) }}</div>
                        </div>
                    @endif
                @endforeach
            </div>

            <flux:heading size="sm" class="mb-2 text-zinc-500">{{ __('Residents per Purok') }}</flux:heading>
            <div class="h-56" wire:ignore x-data="chart(@js(['type' => 'bar', 'labels' => $this->demographics['by_purok']['labels'], 'datasets' => [['label' => __('Residents'), 'data' => $this->demographics['by_purok']['counts'], 'color' => '#8b5cf6']]]))">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>

        {{-- Recent Data Tables --}}
        <div class="grid lg:grid-cols-2 gap-6">

            {{-- Recent Certificate Requests --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 flex flex-col">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                    <flux:heading>Recent Certificate Requests</flux:heading>
                    @if(auth()->user()->hasPermission('certificates'))
                        <flux:button :href="route('certificates.index')" variant="ghost" size="sm" wire:navigate>View all</flux:button>
                    @endif
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
                                <flux:badge :color="$cert->status_color" size="sm">
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
                    @if(auth()->user()->hasPermission('appointments'))
                        <flux:button :href="route('appointments.index')" variant="ghost" size="sm" wire:navigate>View all</flux:button>
                    @endif
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
                                <flux:badge :color="match($appt->status) { 'scheduled' => 'zinc', 'confirmed' => 'blue', default => 'zinc' }" size="sm">
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
                                <flux:badge :color="$cert->status_color" size="sm">
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
                                <flux:badge :color="match($appt->status) { 'scheduled' => 'zinc', 'confirmed' => 'blue', default => 'zinc' }" size="sm">
                                    {{ $appt->status_label }}
                                </flux:badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
    @endif
</div>

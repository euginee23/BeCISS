<?php

use App\Models\Appointment;
use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\Resident;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new
#[Title('Reports')]
#[Layout('layouts::app')]
class extends Component {
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    public function resetFilters(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    /**
     * Apply the selected date range to a query on `created_at`.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>  $query
     * @return \Illuminate\Database\Eloquent\Builder<TModel>
     */
    private function inRange($query)
    {
        return $query
            ->when($this->from, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($this->to, fn ($q, $to) => $q->whereDate('created_at', '<=', $to));
    }

    /**
     * Count rows grouped by a column, labelled from a constant map.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, string>  $labels
     * @return Collection<string, int>
     */
    private function countBy($query, string $column, array $labels = []): Collection
    {
        $counts = $query->reorder()
            ->selectRaw("{$column} as bucket, count(*) as total")
            ->groupBy($column)
            ->pluck('total', 'bucket');

        return $counts
            ->mapWithKeys(fn (int $total, ?string $bucket): array => [
                $labels[$bucket] ?? ucfirst(str_replace('_', ' ', (string) $bucket ?: 'Unspecified')) => $total,
            ])
            ->sortDesc();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function summary(): array
    {
        return [
            'Certificate requests' => $this->inRange(Certificate::query())->count(),
            'Appointments' => $this->inRange(Appointment::query())->count(),
            'Blotter reports' => $this->inRange(Blotter::query())->count(),
            'New residents' => $this->inRange(Resident::query())->count(),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    #[Computed]
    public function revenue(): array
    {
        $certificatesPaid = $this->inRange(Certificate::query())->where('is_paid', true);
        $blottersPaid = $this->inRange(Blotter::query())->where('is_paid', true);

        return [
            'certificates' => (float) (clone $certificatesPaid)->sum('fee'),
            'blotters' => (float) (clone $blottersPaid)->sum('fee'),
            'receipts' => (clone $certificatesPaid)->whereNotNull('or_number')->count()
                + (clone $blottersPaid)->whereNotNull('or_number')->count(),
            'outstanding' => (float) $this->inRange(Certificate::query())->where('is_paid', false)->sum('fee')
                + (float) $this->inRange(Blotter::query())->where('is_paid', false)->sum('fee'),
        ];
    }

    /**
     * Every breakdown section, keyed by the slug used for CSV export.
     *
     * @return array<string, array{heading: string, column: string, rows: Collection<string, int>}>
     */
    #[Computed]
    public function sections(): array
    {
        return [
            'certificates-by-type' => [
                'heading' => __('Certificates by type'),
                'column' => __('Type'),
                'rows' => $this->countBy($this->inRange(Certificate::query()), 'type', Certificate::TYPES),
            ],
            'certificates-by-status' => [
                'heading' => __('Certificates by status'),
                'column' => __('Status'),
                'rows' => $this->countBy($this->inRange(Certificate::query()), 'status', Certificate::STATUSES),
            ],
            'appointments-by-service' => [
                'heading' => __('Appointments by service'),
                'column' => __('Service'),
                'rows' => $this->countBy($this->inRange(Appointment::query()), 'service_type', Appointment::SERVICE_TYPES),
            ],
            'appointments-by-status' => [
                'heading' => __('Appointments by status'),
                'column' => __('Status'),
                'rows' => $this->countBy($this->inRange(Appointment::query()), 'status', Appointment::STATUSES),
            ],
            'blotters-by-type' => [
                'heading' => __('Blotters by incident type'),
                'column' => __('Incident type'),
                'rows' => $this->countBy($this->inRange(Blotter::query()), 'incident_type', Blotter::TYPES),
            ],
            'residents-by-purok' => [
                'heading' => __('Residents by purok'),
                'column' => __('Purok'),
                'rows' => $this->countBy(Resident::query()->approved(), 'purok'),
            ],
            'residents-by-gender' => [
                'heading' => __('Residents by gender'),
                'column' => __('Gender'),
                'rows' => $this->countBy(Resident::query()->approved(), 'gender'),
            ],
            'residents-by-civil-status' => [
                'heading' => __('Residents by civil status'),
                'column' => __('Civil status'),
                'rows' => $this->countBy(Resident::query()->approved(), 'civil_status'),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function voterSplit(): array
    {
        return [
            'Registered voters' => Resident::query()->approved()->where('is_voter', true)->count(),
            'Not registered' => Resident::query()->approved()->where('is_voter', false)->count(),
        ];
    }

    /**
     * Download one breakdown section as CSV.
     */
    public function export(string $section): StreamedResponse
    {
        $sections = $this->sections;

        abort_unless(array_key_exists($section, $sections), 404);

        $definition = $sections[$section];
        $filename = 'beciss-'.$section.'-'.$this->from.'-to-'.$this->to.'.csv';

        return response()->streamDownload(function () use ($definition): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [$definition['column'], __('Total')]);

            foreach ($definition['rows'] as $label => $total) {
                fputcsv($handle, [$label, $total]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}; ?>

<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between print:hidden">
        <div>
            <flux:heading size="xl">{{ __('Reports') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Barangay activity and collections for a chosen period.') }}</flux:text>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <flux:field>
                <flux:label>{{ __('From') }}</flux:label>
                <flux:input type="date" wire:model.live="from" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('To') }}</flux:label>
                <flux:input type="date" wire:model.live="to" />
            </flux:field>

            <flux:button variant="ghost" wire:click="resetFilters">{{ __('This month') }}</flux:button>
            <flux:button variant="primary" icon="printer" x-on:click="window.print()">{{ __('Print') }}</flux:button>
        </div>
    </div>

    <div class="mb-6 hidden print:block">
        <flux:heading size="lg">{{ __('BeCISS Report') }}</flux:heading>
        <flux:text class="text-zinc-500">
            {{ \Carbon\Carbon::parse($from)->format('F j, Y') }} &ndash; {{ \Carbon\Carbon::parse($to)->format('F j, Y') }}
        </flux:text>
    </div>

    {{-- Summary --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 print:break-inside-avoid">
        @foreach ($this->summary as $label => $total)
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</flux:text>
                <flux:heading size="2xl" class="mt-2 text-zinc-900 dark:text-white">{{ number_format($total) }}</flux:heading>
            </div>
        @endforeach
    </div>

    {{-- Collections --}}
    <div class="mb-6 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900 print:break-inside-avoid">
        <flux:heading size="lg" class="mb-4">{{ __('Collections') }}</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('Certificate fees') }}</flux:text>
                <flux:heading size="lg" class="mt-1">&#8369;{{ number_format($this->revenue['certificates'], 2) }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('Blotter fees') }}</flux:text>
                <flux:heading size="lg" class="mt-1">&#8369;{{ number_format($this->revenue['blotters'], 2) }}</flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('Total collected') }}</flux:text>
                <flux:heading size="lg" class="mt-1 text-emerald-600 dark:text-emerald-400">
                    &#8369;{{ number_format($this->revenue['certificates'] + $this->revenue['blotters'], 2) }}
                </flux:heading>
            </div>
            <div>
                <flux:text class="text-sm text-zinc-500">{{ __('Unpaid balance') }}</flux:text>
                <flux:heading size="lg" class="mt-1">&#8369;{{ number_format($this->revenue['outstanding'], 2) }}</flux:heading>
            </div>
        </div>

        <flux:text class="mt-4 text-xs text-zinc-400">
            {{ trans_choice('{0}No official receipts issued|{1}:count official receipt issued|[2,*]:count official receipts issued', $this->revenue['receipts'], ['count' => number_format($this->revenue['receipts'])]) }}
        </flux:text>
    </div>

    {{-- Breakdowns --}}
    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($this->sections as $slug => $section)
            @php($max = $section['rows']->max() ?: 1)
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900 print:break-inside-avoid">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <flux:heading size="lg">{{ $section['heading'] }}</flux:heading>
                    <flux:button size="sm" variant="ghost" icon="arrow-down-tray" wire:click="export('{{ $slug }}')" class="print:hidden">
                        {{ __('CSV') }}
                    </flux:button>
                </div>

                @forelse ($section['rows'] as $label => $total)
                    <div class="mb-3">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="text-zinc-700 dark:text-zinc-300">{{ $label }}</span>
                            <span class="font-semibold text-zinc-900 dark:text-white">{{ number_format($total) }}</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ max(2, round(($total / $max) * 100)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <flux:text class="text-sm text-zinc-500">{{ __('No records in this period.') }}</flux:text>
                @endforelse
            </div>
        @endforeach

        {{-- Voter registration --}}
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900 print:break-inside-avoid">
            <flux:heading size="lg" class="mb-4">{{ __('Voter registration') }}</flux:heading>

            @php($voterMax = max($this->voterSplit) ?: 1)
            @foreach ($this->voterSplit as $label => $total)
                <div class="mb-3">
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $label }}</span>
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ number_format($total) }}</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ max(2, round(($total / $voterMax) * 100)) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

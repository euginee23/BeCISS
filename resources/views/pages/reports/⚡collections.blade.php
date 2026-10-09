<?php

use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new
#[Title('Collection Report')]
#[Layout('layouts::app')]
class extends Component {
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $cashier = '';

    /**
     * "blotter", a certificate type slug, or empty for everything.
     */
    #[Url]
    public string $source = '';

    public function mount(): void
    {
        $this->from = $this->from ?: now()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    public function setRange(string $range): void
    {
        [$this->from, $this->to] = match ($range) {
            'week' => [now()->startOfWeek()->toDateString(), now()->toDateString()],
            'month' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
            default => [now()->toDateString(), now()->toDateString()],
        };
    }

    /**
     * Every payment in the period, oldest first, as the OR booklet reads.
     *
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function payments(): Collection
    {
        return Payment::query()
            ->with([
                'payable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    Certificate::class => ['certificateType'],
                ]),
                'receiver',
            ])
            ->paidBetween($this->from ?: now(), $this->to ?: now())
            ->when($this->cashier, fn (Builder $query) => $query->where('received_by', $this->cashier))
            ->when($this->source === 'blotter', fn (Builder $query) => $query->where('payable_type', Blotter::class))
            ->when($this->source && $this->source !== 'blotter', fn (Builder $query) => $query->whereHasMorph(
                'payable',
                [Certificate::class],
                fn (Builder $certificates) => $certificates->where('type', $this->source),
            ))
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, array{count: int, amount: float}>
     */
    #[Computed]
    public function totalsByService(): array
    {
        return $this->payments
            ->groupBy(fn (Payment $payment): string => $payment->service_label)
            ->map(fn (Collection $payments): array => [
                'count' => $payments->count(),
                'amount' => (float) $payments->sum('amount'),
            ])
            ->sortByDesc('amount')
            ->all();
    }

    /**
     * @return array<string, array{count: int, amount: float}>
     */
    #[Computed]
    public function totalsByCashier(): array
    {
        return $this->payments
            ->groupBy(fn (Payment $payment): string => $payment->receiver?->name ?? __('Unknown'))
            ->map(fn (Collection $payments): array => [
                'count' => $payments->count(),
                'amount' => (float) $payments->sum('amount'),
            ])
            ->sortByDesc('amount')
            ->all();
    }

    #[Computed]
    public function grandTotal(): float
    {
        return (float) $this->payments->sum('amount');
    }

    /**
     * Staff who have received at least one payment.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function cashiers(): Collection
    {
        return User::query()
            ->whereIn('id', Payment::query()->select('received_by')->whereNotNull('received_by'))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function sources(): array
    {
        return [
            ...CertificateType::labels(),
            'blotter' => __('Blotter Report'),
        ];
    }

    public function export(): StreamedResponse
    {
        $payments = $this->payments;
        $filename = 'beciss-collections-'.$this->from.'-to-'.$this->to.'.csv';

        return response()->streamDownload(function () use ($payments): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date Paid', 'OR No.', 'Payor', 'Service', 'Reference No.', 'Amount', 'Collected By']);

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $payment->paid_at->format('Y-m-d H:i'),
                    $payment->or_number,
                    $payment->payor_name,
                    $payment->service_label,
                    $payment->reference_number,
                    number_format((float) $payment->amount, 2, '.', ''),
                    $payment->receiver?->name,
                ]);
            }

            fputcsv($handle, ['', '', '', '', 'TOTAL', number_format((float) $payments->sum('amount'), 2, '.', ''), '']);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}; ?>

<div>
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between print:hidden">
        <div>
            <flux:heading size="xl">{{ __('Collection Report') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Official receipts issued for certificates and blotter reports.') }}</flux:text>
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

            <flux:field>
                <flux:label>{{ __('Collected by') }}</flux:label>
                <flux:select wire:model.live="cashier">
                    <option value="">{{ __('All cashiers') }}</option>
                    @foreach ($this->cashiers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Service') }}</flux:label>
                <flux:select wire:model.live="source">
                    <option value="">{{ __('All services') }}</option>
                    @foreach ($this->sources as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <div class="flex gap-2">
            <flux:button size="sm" variant="ghost" wire:click="setRange('today')">{{ __('Today') }}</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="setRange('week')">{{ __('This week') }}</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="setRange('month')">{{ __('This month') }}</flux:button>
        </div>
        <div class="flex gap-2">
            <flux:button icon="arrow-down-tray" wire:click="export">{{ __('Export CSV') }}</flux:button>
            <flux:button variant="primary" icon="printer" x-on:click="window.print()">{{ __('Print / Save as PDF') }}</flux:button>
        </div>
    </div>

    <div class="mb-6 hidden print:block">
        <flux:heading size="lg">{{ \App\Models\BarangayProfile::get()->barangay_name }} — {{ __('Collection Report') }}</flux:heading>
        <flux:text class="text-zinc-500">
            {{ Carbon::parse($from)->format('F j, Y') }} &ndash; {{ Carbon::parse($to)->format('F j, Y') }}
        </flux:text>
    </div>

    {{-- Summary --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3 print:break-inside-avoid">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:text class="text-sm font-medium text-zinc-500">{{ __('Total collected') }}</flux:text>
            <flux:heading size="2xl" class="mt-2 text-emerald-600 dark:text-emerald-400">&#8369;{{ number_format($this->grandTotal, 2) }}</flux:heading>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:text class="text-sm font-medium text-zinc-500">{{ __('Official receipts') }}</flux:text>
            <flux:heading size="2xl" class="mt-2">{{ number_format($this->payments->count()) }}</flux:heading>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:text class="text-sm font-medium text-zinc-500">{{ __('OR range') }}</flux:text>
            <flux:heading size="lg" class="mt-2 font-mono">
                @if ($this->payments->isNotEmpty())
                    {{ $this->payments->first()->or_number }} &ndash; {{ $this->payments->last()->or_number }}
                @else
                    —
                @endif
            </flux:heading>
        </div>
    </div>

    {{-- OR listing --}}
    <div class="mb-6 rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date Paid') }}</flux:table.column>
                <flux:table.column>{{ __('OR No.') }}</flux:table.column>
                <flux:table.column>{{ __('Payor') }}</flux:table.column>
                <flux:table.column>{{ __('Service') }}</flux:table.column>
                <flux:table.column>{{ __('Reference No.') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Collected By') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->payments as $payment)
                    <flux:table.row :key="$payment->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $payment->paid_at->format('M j, Y g:i A') }}</flux:table.cell>
                        <flux:table.cell class="font-mono">{{ $payment->or_number }}</flux:table.cell>
                        <flux:table.cell>{{ $payment->payor_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $payment->service_label }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $payment->reference_number }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono">&#8369;{{ number_format((float) $payment->amount, 2) }}</flux:table.cell>
                        <flux:table.cell>{{ $payment->receiver?->name ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-8 text-center text-zinc-500">{{ __('No payments recorded for this period.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse

                @if ($this->payments->isNotEmpty())
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-right font-semibold">{{ __('Total') }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono font-semibold">&#8369;{{ number_format($this->grandTotal, 2) }}</flux:table.cell>
                        <flux:table.cell></flux:table.cell>
                    </flux:table.row>
                @endif
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Totals --}}
    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ([__('By service') => $this->totalsByService, __('By cashier') => $this->totalsByCashier] as $heading => $rows)
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900 print:break-inside-avoid">
                <flux:heading size="lg" class="mb-4">{{ $heading }}</flux:heading>

                @forelse ($rows as $label => $row)
                    <div wire:key="{{ md5($heading.$label) }}" class="flex items-center justify-between border-b border-zinc-100 py-2 last:border-0 dark:border-zinc-800">
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $label }} <span class="text-zinc-400">({{ $row['count'] }})</span></span>
                        <span class="font-mono text-sm font-medium">&#8369;{{ number_format($row['amount'], 2) }}</span>
                    </div>
                @empty
                    <flux:text class="text-sm text-zinc-400">{{ __('Nothing to show.') }}</flux:text>
                @endforelse
            </div>
        @endforeach
    </div>
</div>

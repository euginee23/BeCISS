<?php

use App\Models\Certificate;
use App\Services\CertificateWorkflow;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('My Certificates')]
#[Layout('layouts::app')]
class extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public bool $showCancelModal = false;

    public ?int $cancelCertificateId = null;

    public string $cancellationReason = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function resident()
    {
        return auth()->user()->resident;
    }

    #[Computed]
    public function certificates()
    {
        $resident = $this->resident;

        if (! $resident) {
            return null;
        }

        return $resident->certificates()
            ->with([
                'certificateType',
                'appointments' => fn ($query) => $query->whereIn('status', ['scheduled', 'confirmed']),
            ])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(10);
    }

    public function openCancelModal(int $certificateId): void
    {
        $this->resetValidation();
        $this->cancelCertificateId = $certificateId;
        $this->cancellationReason = '';
        $this->showCancelModal = true;
    }

    public function cancelCertificate(CertificateWorkflow $workflow): void
    {
        $this->validate([
            'cancellationReason' => ['nullable', 'string', 'max:500'],
        ]);

        $certificate = $this->resident?->certificates()->findOrFail($this->cancelCertificateId);

        abort_unless($certificate, 403);

        $workflow->cancel($certificate, auth()->user(), $this->cancellationReason ?: null);

        $this->showCancelModal = false;
        $this->cancelCertificateId = null;
        unset($this->certificates);
    }
};
?>

<div class="flex flex-col gap-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" class="text-zinc-900 dark:text-white">My Certificates</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">Track the status of your certificate requests.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" href="{{ route('resident.certificates.create') }}">
            {{ __('Request Certificate') }}
        </flux:button>
    </div>

    {{-- Status Filter --}}
    <div class="flex items-center gap-3 flex-wrap">
        <flux:select wire:model.live="status" class="w-40">
            <flux:select.option value="">All Status</flux:select.option>
            @foreach(\App\Models\Certificate::STATUSES as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        @if($status)
            <flux:button variant="ghost" size="sm" wire:click="$set('status', '')">Clear filter</flux:button>
        @endif
    </div>

    {{-- Certificates Cards --}}
    @if($this->certificates && $this->certificates->isNotEmpty())
        <div class="flex flex-col gap-4">
            @foreach($this->certificates as $cert)
                <div wire:key="{{ $cert->id }}" class="group relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 transition-all hover:shadow-lg hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-transparent dark:from-emerald-950/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative">
                        {{-- Top row: type + status --}}
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="size-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                                    <flux:icon name="document-text" class="size-5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div class="min-w-0">
                                    <span class="font-semibold text-zinc-900 dark:text-white block truncate">{{ $cert->type_label }}</span>
                                    <span class="font-mono text-xs text-zinc-400">{{ $cert->certificate_number }}</span>
                                </div>
                            </div>
                            <flux:badge :color="$cert->status_color" size="sm" class="shrink-0">{{ $cert->status_label }}</flux:badge>
                        </div>

                        {{-- Purpose --}}
                        @if($cert->purpose)
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-3 line-clamp-2">{{ $cert->purpose_label }}</p>
                        @endif

                        {{-- Details row --}}
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-4 flex-wrap text-sm text-zinc-600 dark:text-zinc-300">
                                <span class="flex items-center gap-1.5">
                                    <flux:icon name="calendar" class="size-4 text-zinc-400" />
                                    {{ $cert->created_at->format('M d, Y') }}
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <flux:icon name="banknotes" class="size-4 text-zinc-400" />
                                    @if ((float) $cert->fee > 0)
                                        ₱{{ number_format($cert->fee, 2) }}
                                        @if($cert->is_paid)
                                            <flux:badge color="green" size="sm">{{ __('Paid') }}</flux:badge>
                                        @endif
                                    @else
                                        {{ __('Free') }}
                                    @endif
                                </span>
                                @if ($visit = $cert->appointments->first())
                                    <span class="flex items-center gap-1.5">
                                        <flux:icon name="calendar-days" class="size-4 text-zinc-400" />
                                        {{ __('Visit') }}: {{ $visit->appointment_date->format('M d, Y') }} {{ $visit->appointment_time->format('g:i A') }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($cert->needsVisit() && $cert->appointments->isEmpty())
                                    <flux:button size="sm" variant="primary" icon="calendar-days" href="{{ route('resident.appointments.create', ['certificate' => $cert->id]) }}" wire:navigate>
                                        {{ __('Schedule Visit') }}
                                    </flux:button>
                                @endif
                                @if ($cert->status === 'completed')
                                    <flux:button size="sm" variant="filled" icon="arrow-down-tray" href="{{ route('certificates.download', $cert) }}" target="_blank">
                                        {{ __('Download PDF') }}
                                    </flux:button>
                                @endif
                                @if ($cert->isCancellable())
                                    <flux:button size="sm" variant="ghost" wire:click="openCancelModal({{ $cert->id }})">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        @if ($cert->status === 'awaiting_payment')
                            <flux:callout icon="banknotes" color="orange" class="mt-3">
                                <flux:callout.text>
                                    {{ __('Approved. Please pay ₱:amount at the barangay hall to continue. Bring a valid ID.', ['amount' => number_format($cert->fee, 2)]) }}
                                </flux:callout.text>
                            </flux:callout>
                        @elseif ($cert->status === 'ready_for_pickup')
                            <flux:callout icon="document-check" color="emerald" class="mt-3">
                                <flux:callout.text>{{ __('Ready for pickup at the barangay hall.') }}</flux:callout.text>
                            </flux:callout>
                        @elseif ($cert->status === 'rejected' && $cert->rejection_reason)
                            <flux:callout icon="x-circle" color="red" class="mt-3">
                                <flux:callout.text>{{ $cert->rejection_reason }}</flux:callout.text>
                            </flux:callout>
                        @endif
                    </div>
                </div>
            @endforeach

            @if($this->certificates->hasPages())
                <div class="mt-2">
                    {{ $this->certificates->links() }}
                </div>
            @endif
        </div>
    @else
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            <div class="flex flex-col items-center justify-center py-16 text-center gap-3">
                <div class="size-14 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                    <flux:icon name="document-text" class="size-7 text-zinc-400" />
                </div>
                <div>
                    <flux:heading>No certificates found</flux:heading>
                    <flux:text class="text-zinc-400 mt-1">
                        @if($status)
                            No certificates with this status. <button wire:click="$set('status', '')" class="text-emerald-600 hover:underline">Clear filter</button>
                        @else
                            You haven't requested any certificates yet. Use "Request Certificate" to file one online.
                        @endif
                    </flux:text>
                </div>
            </div>
        </div>
    @endif

    {{-- Cancel Modal --}}
    <flux:modal wire:model="showCancelModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Cancel Request') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Are you sure you want to cancel this certificate request?') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Reason (optional)') }}</flux:label>
                <flux:textarea wire:model="cancellationReason" rows="3" />
                <flux:error name="cancellationReason" />
            </flux:field>

            <flux:error name="status" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">{{ __('Keep') }}</flux:button>
                <flux:button variant="danger" wire:click="cancelCertificate">{{ __('Cancel Request') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

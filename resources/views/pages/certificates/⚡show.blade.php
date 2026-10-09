<?php

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Services\CertificateWorkflow;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('View Certificate')]
#[Layout('layouts::app')]
class extends Component {
    public Certificate $certificate;

    public bool $showPaymentModal = false;
    public bool $showReadyModal = false;
    public bool $showRejectModal = false;
    public bool $showCancelModal = false;

    public string $rejectionReason = '';
    public string $cancellationReason = '';
    public string $orNumber = '';
    public string $paymentRemarks = '';

    public string $issuedAt = '';
    public string $ctcNumber = '';
    public string $ctcPlaceIssued = '';
    public string $ctcDateIssued = '';

    public function mount(Certificate $certificate): void
    {
        $this->certificate = $certificate->load('resident', 'processor', 'certificateType', 'latestPayment.receiver');
    }

    #[Computed]
    public function requiresCtc(): bool
    {
        return CertificateType::requiresCtc($this->certificate->type);
    }

    #[Computed]
    public function canRecordPayment(): bool
    {
        return Auth::user()->hasPermission('payments');
    }

    #[Computed]
    public function visit(): ?\App\Models\Appointment
    {
        return $this->certificate->activeAppointment();
    }

    public function approve(CertificateWorkflow $workflow): void
    {
        $workflow->approve($this->certificate, Auth::user());
        $this->refreshCertificate();
    }

    public function openPaymentModal(): void
    {
        $this->resetValidation();
        $this->orNumber = '';
        $this->paymentRemarks = '';
        $this->showPaymentModal = true;
    }

    public function recordPayment(CertificateWorkflow $workflow): void
    {
        abort_unless($this->canRecordPayment, 403);

        $this->validate([
            'orNumber' => ['required', 'string', 'max:50'],
            'paymentRemarks' => ['nullable', 'string', 'max:500'],
        ]);

        $workflow->recordPayment($this->certificate, trim($this->orNumber), Auth::user(), $this->paymentRemarks ?: null);

        $this->showPaymentModal = false;
        $this->refreshCertificate();
    }

    public function openReadyModal(): void
    {
        $this->resetValidation();
        $this->issuedAt = now()->format('Y-m-d');
        $this->ctcNumber = '';
        $this->ctcPlaceIssued = '';
        $this->ctcDateIssued = now()->format('Y-m-d');
        $this->showReadyModal = true;
    }

    public function markReady(CertificateWorkflow $workflow): void
    {
        $ctcRule = $this->requiresCtc ? 'required' : 'nullable';

        $this->validate([
            'issuedAt' => ['required', 'date'],
            'ctcNumber' => [$ctcRule, 'string', 'max:100'],
            'ctcPlaceIssued' => [$ctcRule, 'string', 'max:200'],
            'ctcDateIssued' => [$ctcRule, 'date'],
        ]);

        $workflow->markReady($this->certificate, [
            'issued_at' => $this->issuedAt,
            'ctc_number' => $this->ctcNumber ?: null,
            'ctc_place_issued' => $this->ctcPlaceIssued ?: null,
            'ctc_date_issued' => $this->requiresCtc || $this->ctcNumber ? ($this->ctcDateIssued ?: null) : null,
        ]);

        $this->showReadyModal = false;
        $this->refreshCertificate();
    }

    public function release(CertificateWorkflow $workflow): void
    {
        $workflow->release($this->certificate);
        $this->refreshCertificate();
    }

    public function openRejectModal(): void
    {
        $this->resetValidation();
        $this->showRejectModal = true;
    }

    public function rejectCertificate(CertificateWorkflow $workflow): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'max:500'],
        ]);

        $workflow->reject($this->certificate, $this->rejectionReason);

        $this->showRejectModal = false;
        $this->refreshCertificate();
    }

    public function openCancelModal(): void
    {
        $this->resetValidation();
        $this->showCancelModal = true;
    }

    public function cancelCertificate(CertificateWorkflow $workflow): void
    {
        $this->validate([
            'cancellationReason' => ['nullable', 'string', 'max:500'],
        ]);

        $workflow->cancel($this->certificate, Auth::user(), $this->cancellationReason ?: null);

        $this->showCancelModal = false;
        $this->refreshCertificate();
    }

    private function refreshCertificate(): void
    {
        $this->certificate->refresh()->load('resident', 'processor', 'certificateType', 'latestPayment.receiver');
        unset($this->visit);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('certificates.index') }}">
            {{ __('Back to Certificates') }}
        </flux:button>

        @if ($certificate->isEditable())
            <flux:button variant="primary" icon="pencil" href="{{ route('certificates.edit', $certificate) }}">
                {{ __('Edit') }}
            </flux:button>
        @endif
    </div>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" class="flex items-center gap-3">
                {{ $certificate->certificate_number }}
                <flux:badge size="lg" :color="$certificate->status_color">
                    {{ $certificate->status_label }}
                </flux:badge>
            </flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ $certificate->type_label }}</flux:text>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap gap-2">
            @if ($certificate->isPrintable())
                <flux:button variant="filled" icon="arrow-down-tray" href="{{ route('certificates.download', $certificate) }}" target="_blank">
                    {{ __('Download PDF') }}
                </flux:button>
            @endif

            @if ($certificate->status === 'pending')
                <flux:button variant="primary" icon="check" wire:click="approve">
                    {{ $certificate->requiresPayment() ? __('Approve (Awaiting Payment)') : __('Approve & Process') }}
                </flux:button>
            @elseif ($certificate->status === 'awaiting_payment' && $this->canRecordPayment)
                <flux:button variant="primary" icon="banknotes" wire:click="openPaymentModal">
                    {{ __('Record Payment') }}
                </flux:button>
            @elseif ($certificate->status === 'processing')
                <flux:button variant="primary" icon="document-check" wire:click="openReadyModal">
                    {{ __('Mark Ready for Pickup') }}
                </flux:button>
            @elseif ($certificate->status === 'ready_for_pickup')
                <flux:button variant="primary" icon="hand-raised" wire:click="release" wire:confirm="{{ __('Release this certificate to the resident?') }}">
                    {{ __('Release') }}
                </flux:button>
            @endif

            @if ($certificate->canTransitionTo('rejected'))
                <flux:button variant="danger" wire:click="openRejectModal">
                    {{ __('Reject') }}
                </flux:button>
            @endif

            @if ($certificate->isCancellable())
                <flux:button variant="ghost" wire:click="openCancelModal">
                    {{ __('Cancel Request') }}
                </flux:button>
            @endif
        </div>
    </div>

    <flux:error name="status" class="mb-4" />

    @if ($this->visit)
        <flux:callout icon="calendar-days" color="blue" class="mb-6">
            <flux:callout.heading>{{ __('Visit scheduled') }}</flux:callout.heading>
            <flux:callout.text>
                {{ $this->visit->appointment_date->format('F j, Y') }} {{ __('at') }} {{ $this->visit->appointment_time->format('g:i A') }}
                ({{ $this->visit->reference_number }}) —
                <flux:link href="{{ route('appointments.show', $this->visit) }}" wire:navigate>{{ __('View appointment') }}</flux:link>
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Requester Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Requester Information') }}</flux:heading>

            <div class="flex items-center gap-4 mb-4">
                <flux:avatar size="lg" name="{{ $certificate->requester_name }}" />
                <div>
                    <flux:heading size="base">{{ $certificate->requester_name }}</flux:heading>
                    <flux:text class="text-zinc-500">{{ $certificate->requester_address }}</flux:text>
                </div>
            </div>

            @if ($certificate->resident)
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Age') }}</dt>
                        <dd class="font-medium">{{ $certificate->resident->age }} {{ __('years old') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Gender') }}</dt>
                        <dd class="font-medium">{{ ucfirst($certificate->resident->gender) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Contact') }}</dt>
                        <dd class="font-medium">{{ $certificate->resident->contact_number ?? '—' }}</dd>
                    </div>
                </dl>
            @endif
        </div>

        {{-- Certificate Details --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Certificate Details') }}</flux:heading>

            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Certificate Number') }}</dt>
                    <dd class="font-mono font-medium">{{ $certificate->certificate_number }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Type') }}</dt>
                    <dd class="font-medium">{{ $certificate->type_label }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Purpose') }}</dt>
                    <dd class="font-medium text-right max-w-xs">{{ $certificate->purpose_label }}</dd>
                </div>
                <flux:separator />
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Processing Fee') }}</dt>
                    <dd class="font-medium text-lg">{{ (float) $certificate->fee > 0 ? '₱'.number_format($certificate->fee, 2) : __('Free') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Payment Status') }}</dt>
                    <dd>
                        @if ($certificate->is_paid)
                            <flux:badge size="sm" color="emerald">{{ __('Paid') }}</flux:badge>
                        @elseif ((float) $certificate->fee <= 0)
                            <flux:badge size="sm" color="zinc">{{ __('No payment needed') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">{{ __('Unpaid') }}</flux:badge>
                        @endif
                    </dd>
                </div>
                @if ($certificate->or_number)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('OR Number') }}</dt>
                        <dd class="font-mono font-medium">{{ $certificate->or_number }}</dd>
                    </div>
                @endif
                @if ($certificate->latestPayment)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Paid On') }}</dt>
                        <dd class="font-medium">{{ $certificate->latestPayment->paid_at->format('M j, Y g:i A') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Received By') }}</dt>
                        <dd class="font-medium">{{ $certificate->latestPayment->receiver?->name ?? '—' }}</dd>
                    </div>
                @endif
                @if ($certificate->issued_at)
                    <flux:separator />
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Date of Issuance') }}</dt>
                        <dd class="font-medium">{{ $certificate->issued_at->format('F j, Y') }}</dd>
                    </div>
                    @if ($certificate->ctc_number)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('CTC No.') }}</dt>
                            <dd class="font-medium text-right">{{ $certificate->ctc_number }} · {{ $certificate->ctc_place_issued }} · {{ $certificate->ctc_date_issued?->format('M j, Y') }}</dd>
                        </div>
                    @endif
                @endif
            </dl>
        </div>

        {{-- Remarks --}}
        @if ($certificate->remarks)
            <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-4">{{ __('Remarks') }}</flux:heading>
                <flux:text>{{ $certificate->remarks }}</flux:text>
            </div>
        @endif

        {{-- Timeline --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Timeline') }}</flux:heading>

            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="mt-1 size-2 rounded-full bg-emerald-500"></div>
                    <div>
                        <flux:text class="font-medium">{{ __('Request Created') }}</flux:text>
                        <flux:text class="text-sm text-zinc-500">{{ $certificate->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                </div>

                @if ($certificate->approved_at)
                    <div class="flex items-start gap-3">
                        <div class="mt-1 size-2 rounded-full bg-orange-500"></div>
                        <div>
                            <flux:text class="font-medium">{{ __('Approved') }}</flux:text>
                            <flux:text class="text-sm text-zinc-500">{{ $certificate->approved_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    </div>
                @endif

                @if ($certificate->latestPayment)
                    <div class="flex items-start gap-3">
                        <div class="mt-1 size-2 rounded-full bg-emerald-500"></div>
                        <div>
                            <flux:text class="font-medium">{{ __('Payment Received') }}</flux:text>
                            <flux:text class="text-sm text-zinc-500">{{ $certificate->latestPayment->paid_at->format('M j, Y g:i A') }} · OR {{ $certificate->latestPayment->or_number }}</flux:text>
                        </div>
                    </div>
                @endif

                @if ($certificate->processed_at)
                    <div class="flex items-start gap-3">
                        <div class="mt-1 size-2 rounded-full bg-blue-500"></div>
                        <div>
                            <flux:text class="font-medium">{{ __('Processing Started') }}</flux:text>
                            <flux:text class="text-sm text-zinc-500">{{ $certificate->processed_at->format('M j, Y g:i A') }}</flux:text>
                            @if ($certificate->processor)
                                <flux:text class="text-sm text-zinc-500">{{ __('By') }}: {{ $certificate->processor->name }}</flux:text>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($certificate->completed_at)
                    <div class="flex items-start gap-3">
                        <div class="mt-1 size-2 rounded-full bg-green-500"></div>
                        <div>
                            <flux:text class="font-medium">{{ __('Completed') }}</flux:text>
                            <flux:text class="text-sm text-zinc-500">{{ $certificate->completed_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    </div>
                @endif

                @if ($certificate->cancelled_at)
                    <div class="flex items-start gap-3">
                        <div class="mt-1 size-2 rounded-full bg-zinc-400"></div>
                        <div>
                            <flux:text class="font-medium">{{ __('Cancelled') }}</flux:text>
                            <flux:text class="text-sm text-zinc-500">{{ $certificate->cancelled_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    </div>
                @endif

                @if ($certificate->rejected_at)
                    <div class="flex items-start gap-3">
                        <div class="mt-1 size-2 rounded-full bg-red-500"></div>
                        <div>
                            <flux:text class="font-medium">{{ __('Rejected') }}</flux:text>
                            <flux:text class="text-sm text-zinc-500">{{ $certificate->rejected_at->format('M j, Y g:i A') }}</flux:text>
                            @if ($certificate->rejection_reason)
                                <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $certificate->rejection_reason }}</flux:text>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Payment Modal --}}
    <flux:modal wire:model="showPaymentModal" class="max-w-sm">
        <form wire:submit="recordPayment" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Record Payment') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Amount due') }}: <strong>₱{{ number_format($certificate->fee, 2) }}</strong>
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('OR Number') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="orNumber" placeholder="OR-XXXX-XXXX" required />
                <flux:error name="orNumber" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Remarks') }}</flux:label>
                <flux:input wire:model="paymentRemarks" />
                <flux:error name="paymentRemarks" />
            </flux:field>

            <flux:error name="status" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showPaymentModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Record Payment') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Ready Modal --}}
    <flux:modal wire:model="showReadyModal" class="max-w-sm">
        <form wire:submit="markReady" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Issuance Details') }}</flux:heading>
                <flux:text class="mt-2">{{ __('These details are printed on the certificate and kept for reprints.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Date of Issuance') }} <span class="text-red-500">*</span></flux:label>
                <flux:input type="date" wire:model="issuedAt" required />
                <flux:error name="issuedAt" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('CTC No.') }} @if ($this->requiresCtc)<span class="text-red-500">*</span>@endif</flux:label>
                <flux:input wire:model="ctcNumber" placeholder="e.g. 12345678" :required="$this->requiresCtc" />
                <flux:error name="ctcNumber" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('CTC Place Issued') }} @if ($this->requiresCtc)<span class="text-red-500">*</span>@endif</flux:label>
                <flux:input wire:model="ctcPlaceIssued" placeholder="e.g. Municipality of ..." :required="$this->requiresCtc" />
                <flux:error name="ctcPlaceIssued" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('CTC Date Issued') }} @if ($this->requiresCtc)<span class="text-red-500">*</span>@endif</flux:label>
                <flux:input type="date" wire:model="ctcDateIssued" :required="$this->requiresCtc" />
                <flux:error name="ctcDateIssued" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showReadyModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Mark Ready') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Cancel Modal --}}
    <flux:modal wire:model="showCancelModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Cancel Request') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The resident will be notified. Any scheduled visit is cancelled too.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Reason') }}</flux:label>
                <flux:textarea wire:model="cancellationReason" rows="3" />
                <flux:error name="cancellationReason" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">{{ __('Back') }}</flux:button>
                <flux:button variant="danger" wire:click="cancelCertificate">{{ __('Cancel Request') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Reject Modal --}}
    <flux:modal wire:model="showRejectModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reject Certificate') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Please provide a reason for rejecting this certificate request.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Rejection Reason') }} <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="rejectionReason" rows="3" required />
                <flux:error name="rejectionReason" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showRejectModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" wire:click="rejectCertificate">
                    {{ __('Reject') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>

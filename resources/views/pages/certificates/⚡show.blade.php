<?php

use App\Mail\CertificateReadyForPickup;
use App\Mail\CertificateRejected;
use App\Models\Certificate;
use App\Notifications\ResidentNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('View Certificate')]
#[Layout('layouts::app')]
class extends Component {
    public Certificate $certificate;

    public bool $showProcessModal = false;
    public bool $showRejectModal = false;
    public bool $showCompleteModal = false;
    public bool $showExportModal = false;

    public string $rejectionReason = '';
    public string $orNumber = '';

    public string $dateOfIssuance = '';
    public string $ctcNo = '';
    public string $ctcPlaceIssued = '';
    public string $ctcDateIssued = '';
    public string $exportFormat = 'docx';

    public function mount(Certificate $certificate): void
    {
        $this->certificate = $certificate->load('resident', 'processor');
    }

    public function startProcessing(): void
    {
        $this->certificate->update([
            'status' => 'processing',
            'processed_by' => Auth::id(),
            'processed_at' => now(),
        ]);

        $this->certificate->refresh();

        $user = $this->certificate->resident->user;
        $user?->notify(new ResidentNotification(
            type: 'certificate_processing',
            title: 'Certificate Being Processed',
            body: 'Your ' . $this->certificate->type_label . ' (' . $this->certificate->certificate_number . ') is now being processed.',
            url: route('resident.certificates.index'),
        ));
    }

    public function markReadyForPickup(): void
    {
        $this->certificate->update([
            'status' => 'ready_for_pickup',
        ]);

        $this->certificate->refresh();
        $this->notifyResident(CertificateReadyForPickup::class);
        $this->notifyResidentDatabase(
            type: 'certificate_ready',
            title: 'Certificate Ready for Pickup',
            body: 'Your ' . $this->certificate->type_label . ' (' . $this->certificate->certificate_number . ') is ready for pickup at the barangay hall.',
        );
    }

    public function openCompleteModal(): void
    {
        $this->showCompleteModal = true;
    }

    public function completeCertificate(): void
    {
        $this->validate([
            'orNumber' => ['required', 'string', 'max:50'],
        ]);

        $this->certificate->update([
            'status' => 'completed',
            'completed_at' => now(),
            'is_paid' => true,
            'or_number' => $this->orNumber,
        ]);

        $this->showCompleteModal = false;
        $this->certificate->refresh();

        $user = $this->certificate->resident->user;
        $user?->notify(new ResidentNotification(
            type: 'certificate_completed',
            title: 'Certificate Completed',
            body: 'Your ' . $this->certificate->type_label . ' (' . $this->certificate->certificate_number . ') has been released and completed.',
            url: route('resident.certificates.index'),
        ));
    }

    public function openRejectModal(): void
    {
        $this->showRejectModal = true;
    }

    public function rejectCertificate(): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'max:500'],
        ]);

        $this->certificate->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $this->rejectionReason,
        ]);

        $this->showRejectModal = false;
        $this->certificate->refresh();
        $this->notifyResident(CertificateRejected::class);
        $this->notifyResidentDatabase(
            type: 'certificate_rejected',
            title: 'Certificate Request Rejected',
            body: 'Your ' . $this->certificate->type_label . ' (' . $this->certificate->certificate_number . ') was rejected. Reason: ' . $this->rejectionReason,
        );
    }

    private function notifyResident(string $mailableClass): void
    {
        $user = $this->certificate->resident->user;

        if ($user) {
            Mail::to($user->email)->send(new $mailableClass($user, $this->certificate));
        }
    }

    private function notifyResidentDatabase(string $type, string $title, string $body): void
    {
        $user = $this->certificate->resident->user;

        $user?->notify(new ResidentNotification(
            type: $type,
            title: $title,
            body: $body,
            url: route('resident.certificates.index'),
        ));
    }

    public function openExportModal(): void
    {
        $this->dateOfIssuance = now()->format('Y-m-d');
        $this->ctcNo = '';
        $this->ctcPlaceIssued = '';
        $this->ctcDateIssued = '';
        $this->exportFormat = 'docx';
        $this->showExportModal = true;
    }

    public function downloadCertificate(): void
    {
        $this->validate([
            'dateOfIssuance' => ['required', 'date'],
            'ctcNo' => ['nullable', 'string', 'max:100'],
            'ctcPlaceIssued' => ['nullable', 'string', 'max:200'],
            'ctcDateIssued' => ['nullable', 'date'],
            'exportFormat' => ['required', 'in:docx,pdf'],
        ]);

        $url = route('certificates.download', $this->certificate).'?'.http_build_query([
            'format' => $this->exportFormat,
            'date_of_issuance' => $this->dateOfIssuance,
            'ctc_no' => $this->ctcNo,
            'ctc_place_issued' => $this->ctcPlaceIssued,
            'ctc_date_issued' => $this->ctcDateIssued,
        ]);

        $this->showExportModal = false;

        $this->dispatch('download-certificate', url: $url);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('certificates.index') }}">
            {{ __('Back to Certificates') }}
        </flux:button>

        @if (in_array($certificate->status, ['pending', 'processing']))
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
        <div class="flex gap-2">
            @if (in_array($certificate->type, ['certificate_of_residency', 'barangay_clearance']) && in_array($certificate->status, ['processing', 'ready_for_pickup', 'completed']))
                <flux:button variant="primary" icon="arrow-down-tray" wire:click="openExportModal">
                    {{ __('Download') }}
                </flux:button>
            @endif

            @if ($certificate->status === 'pending')
                <flux:button variant="primary" wire:click="startProcessing">
                    {{ __('Start Processing') }}
                </flux:button>
                <flux:button variant="danger" wire:click="openRejectModal">
                    {{ __('Reject') }}
                </flux:button>
            @elseif ($certificate->status === 'processing')
                <flux:button variant="primary" wire:click="markReadyForPickup">
                    {{ __('Mark Ready for Pickup') }}
                </flux:button>
                <flux:button variant="danger" wire:click="openRejectModal">
                    {{ __('Reject') }}
                </flux:button>
            @elseif ($certificate->status === 'ready_for_pickup')
                <flux:button variant="primary" wire:click="openCompleteModal">
                    {{ __('Complete & Release') }}
                </flux:button>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Resident Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Resident Information') }}</flux:heading>

            <div class="flex items-center gap-4 mb-4">
                <flux:avatar size="lg" name="{{ $certificate->resident->full_name }}" />
                <div>
                    <flux:heading size="base">{{ $certificate->resident->full_name }}</flux:heading>
                    <flux:text class="text-zinc-500">{{ $certificate->resident->address }}</flux:text>
                </div>
            </div>

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
                    <dd class="font-medium text-right max-w-xs">{{ $certificate->purpose }}</dd>
                </div>
                <flux:separator />
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Processing Fee') }}</dt>
                    <dd class="font-medium text-lg">₱{{ number_format($certificate->fee, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Payment Status') }}</dt>
                    <dd>
                        @if ($certificate->is_paid)
                            <flux:badge size="sm" color="emerald">{{ __('Paid') }}</flux:badge>
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

    {{-- Complete Modal --}}
    <flux:modal wire:model="showCompleteModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Complete Certificate') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Enter the Official Receipt number to complete this certificate request.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('OR Number') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="orNumber" placeholder="OR-XXXX-XXXX" required />
                <flux:error name="orNumber" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCompleteModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" wire:click="completeCertificate">
                    {{ __('Complete') }}
                </flux:button>
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

    {{-- Export Modal --}}
    <flux:modal wire:model="showExportModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Download Certificate') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Fill in the details below before generating the document.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Date of Issuance') }} <span class="text-red-500">*</span></flux:label>
                <flux:input type="date" wire:model="dateOfIssuance" required />
                <flux:error name="dateOfIssuance" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('CTC No.') }}</flux:label>
                <flux:input wire:model="ctcNo" placeholder="e.g. 12345678" />
                <flux:error name="ctcNo" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('CTC Place Issued') }}</flux:label>
                <flux:input wire:model="ctcPlaceIssued" placeholder="e.g. Municipality of ..." />
                <flux:error name="ctcPlaceIssued" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('CTC Date Issued') }}</flux:label>
                <flux:input type="date" wire:model="ctcDateIssued" />
                <flux:error name="ctcDateIssued" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Format') }}</flux:label>
                <flux:radio.group wire:model="exportFormat">
                    <flux:radio value="docx" label="{{ __('Word Document (.docx)') }}" />
                    <flux:radio value="pdf" label="{{ __('PDF (.pdf)') }}" />
                </flux:radio.group>
                <flux:error name="exportFormat" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showExportModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" icon="arrow-down-tray" wire:click="downloadCertificate">
                    {{ __('Generate & Download') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

@script
<script>
    $wire.on('download-certificate', ({ url }) => {
        window.open(url, '_blank');
    });
</script>
@endscript

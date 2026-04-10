<?php

use App\Models\Blotter;
use App\Notifications\ResidentNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('View Blotter')]
#[Layout('layouts::app')]
class extends Component {
    public Blotter $blotter;

    public bool $showRejectModal = false;
    public bool $showCompleteModal = false;
    public bool $showExportModal = false;

    public string $rejectionReason = '';
    public string $orNumber = '';

    public string $dateOfIssuance = '';
    public string $exportFormat = 'docx';

    public function mount(Blotter $blotter): void
    {
        $this->blotter = $blotter->load('resident', 'processor');
    }

    public function startProcessing(): void
    {
        $this->blotter->update([
            'status' => 'processing',
            'processed_by' => Auth::id(),
            'processed_at' => now(),
        ]);

        $this->blotter->refresh();

        $user = $this->blotter->resident?->user;
        $user?->notify(new ResidentNotification(
            type: 'blotter_processing',
            title: 'Blotter Report Being Processed',
            body: 'Your blotter report (' . $this->blotter->blotter_number . ') is now being processed.',
            url: route('resident.blotters.index'),
        ));
    }

    public function markReadyForPickup(): void
    {
        $this->blotter->update([
            'status' => 'ready_for_pickup',
        ]);

        $this->blotter->refresh();

        $user = $this->blotter->resident?->user;
        $user?->notify(new ResidentNotification(
            type: 'blotter_ready',
            title: 'Blotter Report Ready for Pickup',
            body: 'Your blotter report (' . $this->blotter->blotter_number . ') is ready for pickup at the barangay hall.',
            url: route('resident.blotters.index'),
        ));
    }

    public function openCompleteModal(): void
    {
        $this->showCompleteModal = true;
    }

    public function completeBlotter(): void
    {
        $this->validate([
            'orNumber' => ['required', 'string', 'max:50'],
        ]);

        $this->blotter->update([
            'status' => 'completed',
            'completed_at' => now(),
            'is_paid' => true,
            'or_number' => $this->orNumber,
        ]);

        $this->showCompleteModal = false;
        $this->blotter->refresh();

        $user = $this->blotter->resident?->user;
        $user?->notify(new ResidentNotification(
            type: 'blotter_completed',
            title: 'Blotter Report Completed',
            body: 'Your blotter report (' . $this->blotter->blotter_number . ') has been released and completed.',
            url: route('resident.blotters.index'),
        ));
    }

    public function openRejectModal(): void
    {
        $this->showRejectModal = true;
    }

    public function rejectBlotter(): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'max:500'],
        ]);

        $this->blotter->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $this->rejectionReason,
        ]);

        $this->showRejectModal = false;
        $this->blotter->refresh();

        $user = $this->blotter->resident?->user;
        $user?->notify(new ResidentNotification(
            type: 'blotter_rejected',
            title: 'Blotter Report Rejected',
            body: 'Your blotter report (' . $this->blotter->blotter_number . ') was rejected. Reason: ' . $this->rejectionReason,
            url: route('resident.blotters.index'),
        ));
    }

    public function openExportModal(): void
    {
        $this->dateOfIssuance = now()->format('Y-m-d');
        $this->exportFormat = 'docx';
        $this->showExportModal = true;
    }

    public function downloadBlotter(): void
    {
        $this->validate([
            'dateOfIssuance' => ['required', 'date'],
            'exportFormat' => ['required', 'in:docx,pdf'],
        ]);

        $url = route('blotters.download', $this->blotter) . '?' . http_build_query([
            'format' => $this->exportFormat,
            'date_of_issuance' => $this->dateOfIssuance,
        ]);

        $this->showExportModal = false;

        $this->dispatch('download-blotter', url: $url);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('blotters.index') }}">
            {{ __('Back to Blotters') }}
        </flux:button>

        @if (in_array($blotter->status, ['pending', 'processing']))
            <flux:button variant="primary" icon="pencil" href="{{ route('blotters.edit', $blotter) }}">
                {{ __('Edit') }}
            </flux:button>
        @endif
    </div>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" class="flex items-center gap-3">
                {{ $blotter->blotter_number }}
                <flux:badge size="lg" :color="$blotter->status_color">
                    {{ $blotter->status_label }}
                </flux:badge>
            </flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ $blotter->type_label }}</flux:text>
        </div>

        {{-- Action Buttons --}}
        <div class="flex gap-2">
            @if (in_array($blotter->status, ['processing', 'ready_for_pickup', 'completed']))
                <flux:button variant="primary" icon="arrow-down-tray" wire:click="openExportModal">
                    {{ __('Download') }}
                </flux:button>
            @endif

            @if ($blotter->status === 'pending')
                <flux:button variant="primary" wire:click="startProcessing">
                    {{ __('Start Processing') }}
                </flux:button>
                <flux:button variant="danger" wire:click="openRejectModal">
                    {{ __('Reject') }}
                </flux:button>
            @elseif ($blotter->status === 'processing')
                <flux:button variant="primary" wire:click="markReadyForPickup">
                    {{ __('Mark Ready for Pickup') }}
                </flux:button>
                <flux:button variant="danger" wire:click="openRejectModal">
                    {{ __('Reject') }}
                </flux:button>
            @elseif ($blotter->status === 'ready_for_pickup')
                <flux:button variant="primary" wire:click="openCompleteModal">
                    {{ __('Complete & Release') }}
                </flux:button>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Complainant Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Complainant Information') }}</flux:heading>

            @if ($blotter->is_walkin)
                <div class="mb-4 flex items-center gap-3">
                    <flux:badge size="sm" color="zinc">{{ __('Walk-in') }}</flux:badge>
                </div>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Name') }}</dt>
                        <dd class="font-medium">{{ $blotter->complainant_name }}</dd>
                    </div>
                    @if ($blotter->complainant_purok)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Purok / Zone') }}</dt>
                            <dd class="font-medium">{{ $blotter->complainant_purok }}</dd>
                        </div>
                    @endif
                    @if ($blotter->complainant_house_number || $blotter->complainant_street)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Address') }}</dt>
                            <dd class="font-medium">
                                {{ implode(' ', array_filter([$blotter->complainant_house_number, $blotter->complainant_street])) ?: '—' }}
                            </dd>
                        </div>
                    @endif
                    @if ($blotter->complainant_contact)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Contact') }}</dt>
                            <dd class="font-medium">{{ $blotter->complainant_contact }}</dd>
                        </div>
                    @endif
                </dl>
            @else
                <div class="flex items-center gap-4 mb-4">
                    <flux:avatar size="lg" name="{{ $blotter->resident->full_name }}" />
                    <div>
                        <flux:heading size="base">{{ $blotter->resident->full_name }}</flux:heading>
                        <flux:text class="text-zinc-500">{{ $blotter->resident->address }}</flux:text>
                    </div>
                </div>

                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Age') }}</dt>
                        <dd class="font-medium">{{ $blotter->resident->age }} {{ __('years old') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Gender') }}</dt>
                        <dd class="font-medium">{{ ucfirst($blotter->resident->gender) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Contact') }}</dt>
                        <dd class="font-medium">{{ $blotter->resident->contact_number ?? '—' }}</dd>
                    </div>
                </dl>
            @endif
        </div>

        {{-- Blotter Details --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Blotter Details') }}</flux:heading>

            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Blotter Number') }}</dt>
                    <dd class="font-mono font-medium">{{ $blotter->blotter_number }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Incident Type') }}</dt>
                    <dd class="font-medium">{{ $blotter->type_label }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Incident Date/Time') }}</dt>
                    <dd class="font-medium">{{ $blotter->incident_datetime->format('M j, Y g:i A') }}</dd>
                </div>
                @if ($blotter->incident_location)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Incident Location') }}</dt>
                        <dd class="font-medium">{{ $blotter->incident_location }}</dd>
                    </div>
                @endif
                @if ($blotter->respondent_name)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Respondent') }}</dt>
                        <dd class="font-medium">{{ $blotter->respondent_name }}</dd>
                    </div>
                @endif
                @if ($blotter->owner_name)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Property Owner') }}</dt>
                        <dd class="font-medium">{{ $blotter->owner_name }}</dd>
                    </div>
                @endif
                <flux:separator />
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Processing Fee') }}</dt>
                    <dd class="font-medium text-lg">₱{{ number_format($blotter->fee, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Payment Status') }}</dt>
                    <dd>
                        @if ($blotter->is_paid)
                            <flux:badge size="sm" color="emerald">{{ __('Paid') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">{{ __('Unpaid') }}</flux:badge>
                        @endif
                    </dd>
                </div>
                @if ($blotter->or_number)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('OR Number') }}</dt>
                        <dd class="font-mono font-medium">{{ $blotter->or_number }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Narrative --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700 lg:col-span-2">
            <flux:heading size="lg" class="mb-4">{{ __('Narrative / Description') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $blotter->narrative }}</flux:text>
        </div>

        {{-- Remarks --}}
        @if ($blotter->remarks)
            <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-4">{{ __('Remarks') }}</flux:heading>
                <flux:text>{{ $blotter->remarks }}</flux:text>
            </div>
        @endif

        {{-- Timeline --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Timeline') }}</flux:heading>

            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="mt-1 size-2 rounded-full bg-emerald-500"></div>
                    <div>
                        <flux:text class="font-medium">{{ __('Report Filed') }}</flux:text>
                        <flux:text class="text-sm text-zinc-500">{{ $blotter->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                </div>

                @if ($blotter->processed_at)
                    <div class="flex items-start gap-3">
                        <div class="mt-1 size-2 rounded-full bg-blue-500"></div>
                        <div>
                            <flux:text class="font-medium">{{ __('Processing Started') }}</flux:text>
                            <flux:text class="text-sm text-zinc-500">{{ $blotter->processed_at->format('M j, Y g:i A') }}</flux:text>
                            @if ($blotter->processor)
                                <flux:text class="text-sm text-zinc-500">{{ __('By') }}: {{ $blotter->processor->name }}</flux:text>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($blotter->completed_at)
                    <div class="flex items-start gap-3">
                        <div class="mt-1 size-2 rounded-full bg-green-500"></div>
                        <div>
                            <flux:text class="font-medium">{{ __('Completed') }}</flux:text>
                            <flux:text class="text-sm text-zinc-500">{{ $blotter->completed_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    </div>
                @endif

                @if ($blotter->rejected_at)
                    <div class="flex items-start gap-3">
                        <div class="mt-1 size-2 rounded-full bg-red-500"></div>
                        <div>
                            <flux:text class="font-medium">{{ __('Rejected') }}</flux:text>
                            <flux:text class="text-sm text-zinc-500">{{ $blotter->rejected_at->format('M j, Y g:i A') }}</flux:text>
                            @if ($blotter->rejection_reason)
                                <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $blotter->rejection_reason }}</flux:text>
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
                <flux:heading size="lg">{{ __('Complete Blotter Report') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Enter the Official Receipt number to complete this blotter report.') }}
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
                <flux:button variant="primary" wire:click="completeBlotter">
                    {{ __('Complete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Reject Modal --}}
    <flux:modal wire:model="showRejectModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reject Blotter Report') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Please provide a reason for rejecting this blotter report.') }}
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
                <flux:button variant="danger" wire:click="rejectBlotter">
                    {{ __('Reject') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Export Modal --}}
    <flux:modal wire:model="showExportModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Download Blotter Report') }}</flux:heading>
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
                <flux:button variant="primary" icon="arrow-down-tray" wire:click="downloadBlotter">
                    {{ __('Generate & Download') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

@script
<script>
    $wire.on('download-blotter', ({ url }) => {
        window.open(url, '_blank');
    });
</script>
@endscript

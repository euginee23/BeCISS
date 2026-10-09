<?php

use App\Mail\AppointmentCancelled;
use App\Mail\AppointmentCompleted;
use App\Mail\AppointmentConfirmed;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Notifications\ResidentNotification;
use App\Services\CertificateWorkflow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('View Appointment')]
#[Layout('layouts::app')]
class extends Component
{
    public Appointment $appointment;

    public bool $showCancelModal = false;

    public bool $showCompleteModal = false;

    public string $cancellationReason = '';

    public string $completionNotes = '';

    public bool $showPaymentModal = false;

    public string $orNumber = '';

    public function mount(Appointment $appointment): void
    {
        $this->appointment = $appointment->load('resident', 'handler', 'certificate.certificateType');
    }

    public function openPaymentModal(): void
    {
        $this->resetValidation();
        $this->orNumber = '';
        $this->showPaymentModal = true;
    }

    /**
     * Take payment for the linked certificate during the visit.
     */
    public function recordCertificatePayment(CertificateWorkflow $workflow): void
    {
        abort_unless($this->appointment->certificate && Auth::user()->hasPermission('payments'), 403);

        $this->validate(['orNumber' => ['required', 'string', 'max:50']]);

        $workflow->recordPayment($this->appointment->certificate, trim($this->orNumber), Auth::user());

        $this->showPaymentModal = false;
        $this->appointment->refresh()->load('resident', 'handler', 'certificate.certificateType');
    }

    /**
     * Release the linked certificate, which also completes this visit.
     */
    public function releaseCertificate(CertificateWorkflow $workflow): void
    {
        abort_unless($this->appointment->certificate && Auth::user()->hasPermission('certificates'), 403);

        $workflow->release($this->appointment->certificate);

        $this->appointment->refresh()->load('resident', 'handler', 'certificate.certificateType');
    }

    public function confirmAppointment(): void
    {
        abort_unless($this->appointment->status === 'scheduled', 422);

        $this->appointment->update([
            'status' => 'confirmed',
            'handled_by' => Auth::id(),
        ]);

        $this->appointment->refresh();
        $this->log('confirmed', 'Confirmed the appointment');
        $this->notifyResident(AppointmentConfirmed::class);
        $this->notifyResidentDatabase(
            type: 'appointment_confirmed',
            title: 'Appointment Confirmed',
            body: 'Your appointment for '.$this->appointment->service_type_label.' ('.$this->appointment->reference_number.') on '.$this->appointment->appointment_date->format('F j, Y').' at '.$this->appointment->appointment_time->format('g:i A').' has been confirmed.',
        );
    }

    public function openCompleteModal(): void
    {
        $this->showCompleteModal = true;
    }

    public function completeAppointment(): void
    {
        abort_unless(in_array($this->appointment->status, ['scheduled', 'confirmed'], true), 422);

        $notes = $this->appointment->notes;
        if ($this->completionNotes) {
            $notes = $notes ? $notes."\n\nCompletion: ".$this->completionNotes : $this->completionNotes;
        }

        $this->appointment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'handled_by' => Auth::id(),
            'notes' => $notes,
        ]);

        $this->showCompleteModal = false;
        $this->appointment->refresh();
        $this->log('completed', 'Completed the appointment');
        $this->notifyResident(AppointmentCompleted::class);
        $this->notifyResidentDatabase(
            type: 'appointment_completed',
            title: 'Appointment Completed',
            body: 'Your appointment for '.$this->appointment->service_type_label.' ('.$this->appointment->reference_number.') has been completed. Thank you for visiting!',
        );
    }

    public function markNoShow(): void
    {
        abort_unless($this->appointment->status === 'confirmed', 422);

        $this->appointment->update([
            'status' => 'no_show',
            'handled_by' => Auth::id(),
        ]);

        $this->appointment->refresh();
        $this->log('no_show', 'Marked as a no-show');
        $this->notifyResidentDatabase(
            type: 'appointment_no_show',
            title: 'Appointment Missed',
            body: 'You were marked as a no-show for your appointment ('.$this->appointment->reference_number.') on '.$this->appointment->appointment_date->format('F j, Y').'. Please book a new appointment if needed.',
        );
    }

    public function openCancelModal(): void
    {
        $this->showCancelModal = true;
    }

    public function cancelAppointment(): void
    {
        abort_unless(in_array($this->appointment->status, ['scheduled', 'confirmed'], true), 422);

        $this->appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'handled_by' => Auth::id(),
            'cancellation_reason' => $this->cancellationReason,
        ]);

        $this->showCancelModal = false;
        $this->appointment->refresh();
        $this->log('cancelled', 'Cancelled. Reason: '.$this->cancellationReason, ['reason' => $this->cancellationReason]);
        $this->notifyResident(AppointmentCancelled::class);
        $this->notifyResidentDatabase(
            type: 'appointment_cancelled',
            title: 'Appointment Cancelled',
            body: 'Your appointment for '.$this->appointment->service_type_label.' ('.$this->appointment->reference_number.') has been cancelled. Reason: '.$this->cancellationReason,
        );
    }

    /**
     * @param  array<string, mixed>|null  $properties
     */
    private function log(string $action, string $summary, ?array $properties = null): void
    {
        ActivityLog::record(
            module: 'appointments',
            action: $action,
            subject: $this->appointment,
            description: $summary.' — '.$this->appointment->service_type_label.' ('.$this->appointment->reference_number.').',
            properties: $properties,
        );
    }

    private function notifyResident(string $mailableClass): void
    {
        $user = $this->appointment->resident->user;

        if ($user) {
            Mail::to($user->email)->send(new $mailableClass($user, $this->appointment));
        }
    }

    private function notifyResidentDatabase(string $type, string $title, string $body): void
    {
        $user = $this->appointment->resident->user;

        $user?->notify(new ResidentNotification(
            type: $type,
            title: $title,
            body: $body,
            url: route('resident.appointments.index'),
        ));
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('appointments.index') }}">
            {{ __('Back to Appointments') }}
        </flux:button>

        @if (in_array($appointment->status, ['scheduled', 'confirmed']))
            <flux:button variant="primary" icon="pencil" href="{{ route('appointments.edit', $appointment) }}">
                {{ __('Edit') }}
            </flux:button>
        @endif
    </div>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" class="flex items-center gap-3">
                {{ $appointment->reference_number }}
                <flux:badge size="lg" :color="$appointment->status_color">
                    {{ $appointment->status_label }}
                </flux:badge>
            </flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ $appointment->service_type_label }}</flux:text>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap gap-2">
            @if ($appointment->status === 'scheduled')
                <flux:button variant="primary" wire:click="confirmAppointment">
                    {{ __('Confirm') }}
                </flux:button>
                <flux:button variant="danger" wire:click="openCancelModal">
                    {{ __('Cancel') }}
                </flux:button>
            @elseif ($appointment->status === 'confirmed')
                <flux:button variant="primary" wire:click="openCompleteModal">
                    {{ __('Mark Complete') }}
                </flux:button>
                <flux:button variant="ghost" wire:click="markNoShow">
                    {{ __('No Show') }}
                </flux:button>
                <flux:button variant="danger" wire:click="openCancelModal">
                    {{ __('Cancel') }}
                </flux:button>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Schedule Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Schedule') }}</flux:heading>

            <div class="flex items-center gap-4 mb-4 p-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                <flux:icon name="calendar" class="size-10 text-emerald-600" />
                <div>
                    <flux:heading size="base">{{ $appointment->appointment_date->format('l, F j, Y') }}</flux:heading>
                    <flux:text class="text-lg font-semibold text-emerald-600">
                        {{ $appointment->appointment_time->format('g:i A') }}
                    </flux:text>
                </div>
            </div>

            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Service Type') }}</dt>
                    <dd class="font-medium">{{ $appointment->service_type_label }}</dd>
                </div>
            </dl>
        </div>

        {{-- Resident Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Resident Information') }}</flux:heading>

            <div class="flex items-center gap-4 mb-4">
                <flux:avatar size="lg" name="{{ $appointment->resident->full_name }}" />
                <div>
                    <flux:heading size="base">{{ $appointment->resident->full_name }}</flux:heading>
                    <flux:text class="text-zinc-500">{{ $appointment->resident->address }}</flux:text>
                </div>
            </div>

            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Contact') }}</dt>
                    <dd class="font-medium">{{ $appointment->resident->contact_number ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Purok') }}</dt>
                    <dd class="font-medium">{{ $appointment->resident->purok ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Linked Certificate --}}
        @if ($certificate = $appointment->certificate)
            <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-6 dark:border-blue-900 dark:bg-blue-900/10 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ __('Certificate for this visit') }}</flux:heading>
                        <flux:text class="mt-1">
                            {{ $certificate->type_label }} ·
                            <span class="font-mono">{{ $certificate->certificate_number }}</span>
                        </flux:text>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" :color="$certificate->status_color">{{ $certificate->status_label }}</flux:badge>
                            @if ((float) $certificate->fee > 0)
                                <flux:badge size="sm" :color="$certificate->is_paid ? 'emerald' : 'amber'">
                                    ₱{{ number_format($certificate->fee, 2) }} · {{ $certificate->is_paid ? __('Paid (OR :or)', ['or' => $certificate->or_number]) : __('Unpaid') }}
                                </flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Free') }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($certificate->status === 'awaiting_payment' && auth()->user()->hasPermission('payments'))
                            <flux:button variant="primary" icon="banknotes" wire:click="openPaymentModal">{{ __('Record Payment') }}</flux:button>
                        @endif
                        @if ($certificate->status === 'ready_for_pickup' && auth()->user()->hasPermission('certificates'))
                            <flux:button variant="primary" icon="hand-raised" wire:click="releaseCertificate" wire:confirm="{{ __('Release the certificate and complete this visit?') }}">{{ __('Release Certificate') }}</flux:button>
                        @endif
                        @if (auth()->user()->hasPermission('certificates'))
                            <flux:button variant="ghost" icon="arrow-top-right-on-square" href="{{ route('certificates.show', $certificate) }}" wire:navigate>
                                {{ $certificate->status === 'processing' ? __('Prepare Certificate') : __('Open Certificate') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
                <flux:error name="status" class="mt-3" />
            </div>
        @endif

        {{-- Description --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Description') }}</flux:heading>
            <flux:text>{{ $appointment->description }}</flux:text>
        </div>

        {{-- Notes --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Notes') }}</flux:heading>
            <flux:text>{{ $appointment->notes ?? __('No notes added.') }}</flux:text>

            @if ($appointment->handler)
                <flux:separator class="my-4" />
                <div class="flex items-center gap-2 text-sm text-zinc-500">
                    <flux:icon name="user" class="size-4" />
                    {{ __('Handled by') }}: {{ $appointment->handler->name }}
                </div>
            @endif
        </div>

        {{-- Status Information --}}
        @if ($appointment->status === 'cancelled' && $appointment->cancellation_reason)
            <div class="rounded-lg border border-red-200 bg-red-50 p-6 dark:border-red-900 dark:bg-red-900/20 lg:col-span-2">
                <flux:heading size="lg" class="mb-2 text-red-900 dark:text-red-100">{{ __('Cancellation Reason') }}</flux:heading>
                <flux:text class="text-red-800 dark:text-red-200">{{ $appointment->cancellation_reason }}</flux:text>
                <flux:text class="mt-2 text-sm text-red-600 dark:text-red-400">
                    {{ __('Cancelled on') }}: {{ $appointment->cancelled_at->format('M j, Y g:i A') }}
                </flux:text>
            </div>
        @endif
    </div>

    {{-- Payment Modal --}}
    <flux:modal wire:model="showPaymentModal" class="max-w-sm">
        <form wire:submit="recordCertificatePayment" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Record Payment') }}</flux:heading>
                @if ($appointment->certificate)
                    <flux:text class="mt-2">{{ __('Amount due') }}: <strong>₱{{ number_format($appointment->certificate->fee, 2) }}</strong></flux:text>
                @endif
            </div>

            <flux:field>
                <flux:label>{{ __('OR Number') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="orNumber" placeholder="OR-XXXX-XXXX" required />
                <flux:error name="orNumber" />
            </flux:field>

            <flux:error name="status" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showPaymentModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Record Payment') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Complete Modal --}}
    <flux:modal wire:model="showCompleteModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Complete Appointment') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Add any final notes before completing this appointment.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Completion Notes') }}</flux:label>
                <flux:textarea wire:model="completionNotes" rows="3" placeholder="{{ __('Summary of the appointment...') }}" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCompleteModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" wire:click="completeAppointment">
                    {{ __('Complete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Cancel Modal --}}
    <flux:modal wire:model="showCancelModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Cancel Appointment') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Please provide a reason for cancelling this appointment.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Reason') }}</flux:label>
                <flux:textarea wire:model="cancellationReason" rows="3" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">
                    {{ __('Keep') }}
                </flux:button>
                <flux:button variant="danger" wire:click="cancelAppointment">
                    {{ __('Cancel Appointment') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

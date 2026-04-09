<?php

use App\Mail\AppointmentCancelled;
use App\Mail\AppointmentCompleted;
use App\Mail\AppointmentConfirmed;
use App\Models\Appointment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('View Appointment')]
#[Layout('layouts::app')]
class extends Component {
    public Appointment $appointment;

    public bool $showCancelModal = false;

    public bool $showCompleteModal = false;

    public string $cancellationReason = '';

    public string $completionNotes = '';

    public function mount(Appointment $appointment): void
    {
        $this->appointment = $appointment->load('resident', 'handler');
    }

    public function confirmAppointment(): void
    {
        $this->appointment->update([
            'status' => 'confirmed',
            'handled_by' => Auth::id(),
        ]);

        $this->appointment->refresh();
        $this->notifyResident(AppointmentConfirmed::class);
    }

    public function startAppointment(): void
    {
        $this->appointment->update([
            'status' => 'in_progress',
            'handled_by' => Auth::id(),
        ]);

        $this->appointment->refresh();
    }

    public function showCompleteModal(): void
    {
        $this->showCompleteModal = true;
    }

    public function completeAppointment(): void
    {
        $notes = $this->appointment->notes;
        if ($this->completionNotes) {
            $notes = $notes ? $notes . "\n\nCompletion: " . $this->completionNotes : $this->completionNotes;
        }

        $this->appointment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'notes' => $notes,
        ]);

        $this->showCompleteModal = false;
        $this->appointment->refresh();
        $this->notifyResident(AppointmentCompleted::class);
    }

    public function markNoShow(): void
    {
        $this->appointment->update([
            'status' => 'no_show',
        ]);

        $this->appointment->refresh();
    }

    public function showCancelModal(): void
    {
        $this->showCancelModal = true;
    }

    public function cancelAppointment(): void
    {
        $this->appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $this->cancellationReason,
        ]);

        $this->showCancelModal = false;
        $this->appointment->refresh();
        $this->notifyResident(AppointmentCancelled::class);
    }

    private function notifyResident(string $mailableClass): void
    {
        $user = $this->appointment->resident->user;

        if ($user) {
            Mail::to($user->email)->send(new $mailableClass($user, $this->appointment));
        }
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
                <flux:button variant="danger" wire:click="showCancelModal">
                    {{ __('Cancel') }}
                </flux:button>
            @elseif ($appointment->status === 'confirmed')
                <flux:button variant="primary" wire:click="startAppointment">
                    {{ __('Start') }}
                </flux:button>
                <flux:button variant="ghost" wire:click="markNoShow">
                    {{ __('No Show') }}
                </flux:button>
                <flux:button variant="danger" wire:click="showCancelModal">
                    {{ __('Cancel') }}
                </flux:button>
            @elseif ($appointment->status === 'in_progress')
                <flux:button variant="primary" wire:click="showCompleteModal">
                    {{ __('Complete') }}
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
                    <dt class="text-zinc-500">{{ __('Duration') }}</dt>
                    <dd class="font-medium">{{ $appointment->duration_minutes }} {{ __('minutes') }}</dd>
                </div>
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

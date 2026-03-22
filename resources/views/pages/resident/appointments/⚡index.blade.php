<?php

use App\Models\Appointment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('My Appointments')]
#[Layout('layouts::app')]
class extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'upcoming';

    public bool $showCancelModal = false;
    public ?int $appointmentToCancel = null;
    public string $cancellationReason = '';

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function resident()
    {
        return auth()->user()->resident;
    }

    #[Computed]
    public function appointments()
    {
        $resident = $this->resident;

        if (! $resident) {
            return null;
        }

        return $resident->appointments()
            ->when($this->tab === 'upcoming', fn ($q) => $q->upcoming())
            ->when($this->tab === 'past', fn ($q) => $q->where('appointment_date', '<', today())->whereIn('status', ['completed', 'no_show', 'cancelled']))
            ->latest('appointment_date')
            ->paginate(10);
    }

    public function confirmCancel(int $id): void
    {
        $this->appointmentToCancel = $id;
        $this->cancellationReason = '';
        $this->showCancelModal = true;
    }

    public function cancelAppointment(): void
    {
        $this->validate([
            'cancellationReason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        if ($this->appointmentToCancel) {
            $appt = $this->resident?->appointments()->find($this->appointmentToCancel);

            if ($appt && in_array($appt->status, ['scheduled', 'confirmed'])) {
                $appt->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $this->cancellationReason,
                    'cancelled_at' => now(),
                ]);
            }
        }

        $this->showCancelModal = false;
        $this->appointmentToCancel = null;
        $this->cancellationReason = '';
        unset($this->appointments);
    }
};
?>

<div class="flex flex-col gap-6">

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">My Appointments</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">View and manage your scheduled service appointments.</flux:text>
        </div>
    </div>

    @php $resident = $this->resident; @endphp

    @if(! $resident)
        <div class="rounded-xl border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-6">
            <div class="flex items-start gap-4">
                <div class="size-10 rounded-lg bg-amber-100 dark:bg-amber-800/50 flex items-center justify-center shrink-0">
                    <flux:icon name="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <flux:heading>Profile Not Linked</flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-300 mt-1">
                        Your account is not yet linked to a resident profile. Please visit the barangay hall or contact staff to have your account linked before you can view appointments.
                    </flux:text>
                </div>
            </div>
        </div>
    @else

        {{-- Tabs --}}
        <div class="flex gap-1 border-b border-zinc-200 dark:border-zinc-700">
            <button
                wire:click="$set('tab', 'upcoming')"
                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $tab === 'upcoming' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-200' }}"
            >Upcoming</button>
            <button
                wire:click="$set('tab', 'past')"
                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $tab === 'past' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-200' }}"
            >Past</button>
        </div>

        {{-- Appointments List --}}
        @if($this->appointments && $this->appointments->isNotEmpty())
            <div class="flex flex-col gap-3">
                @foreach($this->appointments as $appt)
                    <div wire:key="{{ $appt->id }}" class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-semibold text-zinc-900 dark:text-white">{{ $appt->service_type_label }}</span>
                                    <flux:badge :color="match($appt->status) {
                                        'scheduled' => 'zinc',
                                        'confirmed' => 'blue',
                                        'in_progress' => 'yellow',
                                        'completed' => 'green',
                                        'cancelled' => 'red',
                                        'no_show' => 'orange',
                                        default => 'zinc'
                                    }" size="sm">{{ $appt->status_label }}</flux:badge>
                                </div>

                                <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-3">{{ $appt->description }}</p>

                                <div class="flex items-center gap-4 flex-wrap text-sm text-zinc-600 dark:text-zinc-300">
                                    <span class="flex items-center gap-1.5">
                                        <flux:icon name="calendar" class="size-4 text-zinc-400" />
                                        {{ $appt->appointment_date->format('F d, Y') }}
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <flux:icon name="clock" class="size-4 text-zinc-400" />
                                        {{ $appt->appointment_time->format('h:i A') }} ({{ $appt->duration_minutes }} min)
                                    </span>
                                    <span class="flex items-center gap-1.5 font-mono text-xs text-zinc-400">
                                        Ref: {{ $appt->reference_number }}
                                    </span>
                                </div>

                                @if($appt->cancellation_reason)
                                    <div class="mt-3 text-sm text-red-600 dark:text-red-400">
                                        <span class="font-medium">Cancellation reason:</span> {{ $appt->cancellation_reason }}
                                    </div>
                                @endif
                            </div>

                            @if(in_array($appt->status, ['scheduled', 'confirmed']))
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    class="text-red-600 dark:text-red-400 hover:text-red-700 shrink-0"
                                    wire:click="confirmCancel({{ $appt->id }})"
                                >
                                    Cancel
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if($this->appointments->hasPages())
                    <div class="mt-2">
                        {{ $this->appointments->links() }}
                    </div>
                @endif
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
                <div class="flex flex-col items-center justify-center py-16 text-center gap-3">
                    <div class="size-14 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                        <flux:icon name="calendar" class="size-7 text-zinc-400" />
                    </div>
                    <div>
                        <flux:heading>
                            {{ $tab === 'upcoming' ? 'No upcoming appointments' : 'No past appointments' }}
                        </flux:heading>
                        <flux:text class="text-zinc-400 mt-1">
                            {{ $tab === 'upcoming' ? 'You have no scheduled or confirmed appointments.' : 'You have no completed or past appointments.' }}
                        </flux:text>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- Cancel Modal --}}
    <flux:modal wire:model="showCancelModal" class="w-full max-w-md">
        <div class="flex flex-col gap-5">
            <div>
                <flux:heading>Cancel Appointment</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">
                    Please provide a reason for cancellation. This will be recorded.
                </flux:text>
            </div>

            <flux:field>
                <flux:label>Reason for cancellation <flux:badge size="sm" color="red" class="ml-1">Required</flux:badge></flux:label>
                <flux:textarea wire:model="cancellationReason" placeholder="Please explain why you are cancelling this appointment..." rows="3" />
                <flux:error name="cancellationReason" />
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">Keep Appointment</flux:button>
                <flux:button variant="danger" wire:click="cancelAppointment" wire:loading.attr="disabled">
                    <span wire:loading.remove>Cancel Appointment</span>
                    <span wire:loading>Cancelling...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>

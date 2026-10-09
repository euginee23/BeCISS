<?php

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Certificate;
use App\Models\User;
use App\Notifications\ResidentNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Title('Book Appointment')]
#[Layout('layouts::app')]
class extends Component {
    #[Url(as: 'certificate')]
    public ?int $certificate_id = null;

    public string $service_type = '';
    public string $description = '';
    public string $appointment_date = '';
    public string $appointment_time = '';
    public string $notes = '';

    public function mount(): void
    {
        if (! $this->certificate_id) {
            return;
        }

        $certificate = $this->linkableCertificate();

        if (! $certificate) {
            $this->certificate_id = null;

            return;
        }

        $this->service_type = Service::CERTIFICATE_VISIT;
        $this->description = ($certificate->requiresPayment() ? 'Payment and pickup of ' : 'Pickup of ')
            .$certificate->type_label.' ('.$certificate->certificate_number.')';
    }

    /**
     * The resident's certificate this visit is for, if it still needs one.
     */
    #[Computed]
    public function linkableCertificate(): ?Certificate
    {
        if (! $this->certificate_id) {
            return null;
        }

        $certificate = auth()->user()->resident?->certificates()->find($this->certificate_id);

        return $certificate && $certificate->needsVisit() && ! $certificate->activeAppointment()
            ? $certificate
            : null;
    }

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'service_type' => ['required', Rule::in([
                ...$this->services->pluck('slug')->all(),
                ...($this->certificate_id ? [Service::CERTIFICATE_VISIT] : []),
            ])],
            'description' => ['required', 'string', 'max:1000'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.Appointment::maxBookingDate()],
            'appointment_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $resident = auth()->user()->resident;

        abort_unless($resident, 403);

        if ($this->certificate_id) {
            unset($this->linkableCertificate);

            abort_unless($this->linkableCertificate, 422, __('This certificate does not need a visit or already has one scheduled.'));

            $validated['certificate_id'] = $this->certificate_id;
            $validated['service_type'] = Service::CERTIFICATE_VISIT;
        }

        $validated['resident_id'] = $resident->id;
        $validated['reference_number'] = Appointment::generateReferenceNumber();

        $appointment = Appointment::create($validated);

        // Staff had no signal that a resident booking had arrived.
        User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->get()
            ->filter(fn (User $staff): bool => $staff->hasPermission('appointments'))
            ->each(fn (User $staff) => $staff->notify(new ResidentNotification(
                type: 'appointment_requested',
                title: 'New Appointment Booked',
                body: $resident->full_name.' booked '.$appointment->service_type_label.' on '.$appointment->appointment_date->format('F j, Y').' at '.$appointment->appointment_time->format('g:i A').'. Reference: '.$appointment->reference_number.'.',
                url: route('appointments.show', $appointment),
            )));

        session()->flash('status', __('Appointment booked successfully.'));

        $this->redirect(route('resident.appointments.index'), navigate: true);
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->bookable()->ordered()->get();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function timeSlots(): array
    {
        return Appointment::timeSlots();
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('resident.appointments.index') }}">
            {{ __('Back to My Appointments') }}
        </flux:button>
    </div>

    <div class="mb-6">
        <flux:heading size="xl">{{ __('Book Appointment') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Schedule a new appointment with the barangay') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-8">
        {{-- Service Details --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Service Details') }}</flux:heading>

            @if ($this->linkableCertificate)
                <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/20">
                    <flux:text class="font-medium text-blue-900 dark:text-blue-100">{{ $this->linkableCertificate->type_label }} · {{ $this->linkableCertificate->certificate_number }}</flux:text>
                    <flux:text class="text-sm text-blue-800 dark:text-blue-200">
                        @if ($this->linkableCertificate->requiresPayment())
                            {{ __('Bring ₱:amount for the processing fee and a valid ID.', ['amount' => number_format($this->linkableCertificate->fee, 2)]) }}
                        @else
                            {{ __('Bring a valid ID to claim your certificate.') }}
                        @endif
                    </flux:text>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field @class(['hidden' => $this->linkableCertificate])>
                    <flux:label>{{ __('Service Type') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="service_type" required>
                        <option value="">{{ __('Select service') }}</option>
                        @foreach ($this->services as $service)
                            <option value="{{ $service->slug }}">{{ $service->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="service_type" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Description') }} <span class="text-red-500">*</span></flux:label>
                    <flux:textarea wire:model="description" rows="3" required placeholder="{{ __('Describe the purpose of the appointment') }}" />
                    <flux:error name="description" />
                </flux:field>
            </div>
        </div>

        {{-- Schedule --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Schedule') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Date') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="appointment_date" type="date" required min="{{ now()->toDateString() }}" max="{{ \App\Models\Appointment::maxBookingDate() }}" />
                    <flux:description>{{ __('Appointments can be booked up to :days days ahead.', ['days' => \App\Models\Appointment::MAX_ADVANCE_DAYS]) }}</flux:description>
                    <flux:error name="appointment_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Time') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="appointment_time" required>
                        <option value="">{{ __('Select time') }}</option>
                        @foreach ($this->timeSlots as $slot)
                            <option value="{{ $slot }}">{{ \Carbon\Carbon::parse($slot)->format('g:i A') }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="appointment_time" />
                </flux:field>
            </div>
        </div>

        {{-- Additional Notes --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Additional Notes') }}</flux:heading>

            <flux:field>
                <flux:textarea wire:model="notes" rows="3" placeholder="{{ __('Any additional notes or instructions') }}" />
                <flux:error name="notes" />
            </flux:field>
        </div>

        {{-- Form Actions --}}
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" href="{{ route('resident.appointments.index') }}">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Book Appointment') }}
            </flux:button>
        </div>
    </form>
</div>

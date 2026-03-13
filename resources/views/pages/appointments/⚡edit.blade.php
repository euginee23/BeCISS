<?php

use App\Models\Appointment;
use App\Models\Resident;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Edit Appointment')]
#[Layout('layouts::app')]
class extends Component {
    public Appointment $appointment;

    public ?int $resident_id = null;
    public string $service_type = '';
    public string $description = '';
    public string $appointment_date = '';
    public string $appointment_time = '';
    public int $duration_minutes = 30;
    public string $notes = '';

    public function mount(Appointment $appointment): void
    {
        $this->appointment = $appointment;
        $this->resident_id = $appointment->resident_id;
        $this->service_type = $appointment->service_type;
        $this->description = $appointment->description;
        $this->appointment_date = $appointment->appointment_date->format('Y-m-d');
        $this->appointment_time = $appointment->appointment_time->format('H:i');
        $this->duration_minutes = $appointment->duration_minutes;
        $this->notes = $appointment->notes ?? '';
    }

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'resident_id' => ['required', 'exists:residents,id'],
            'service_type' => ['required', 'in:' . implode(',', array_keys(Appointment::SERVICE_TYPES))],
            'description' => ['required', 'string', 'max:1000'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $this->appointment->update($validated);

        session()->flash('status', __('Appointment updated successfully.'));

        $this->redirect(route('appointments.show', $this->appointment), navigate: true);
    }

    #[Computed]
    public function residents()
    {
        return Resident::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function timeSlots(): array
    {
        $slots = [];
        for ($hour = 8; $hour < 17; $hour++) {
            $slots[] = sprintf('%02d:00', $hour);
            $slots[] = sprintf('%02d:30', $hour);
        }

        return $slots;
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('appointments.show', $appointment) }}">
            {{ __('Back to Appointment') }}
        </flux:button>
    </div>

    <div class="mb-6">
        <flux:heading size="xl">{{ __('Edit Appointment') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ $appointment->reference_number }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-8">
        {{-- Resident Selection --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Resident Information') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Resident') }} <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model="resident_id" required>
                    <option value="">{{ __('Select a resident') }}</option>
                    @foreach ($this->residents as $resident)
                        <option value="{{ $resident->id }}">{{ $resident->full_name }} ({{ $resident->address }})</option>
                    @endforeach
                </flux:select>
                <flux:error name="resident_id" />
            </flux:field>
        </div>

        {{-- Service Details --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Service Details') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Service Type') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="service_type" required>
                        <option value="">{{ __('Select service') }}</option>
                        @foreach (App\Models\Appointment::SERVICE_TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="service_type" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Duration') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="duration_minutes" required>
                        <option value="15">15 {{ __('minutes') }}</option>
                        <option value="30">30 {{ __('minutes') }}</option>
                        <option value="45">45 {{ __('minutes') }}</option>
                        <option value="60">1 {{ __('hour') }}</option>
                        <option value="90">1.5 {{ __('hours') }}</option>
                        <option value="120">2 {{ __('hours') }}</option>
                    </flux:select>
                    <flux:error name="duration_minutes" />
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
                    <flux:input wire:model="appointment_date" type="date" required />
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
            <flux:button variant="ghost" href="{{ route('appointments.show', $appointment) }}">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Update Appointment') }}
            </flux:button>
        </div>
    </form>
</div>

<?php

use App\Models\Blotter;
use App\Models\Resident;
use App\Models\ServiceFee;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('New Blotter Report')]
#[Layout('layouts::app')]
class extends Component
{
    public string $complainantType = 'registered';

    public ?int $resident_id = null;

    // Walk-in complainant fields
    public string $complainant_name = '';

    public string $complainant_purok = '';

    public string $complainant_street = '';

    public string $complainant_house_number = '';

    public string $complainant_contact = '';

    // Incident details
    public string $incident_type = '';

    public string $incident_type_other = '';

    public string $incident_datetime = '';

    public string $incident_location = '';

    public string $owner_name = '';

    public string $respondent_name = '';

    public string $narrative = '';

    public string $remarks = '';

    public string $or_number = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'incident_type' => ['required', 'in:' . implode(',', array_keys(Blotter::TYPES))],
            'incident_type_other' => ['nullable', 'string', 'max:255', 'required_if:incident_type,other'],
            'incident_datetime' => ['required', 'date'],
            'incident_location' => ['nullable', 'string', 'max:500'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'respondent_name' => ['nullable', 'string', 'max:255'],
            'narrative' => ['required', 'string', 'max:5000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'or_number' => ['nullable', 'string', 'max:50'],
        ];

        if ($this->complainantType === 'registered') {
            $rules['resident_id'] = ['required', 'exists:residents,id'];
        } else {
            $rules['complainant_name'] = ['required', 'string', 'max:255'];
            $rules['complainant_purok'] = ['nullable', 'string', 'max:100'];
            $rules['complainant_street'] = ['nullable', 'string', 'max:255'];
            $rules['complainant_house_number'] = ['nullable', 'string', 'max:50'];
            $rules['complainant_contact'] = ['nullable', 'string', 'max:20'];
        }

        return $rules;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $data = [
            'blotter_number' => Blotter::generateBlotterNumber(),
            'fee' => ServiceFee::getFee('blotter'),
            'incident_type' => $this->incident_type,
            'incident_type_other' => $this->incident_type === 'other' ? $this->incident_type_other : null,
            'incident_datetime' => $this->incident_datetime,
            'incident_location' => $this->incident_location ?: null,
            'owner_name' => $this->owner_name ?: null,
            'respondent_name' => $this->respondent_name ?: null,
            'narrative' => $this->narrative,
            'remarks' => $this->remarks ?: null,
            'or_number' => $this->or_number ?: null,
        ];

        if ($this->complainantType === 'registered') {
            $data['resident_id'] = $this->resident_id;
        } else {
            $data['complainant_name'] = $this->complainant_name;
            $data['complainant_purok'] = $this->complainant_purok ?: null;
            $data['complainant_street'] = $this->complainant_street ?: null;
            $data['complainant_house_number'] = $this->complainant_house_number ?: null;
            $data['complainant_contact'] = $this->complainant_contact ?: null;
        }

        Blotter::create($data);

        session()->flash('status', __('Blotter report created successfully.'));

        $this->redirect(route('blotters.index'), navigate: true);
    }

    #[Computed]
    public function residents()
    {
        return Resident::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('blotters.index') }}">
            {{ __('Back to Blotters') }}
        </flux:button>
    </div>

    <div class="mb-6">
        <flux:heading size="xl">{{ __('New Blotter Report') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Create a new blotter report') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-8">

        {{-- Complainant Section --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Complainant') }}</flux:heading>

            <flux:field class="mb-4">
                <flux:label>{{ __('Complainant Type') }}</flux:label>
                <flux:radio.group wire:model.live="complainantType">
                    <flux:radio value="registered" label="{{ __('Registered Resident') }}" />
                    <flux:radio value="walkin" label="{{ __('Walk-in / Unregistered') }}" />
                </flux:radio.group>
            </flux:field>

            @if ($complainantType === 'registered')
                <flux:field>
                    <flux:label>{{ __('Resident') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="resident_id" required>
                        <option value="">{{ __('Select a resident') }}</option>
                        @foreach ($this->residents as $resident)
                            <option value="{{ $resident->id }}">{{ $resident->full_name }} — {{ $resident->address }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="resident_id" />
                </flux:field>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field class="sm:col-span-2">
                        <flux:label>{{ __('Full Name') }} <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="complainant_name" placeholder="{{ __('Complainant\'s full name') }}" required />
                        <flux:error name="complainant_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Purok / Zone') }}</flux:label>
                        <flux:input wire:model="complainant_purok" placeholder="{{ __('e.g. Purok 3') }}" />
                        <flux:error name="complainant_purok" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('House Number') }}</flux:label>
                        <flux:input wire:model="complainant_house_number" placeholder="{{ __('e.g. 123') }}" />
                        <flux:error name="complainant_house_number" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>{{ __('Street / Barangay') }}</flux:label>
                        <flux:input wire:model="complainant_street" placeholder="{{ __('Street name or barangay') }}" />
                        <flux:error name="complainant_street" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Contact Number') }}</flux:label>
                        <flux:input wire:model="complainant_contact" type="tel" placeholder="{{ __('e.g. 09XX-XXX-XXXX') }}" />
                        <flux:error name="complainant_contact" />
                    </flux:field>
                </div>
            @endif
        </div>

        {{-- Incident Details --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Incident Details') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Incident Type') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model.live="incident_type" required>
                        <option value="">{{ __('Select type') }}</option>
                        @foreach (App\Models\Blotter::TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="incident_type" />
                </flux:field>

                @if ($incident_type === 'other')
                    <flux:field>
                        <flux:label>{{ __('Specify Incident Type') }} <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="incident_type_other" placeholder="{{ __('Describe the incident type') }}" required />
                        <flux:error name="incident_type_other" />
                    </flux:field>
                @else
                    <div></div>
                @endif

                <flux:field>
                    <flux:label>{{ __('Date & Time of Incident') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input type="datetime-local" wire:model="incident_datetime" required />
                    <flux:error name="incident_datetime" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Location of Incident') }}</flux:label>
                    <flux:input wire:model="incident_location" placeholder="{{ __('Where did the incident occur?') }}" />
                    <flux:error name="incident_location" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Respondent Name') }}</flux:label>
                    <flux:input wire:model="respondent_name" placeholder="{{ __('Name of the person involved') }}" />
                    <flux:error name="respondent_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Property / Establishment Owner') }}</flux:label>
                    <flux:input wire:model="owner_name" placeholder="{{ __('If applicable') }}" />
                    <flux:error name="owner_name" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Narrative / Description') }} <span class="text-red-500">*</span></flux:label>
                    <flux:textarea wire:model="narrative" rows="5" placeholder="{{ __('Describe in detail what happened...') }}" />
                    <flux:error name="narrative" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Remarks') }}</flux:label>
                    <flux:textarea wire:model="remarks" rows="2" placeholder="{{ __('Any additional notes') }}" />
                    <flux:error name="remarks" />
                </flux:field>
            </div>
        </div>

        {{-- Payment --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Payment') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-900/20">
                    <flux:text class="text-sm text-emerald-700 dark:text-emerald-400">{{ __('Processing Fee') }}</flux:text>
                    <flux:text class="text-2xl font-bold text-emerald-600">
                        ₱{{ number_format(\App\Models\ServiceFee::getFee('blotter'), 2) }}
                    </flux:text>
                </div>

                <flux:field>
                    <flux:label>{{ __('OR Number') }} <flux:text class="text-zinc-400 text-xs">({{ __('optional — if paid on filing') }})</flux:text></flux:label>
                    <flux:input wire:model="or_number" placeholder="OR-XXXX-XXXX" />
                    <flux:error name="or_number" />
                </flux:field>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" href="{{ route('blotters.index') }}">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Submit Report') }}
            </flux:button>
        </div>
    </form>
</div>

<?php

use App\Models\Certificate;
use App\Models\Resident;
use App\Models\ServiceFee;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('New Certificate Request')]
#[Layout('layouts::app')]
class extends Component
{
    public string $complainantType = 'registered';

    public ?int $resident_id = null;

    public string $walkin_name = '';

    public string $walkin_purok = '';

    public string $walkin_street = '';

    public string $walkin_house_number = '';

    public string $walkin_contact = '';

    public string $type = '';

    public string $purpose = '';

    public string $purpose_other = '';

    public string $remarks = '';

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'type' => ['required', 'in:'.implode(',', array_keys(Certificate::TYPES))],
            'purpose' => ['required', Rule::in(Certificate::PURPOSE_OPTIONS)],
            'purpose_other' => ['nullable', 'string', 'max:255', 'required_if:purpose,Other'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->complainantType === 'registered') {
            $rules['resident_id'] = ['required', 'exists:residents,id'];
        } else {
            $rules['walkin_name'] = ['required', 'string', 'max:255'];
            $rules['walkin_purok'] = ['nullable', 'string', 'max:100'];
            $rules['walkin_street'] = ['nullable', 'string', 'max:255'];
            $rules['walkin_house_number'] = ['nullable', 'string', 'max:50'];
            $rules['walkin_contact'] = ['nullable', 'string', 'max:20'];
        }

        return $rules;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'certificate_number' => Certificate::generateCertificateNumber(),
            'type' => $this->type,
            'purpose' => $this->purpose,
            'purpose_other' => $this->purpose === 'Other' ? $this->purpose_other : null,
            'remarks' => $this->remarks ?: null,
            'fee' => ServiceFee::getFee($this->type),
        ];

        if ($this->complainantType === 'registered') {
            $data['resident_id'] = $this->resident_id;
            $data['is_walkin'] = false;
            $data['walkin_name'] = null;
            $data['walkin_purok'] = null;
            $data['walkin_street'] = null;
            $data['walkin_house_number'] = null;
            $data['walkin_contact'] = null;
        } else {
            $data['resident_id'] = null;
            $data['is_walkin'] = true;
            $data['walkin_name'] = $this->walkin_name;
            $data['walkin_purok'] = $this->walkin_purok ?: null;
            $data['walkin_street'] = $this->walkin_street ?: null;
            $data['walkin_house_number'] = $this->walkin_house_number ?: null;
            $data['walkin_contact'] = $this->walkin_contact ?: null;
        }

        Certificate::create($data);

        session()->flash('status', __('Certificate request created successfully.'));

        $this->redirect(route('certificates.index'), navigate: true);
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
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('certificates.index') }}">
            {{ __('Back to Certificates') }}
        </flux:button>
    </div>

    <div class="mb-6">
        <flux:heading size="xl">{{ __('New Certificate Request') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Create a new certificate request for a registered resident or walk-in requester') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-8">
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Request Details') }}</flux:heading>

            <flux:field class="mb-4">
                <flux:label>{{ __('Requester Type') }}</flux:label>
                <flux:radio.group wire:model.live="complainantType">
                    <flux:radio value="registered" label="{{ __('Registered Resident') }}" />
                    <flux:radio value="walkin" label="{{ __('Walk-in / Unregistered') }}" />
                </flux:radio.group>
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                @if ($complainantType === 'registered')
                    <flux:field class="sm:col-span-2">
                        <flux:label>{{ __('Resident') }} <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model="resident_id" required>
                            <option value="">{{ __('Select a resident') }}</option>
                            @foreach ($this->residents as $resident)
                                <option value="{{ $resident->id }}">{{ $resident->full_name }} ({{ $resident->address }})</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="resident_id" />
                    </flux:field>
                @else
                    <flux:field class="sm:col-span-2">
                        <flux:label>{{ __('Full Name') }} <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="walkin_name" placeholder="{{ __('Requester\'s full name') }}" required />
                        <flux:error name="walkin_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Purok / Zone') }}</flux:label>
                        <flux:input wire:model="walkin_purok" placeholder="{{ __('e.g. Purok 3') }}" />
                        <flux:error name="walkin_purok" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('House Number') }}</flux:label>
                        <flux:input wire:model="walkin_house_number" placeholder="{{ __('e.g. 123') }}" />
                        <flux:error name="walkin_house_number" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>{{ __('Street / Barangay') }}</flux:label>
                        <flux:input wire:model="walkin_street" placeholder="{{ __('Street name or barangay') }}" />
                        <flux:error name="walkin_street" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Contact Number') }}</flux:label>
                        <flux:input wire:model="walkin_contact" type="tel" placeholder="{{ __('e.g. 09XX-XXX-XXXX') }}" />
                        <flux:error name="walkin_contact" />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>{{ __('Certificate Type') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="type" required>
                        <option value="">{{ __('Select type') }}</option>
                        @foreach (App\Models\Certificate::TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="type" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Purpose') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model.live="purpose" required>
                        <option value="">{{ __('Select purpose') }}</option>
                        @foreach (Certificate::PURPOSE_OPTIONS as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="purpose" />
                </flux:field>

                @if ($purpose === 'Other')
                    <flux:field class="sm:col-span-2">
                        <flux:label>{{ __('Specify Purpose') }} <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="purpose_other" placeholder="{{ __('Enter the specific reason') }}" required />
                        <flux:error name="purpose_other" />
                    </flux:field>
                @endif

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Remarks') }}</flux:label>
                    <flux:textarea wire:model="remarks" rows="3" placeholder="{{ __('Any additional notes or special requirements') }}" />
                    <flux:error name="remarks" />
                </flux:field>
            </div>
        </div>

        {{-- Fee Information --}}
        @if ($type)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-900/20">
                <div class="flex items-center gap-3">
                    <flux:icon name="banknotes" class="size-5 text-emerald-600" />
                    <div>
                        <flux:text class="font-medium text-emerald-900 dark:text-emerald-100">
                            {{ __('Processing Fee') }}
                        </flux:text>
                        <flux:text class="text-2xl font-bold text-emerald-600">
                            ₱{{ number_format(\App\Models\ServiceFee::getFee($type), 2) }}
                        </flux:text>
                    </div>
                </div>
            </div>
        @endif

        {{-- Form Actions --}}
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" href="{{ route('certificates.index') }}">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Submit Request') }}
            </flux:button>
        </div>
    </form>
</div>

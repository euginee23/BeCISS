<?php

use App\Models\ActivityLog;
use App\Models\Resident;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Edit Resident')]
#[Layout('layouts::app')]
class extends Component {
    public Resident $resident;

    public string $first_name = '';
    public string $middle_name = '';
    public string $last_name = '';
    public string $suffix = '';
    public string $birthdate = '';
    public string $gender = '';
    public string $civil_status = '';
    public string $contact_number = '';
    public string $house_number = '';
    public string $street = '';
    public string $purok = '';
    public string $residency_start_date = '';
    public string $occupation = '';
    public ?float $monthly_income = null;
    public bool $is_voter = false;
    public ?int $household_head_id = null;

    public function mount(Resident $resident): void
    {
        $this->resident = $resident;
        $this->first_name = $resident->first_name;
        $this->middle_name = $resident->middle_name ?? '';
        $this->last_name = $resident->last_name;
        $this->suffix = $resident->suffix ?? '';
        $this->birthdate = $resident->birthdate->format('Y-m-d');
        $this->gender = $resident->gender;
        $this->civil_status = $resident->civil_status;
        $this->contact_number = $resident->contact_number ?? '';
        $this->house_number = $resident->house_number ?? '';
        $this->street = $resident->street ?? '';
        $this->purok = $resident->purok ?? '';
        $this->residency_start_date = $resident->residency_start_date?->format('Y-m-d') ?? '';
        $this->occupation = $resident->occupation ?? '';
        $this->monthly_income = $resident->monthly_income;
        $this->is_voter = $resident->is_voter;
        $this->household_head_id = $resident->household_head_id;
    }

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'birthdate' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'civil_status' => ['required', Rule::in(['single', 'married', 'widowed', 'separated'])],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:255'],
            'purok' => ['required', Rule::in(Resident::PUROKS)],
            'residency_start_date' => ['required', 'date', 'before_or_equal:today'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
            'is_voter' => ['boolean'],
            'household_head_id' => ['nullable', 'exists:residents,id'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $this->resident->update($validated);

        ActivityLog::record(
            module: 'residents',
            action: 'updated',
            subject: $this->resident,
            description: 'Updated resident '.$this->resident->full_name.'.',
        );

        session()->flash('status', __('Resident updated successfully.'));

        $this->redirect(route('residents.index'), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('residents.index') }}">
            {{ __('Back to Residents') }}
        </flux:button>
    </div>

    <div class="mb-6">
        <flux:heading size="xl">{{ __('Edit Resident') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Update resident information') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-8">
        {{-- Personal Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Personal Information') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:field class="sm:col-span-1">
                    <flux:label>{{ __('First Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="first_name" required x-capitalize />
                    <flux:error name="first_name" />
                </flux:field>

                <flux:field class="sm:col-span-1">
                    <flux:label>{{ __('Middle Name') }}</flux:label>
                    <flux:input wire:model="middle_name" x-capitalize />
                    <flux:error name="middle_name" />
                </flux:field>

                <flux:field class="sm:col-span-1">
                    <flux:label>{{ __('Last Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="last_name" required x-capitalize />
                    <flux:error name="last_name" />
                </flux:field>

                <flux:field class="sm:col-span-1">
                    <flux:label>{{ __('Suffix') }}</flux:label>
                    <flux:input wire:model="suffix" placeholder="Jr., Sr., III" />
                    <flux:error name="suffix" />
                </flux:field>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Birthdate') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="birthdate" type="date" required />
                    <flux:error name="birthdate" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Gender') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="gender" required>
                        <option value="">{{ __('Select gender') }}</option>
                        <option value="male">{{ __('Male') }}</option>
                        <option value="female">{{ __('Female') }}</option>
                    </flux:select>
                    <flux:error name="gender" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Civil Status') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="civil_status" required>
                        <option value="">{{ __('Select status') }}</option>
                        <option value="single">{{ __('Single') }}</option>
                        <option value="married">{{ __('Married') }}</option>
                        <option value="widowed">{{ __('Widowed') }}</option>
                        <option value="separated">{{ __('Separated') }}</option>
                    </flux:select>
                    <flux:error name="civil_status" />
                </flux:field>
            </div>

            <div class="mt-4">
                <flux:field class="max-w-sm">
                    <flux:label>{{ __('Contact Number') }}</flux:label>
                    <flux:input wire:model="contact_number" type="tel" placeholder="+63 9XX XXX XXXX" />
                    <flux:error name="contact_number" />
                </flux:field>
            </div>
        </div>

        {{-- Address Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Address Information') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('House No.') }}</flux:label>
                    <flux:input wire:model="house_number" placeholder="{{ __('e.g. 123') }}" />
                    <flux:error name="house_number" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Street') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="street" required x-capitalize placeholder="{{ __('e.g. Mabini Street') }}" />
                    <flux:error name="street" />
                </flux:field>

                <x-purok-select wire="purok" required />

                <flux:field>
                    <flux:label>{{ __('Registered in barangay since') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="residency_start_date" type="date" required max="{{ now()->toDateString() }}" />
                    <flux:description>{{ __('Years of residency is computed from this date.') }}</flux:description>
                    <flux:error name="residency_start_date" />
                </flux:field>
            </div>
        </div>

        {{-- Additional Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Additional Information') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Occupation') }}</flux:label>
                    <flux:input wire:model="occupation" />
                    <flux:error name="occupation" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Monthly Income') }}</flux:label>
                    <flux:input wire:model="monthly_income" type="number" min="0" step="0.01" placeholder="₱0.00" />
                    <flux:error name="monthly_income" />
                </flux:field>
            </div>

            <div class="mt-4">
                <flux:checkbox wire:model="is_voter" label="{{ __('Registered Voter') }}" />
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" href="{{ route('residents.index') }}">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Update Resident') }}
            </flux:button>
        </div>
    </form>
</div>

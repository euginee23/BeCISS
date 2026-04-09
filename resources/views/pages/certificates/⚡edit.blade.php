<?php

use App\Models\Certificate;
use App\Models\Resident;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Edit Certificate Request')]
#[Layout('layouts::app')]
class extends Component
{
    public Certificate $certificate;

    public ?int $resident_id = null;

    public string $type = '';

    public string $purpose = '';

    public string $remarks = '';

    public function mount(Certificate $certificate): void
    {
        $this->certificate = $certificate;
        $this->resident_id = $certificate->resident_id;
        $this->type = $certificate->type;
        $this->purpose = $certificate->purpose;
        $this->remarks = $certificate->remarks ?? '';
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
            'type' => ['required', 'in:'.implode(',', array_keys(Certificate::TYPES))],
            'purpose' => ['required', Rule::in(Certificate::PURPOSE_OPTIONS)],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $validated['fee'] = $this->calculateFee($validated['type']);

        $this->certificate->update($validated);

        session()->flash('status', __('Certificate request updated successfully.'));

        $this->redirect(route('certificates.show', $this->certificate), navigate: true);
    }

    protected function calculateFee(string $type): float
    {
        return match ($type) {
            'barangay_clearance' => 50.00,
            'certificate_of_residency' => 30.00,
            'certificate_of_indigency' => 0.00,
            'business_permit' => 200.00,
            'building_permit' => 150.00,
            'cedula' => 50.00,
            default => 50.00,
        };
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
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('certificates.show', $certificate) }}">
            {{ __('Back to Certificate') }}
        </flux:button>
    </div>

    <div class="mb-6">
        <flux:heading size="xl">{{ __('Edit Certificate Request') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ $certificate->certificate_number }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-8">
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Request Details') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
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
                    <flux:select wire:model="purpose" required>
                        <option value="">{{ __('Select purpose') }}</option>
                        @foreach (Certificate::PURPOSE_OPTIONS as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="purpose" />
                </flux:field>

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
                            ₱{{ number_format($this->calculateFee($type), 2) }}
                        </flux:text>
                    </div>
                </div>
            </div>
        @endif

        {{-- Form Actions --}}
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" href="{{ route('certificates.show', $certificate) }}">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Update Request') }}
            </flux:button>
        </div>
    </form>
</div>

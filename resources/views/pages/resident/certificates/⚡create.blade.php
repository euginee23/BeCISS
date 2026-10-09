<?php

use App\Models\Certificate;
use App\Models\CertificatePurpose;
use App\Models\CertificateType;
use App\Services\CertificateWorkflow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Request Certificate')]
#[Layout('layouts::app')]
class extends Component
{
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
        return [
            'type' => ['required', Rule::in($this->types->pluck('slug')->all())],
            'purpose' => ['required', Rule::in(CertificatePurpose::options())],
            'purpose_other' => ['nullable', 'string', 'max:255', 'required_if:purpose,'.CertificatePurpose::OTHER],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function save(CertificateWorkflow $workflow): void
    {
        $this->validate();

        $resident = auth()->user()->resident;

        abort_unless($resident, 403);

        $certificate = Certificate::createWithNumber([
            'resident_id' => $resident->id,
            'type' => $this->type,
            'purpose' => $this->purpose,
            'purpose_other' => $this->purpose === CertificatePurpose::OTHER ? $this->purpose_other : null,
            'remarks' => $this->remarks ?: null,
            'fee' => CertificateType::feeFor($this->type),
        ]);

        $workflow->submitted($certificate);

        session()->flash('status', __('Certificate request submitted successfully.'));

        $this->redirect(route('resident.certificates.index'), navigate: true);
    }

    /**
     * Types residents may request themselves.
     *
     * @return Collection<int, CertificateType>
     */
    #[Computed]
    public function types(): Collection
    {
        return CertificateType::query()
            ->active()
            ->where('available_to_residents', true)
            ->ordered()
            ->get();
    }

    #[Computed]
    public function selectedType(): ?CertificateType
    {
        return $this->types->firstWhere('slug', $this->type);
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('resident.certificates.index') }}">
            {{ __('Back to My Certificates') }}
        </flux:button>
    </div>

    <div class="mb-6">
        <flux:heading size="xl">{{ __('Request Certificate') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Submit a new certificate request to the barangay') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-8">
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Request Details') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Certificate Type') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model.live="type" required>
                        <option value="">{{ __('Select type') }}</option>
                        @foreach ($this->types as $certificateType)
                            <option value="{{ $certificateType->slug }}">{{ $certificateType->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="type" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Purpose') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model.live="purpose" required>
                        <option value="">{{ __('Select purpose') }}</option>
                        @foreach (CertificatePurpose::options() as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="purpose" />
                </flux:field>

                @if ($purpose === CertificatePurpose::OTHER)
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
        @if ($this->selectedType)
            <x-certificate-type-summary :type="$this->selectedType" />
        @endif

        {{-- Form Actions --}}
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" href="{{ route('resident.certificates.index') }}">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Submit Request') }}
            </flux:button>
        </div>
    </form>
</div>

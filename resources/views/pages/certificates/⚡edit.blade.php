<?php

use App\Models\ActivityLog;
use App\Models\Certificate;
use App\Models\CertificatePurpose;
use App\Models\CertificateType;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Collection;
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

    public string $purpose_other = '';

    public string $remarks = '';

    public function mount(Certificate $certificate): void
    {
        abort_unless($certificate->isEditable(), 403);

        $this->certificate = $certificate;

        $this->resident_id = $certificate->resident_id;
        $this->type = $certificate->type;
        $this->purpose = $certificate->purpose;
        $this->purpose_other = $certificate->purpose_other ?? '';
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
            'type' => ['required', Rule::in($this->types->pluck('slug')->all())],
            'purpose' => ['required', Rule::in($this->purposeOptions())],
            'purpose_other' => ['nullable', 'string', 'max:255', 'required_if:purpose,Other'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function save(): void
    {
        abort_unless($this->certificate->fresh()->isEditable(), 403);

        $this->validate();

        $typeChanged = $this->type !== $this->certificate->type;

        $this->certificate->update([
            'resident_id' => $this->resident_id,
            'type' => $this->type,
            'purpose' => $this->purpose,
            'purpose_other' => $this->purpose === CertificatePurpose::OTHER ? $this->purpose_other : null,
            'remarks' => $this->remarks ?: null,
            'fee' => $typeChanged && ! $this->certificate->is_paid
                ? CertificateType::feeFor($this->type)
                : $this->certificate->fee,
        ]);

        ActivityLog::record(
            module: 'certificates',
            action: 'updated',
            subject: $this->certificate,
            description: 'Updated '.$this->certificate->type_label.' request ('.$this->certificate->certificate_number.').',
        );

        session()->flash('status', __('Certificate request updated successfully.'));

        $this->redirect(route('certificates.show', $this->certificate), navigate: true);
    }

    /**
     * Active types, plus the certificate's own type when it has since been retired.
     *
     * @return Collection<int, CertificateType>
     */
    #[Computed]
    public function types(): Collection
    {
        return CertificateType::withTrashed()
            ->where(fn ($query) => $query
                ->where(fn ($active) => $active->active()->whereNull('deleted_at'))
                ->orWhere('slug', $this->certificate->type))
            ->ordered()
            ->get();
    }

    #[Computed]
    public function selectedType(): ?CertificateType
    {
        return $this->types->firstWhere('slug', $this->type);
    }

    /**
     * Active purposes, plus the certificate's own purpose when it has since been retired.
     *
     * @return list<string>
     */
    public function purposeOptions(): array
    {
        $options = CertificatePurpose::options();

        if ($this->certificate->purpose && ! in_array($this->certificate->purpose, $options, true)) {
            array_unshift($options, $this->certificate->purpose);
        }

        return $options;
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
                        @foreach ($this->purposeOptions() as $option)
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
            <flux:button variant="ghost" href="{{ route('certificates.show', $certificate) }}">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Update Request') }}
            </flux:button>
        </div>
    </form>
</div>

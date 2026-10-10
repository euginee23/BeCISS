<?php

use App\Models\Resident;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('View Resident')]
#[Layout('layouts::app')]
class extends Component {
    public Resident $resident;

    public function mount(Resident $resident): void
    {
        $this->resident = $resident->load(['user', 'householdHead', 'householdMembers']);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('residents.index') }}">
            {{ __('Back to Residents') }}
        </flux:button>

        <flux:button variant="primary" icon="pencil" href="{{ route('residents.edit', $resident) }}">
            {{ __('Edit Resident') }}
        </flux:button>
    </div>

    <div class="mb-6 flex items-center gap-4">
        <flux:avatar size="xl" name="{{ $resident->full_name }}" />
        <div>
            <flux:heading size="xl">{{ $resident->full_name }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ collect([
                    $resident->age !== null ? $resident->age.' '.__('years old') : null,
                    $resident->gender ? ucfirst($resident->gender) : null,
                    $resident->civil_status_label,
                ])->filter()->implode(' • ') }}
            </flux:text>
            @if ($resident->sector_labels)
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach ($resident->sector_labels as $sectorLabel)
                        <flux:badge size="sm" color="purple">{{ $sectorLabel }}</flux:badge>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($resident->isIncomplete())
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6">
            <flux:callout.heading>{{ __('Incomplete profile') }}</flux:callout.heading>
            <flux:callout.text>{{ __('This record is missing a birthdate or gender, so it is left out of age and sector reports. Edit the resident to complete it.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Personal Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Personal Information') }}</flux:heading>

            <dl class="space-y-4">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('First Name') }}</dt>
                    <dd class="font-medium">{{ $resident->first_name }}</dd>
                </div>
                @if ($resident->middle_name)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Middle Name') }}</dt>
                        <dd class="font-medium">{{ $resident->middle_name }}</dd>
                    </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Last Name') }}</dt>
                    <dd class="font-medium">{{ $resident->last_name }}</dd>
                </div>
                @if ($resident->suffix)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Suffix') }}</dt>
                        <dd class="font-medium">{{ $resident->suffix }}</dd>
                    </div>
                @endif
                <flux:separator />
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Birthdate') }}</dt>
                    <dd class="font-medium">{{ $resident->birthdate?->format('F j, Y') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Age') }}</dt>
                    <dd class="font-medium">{{ $resident->age !== null ? $resident->age.' '.__('years old') : '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Gender') }}</dt>
                    <dd>
                        @if ($resident->gender)
                            <flux:badge size="sm" :color="$resident->gender === 'male' ? 'blue' : 'pink'">
                                {{ ucfirst($resident->gender) }}
                            </flux:badge>
                        @else
                            <span class="font-medium">—</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Civil Status') }}</dt>
                    <dd class="font-medium">{{ $resident->civil_status_label }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Place of Birth') }}</dt>
                    <dd class="font-medium text-right">{{ $resident->place_of_birth ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Citizenship') }}</dt>
                    <dd class="font-medium">{{ $resident->citizenship ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Religion') }}</dt>
                    <dd class="font-medium">{{ $resident->religion ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Blood Type') }}</dt>
                    <dd class="font-medium">{{ $resident->blood_type ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Contact Number') }}</dt>
                    <dd class="font-medium">{{ $resident->contact_number ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Email') }}</dt>
                    <dd class="font-medium">{{ $resident->user?->email ?? $resident->email ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Address Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Address Information') }}</flux:heading>

            <dl class="space-y-4">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Address') }}</dt>
                    <dd class="font-medium text-right max-w-xs">{{ $resident->address }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Purok') }}</dt>
                    <dd class="font-medium">{{ $resident->purok ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Years of Residency') }}</dt>
                    <dd class="font-medium">
                        @if ($resident->years_of_residency)
                            {{ $resident->years_of_residency }} {{ __('years') }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Additional Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Education & Employment') }}</flux:heading>

            <dl class="space-y-4">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Educational Attainment') }}</dt>
                    <dd class="font-medium text-right">{{ $resident->education_label ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Employment Status') }}</dt>
                    <dd class="font-medium">{{ $resident->employment_label ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Occupation') }}</dt>
                    <dd class="font-medium">{{ $resident->occupation ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Monthly Income') }}</dt>
                    <dd class="font-medium">
                        @if ($resident->monthly_income)
                            ₱{{ number_format($resident->monthly_income, 2) }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Registered Voter') }}</dt>
                    <dd>
                        @if ($resident->is_voter)
                            <flux:badge size="sm" color="emerald">{{ __('Yes') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('No') }}</flux:badge>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Precinct No.') }}</dt>
                    <dd class="font-medium">{{ $resident->precinct_number ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Household --}}
        @if ($resident->householdHead || $resident->householdMembers->isNotEmpty())
            <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-4">{{ __('Household') }}</flux:heading>

                <dl class="space-y-4">
                    @if ($resident->householdHead)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Household Head') }}</dt>
                            <dd class="font-medium">
                                <flux:link href="{{ route('residents.show', $resident->householdHead) }}" wire:navigate>{{ $resident->householdHead->full_name }}</flux:link>
                            </dd>
                        </div>
                    @else
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Role') }}</dt>
                            <dd class="font-medium">{{ __('Household Head') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('Members') }}</dt>
                            <dd class="space-y-1 text-right font-medium">
                                @foreach ($resident->householdMembers as $member)
                                    <div wire:key="member-{{ $member->id }}">
                                        <flux:link href="{{ route('residents.show', $member) }}" wire:navigate>{{ $member->full_name }}</flux:link>
                                    </div>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif

        {{-- System Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('System Information') }}</flux:heading>

            <dl class="space-y-4">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Resident ID') }}</dt>
                    <dd class="font-medium font-mono text-sm">{{ $resident->id }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Online Account') }}</dt>
                    <dd>
                        @if ($resident->user)
                            <flux:badge size="sm" color="blue">{{ __('Yes') }} · {{ ucfirst($resident->status) }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('None (walk-in record)') }}</flux:badge>
                        @endif
                    </dd>
                </div>
                @if ($resident->is_pwd && $resident->pwd_id_number)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('PWD ID No.') }}</dt>
                        <dd class="font-medium">{{ $resident->pwd_id_number }}</dd>
                    </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Created At') }}</dt>
                    <dd class="font-medium">{{ $resident->created_at->format('M j, Y g:i A') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Last Updated') }}</dt>
                    <dd class="font-medium">{{ $resident->updated_at->format('M j, Y g:i A') }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>

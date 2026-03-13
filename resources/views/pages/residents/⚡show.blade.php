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
        $this->resident = $resident;
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
                {{ $resident->age }} {{ __('years old') }} &bull; {{ ucfirst($resident->gender) }} &bull; {{ ucfirst($resident->civil_status) }}
            </flux:text>
        </div>
    </div>

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
                    <dd class="font-medium">{{ $resident->birthdate->format('F j, Y') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Age') }}</dt>
                    <dd class="font-medium">{{ $resident->age }} {{ __('years old') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Gender') }}</dt>
                    <dd>
                        <flux:badge size="sm" :color="$resident->gender === 'male' ? 'blue' : 'pink'">
                            {{ ucfirst($resident->gender) }}
                        </flux:badge>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Civil Status') }}</dt>
                    <dd class="font-medium">{{ ucfirst($resident->civil_status) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Contact Number') }}</dt>
                    <dd class="font-medium">{{ $resident->contact_number ?? '—' }}</dd>
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
            <flux:heading size="lg" class="mb-4">{{ __('Additional Information') }}</flux:heading>

            <dl class="space-y-4">
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
            </dl>
        </div>

        {{-- System Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('System Information') }}</flux:heading>

            <dl class="space-y-4">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Resident ID') }}</dt>
                    <dd class="font-medium font-mono text-sm">{{ $resident->id }}</dd>
                </div>
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

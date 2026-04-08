<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Models\Resident;

new
#[Title('Complete Your Profile')]
#[Layout('layouts::auth-form')]
class extends Component {
    #[Validate('required|string|max:255')]
    public string $first_name = '';

    #[Validate('nullable|string|max:255')]
    public ?string $middle_name = '';

    #[Validate('required|string|max:255')]
    public string $last_name = '';

    #[Validate('nullable|string|max:10')]
    public ?string $suffix = '';

    #[Validate('required|date|before:today')]
    public string $birthdate = '';

    #[Validate('required|in:male,female')]
    public string $gender = '';

    #[Validate('required|in:single,married,widowed,separated,divorced')]
    public string $civil_status = 'single';

    #[Validate('required|string|max:20')]
    public string $contact_number = '';

    #[Validate('required|string|max:500')]
    public string $address = '';

    #[Validate('nullable|string|max:100')]
    public ?string $purok = '';

    public ?string $rejectionReason = null;

    public function mount(): void
    {
        $user = auth()->user();
        $resident = $user->resident;

        if ($resident?->isRejected()) {
            $this->rejectionReason = $resident->rejection_reason;
            $this->first_name = $resident->first_name;
            $this->middle_name = $resident->middle_name ?? '';
            $this->last_name = $resident->last_name;
            $this->suffix = $resident->suffix ?? '';
            $this->birthdate = $resident->birthdate->format('Y-m-d');
            $this->gender = $resident->gender;
            $this->civil_status = $resident->civil_status;
            $this->contact_number = $resident->contact_number ?? '';
            $this->address = $resident->address;
            $this->purok = $resident->purok ?? '';
        }
    }

    public function submitProfile(): void
    {
        $this->validate();

        $user = auth()->user();
        $resident = $user->resident;

        $data = [
            'user_id' => $user->id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name ?: null,
            'last_name' => $this->last_name,
            'suffix' => $this->suffix ?: null,
            'birthdate' => $this->birthdate,
            'gender' => $this->gender,
            'civil_status' => $this->civil_status,
            'contact_number' => $this->contact_number,
            'address' => $this->address,
            'purok' => $this->purok ?: null,
            'status' => 'pending',
            'rejection_reason' => null,
        ];

        if ($resident) {
            $resident->update($data);
        } else {
            Resident::create($data);
        }

        $this->redirect(route('pending-approval'), navigate: true);
    }
}; ?>

<div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xl overflow-hidden">

    {{-- Card Header --}}
    <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-8 py-7 text-white">
        <div class="flex items-center gap-4">
            <div class="flex shrink-0 items-center justify-center size-12 rounded-xl bg-white/20">
                <flux:icon name="user" class="size-6 text-white" />
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight">
                    {{ $this->rejectionReason ? __('Update Your Registration') : __('Complete Your Profile') }}
                </h1>
                <p class="mt-0.5 text-sm text-emerald-100">
                    {{ __('Register as a barangay resident to access services') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Rejection notice --}}
    @if ($this->rejectionReason)
        <div class="mx-8 mt-6 flex items-start gap-3 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3.5">
            <flux:icon name="x-circle" class="size-5 shrink-0 text-red-500 dark:text-red-400 mt-0.5" />
            <div>
                <p class="text-sm font-medium text-red-800 dark:text-red-300">{{ __('Registration was not approved') }}</p>
                <p class="mt-0.5 text-sm text-red-700 dark:text-red-400">{{ $this->rejectionReason }}</p>
            </div>
        </div>
    @endif

    {{-- Form --}}
    <form wire:submit="submitProfile" class="px-8 py-7 space-y-7">

        {{-- Section: Full Name --}}
        <div class="space-y-4">
            <div class="flex items-center gap-2.5">
                <div class="flex items-center justify-center size-7 rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <flux:icon name="identification" class="size-4 text-emerald-600 dark:text-emerald-400" />
                </div>
                <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">{{ __('Full Name') }}</h2>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('First Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="first_name" required autofocus />
                    <flux:error name="first_name" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Last Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="last_name" required />
                    <flux:error name="last_name" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Middle Name') }}</flux:label>
                    <flux:input wire:model="middle_name" placeholder="{{ __('Optional') }}" />
                    <flux:error name="middle_name" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Suffix') }}</flux:label>
                    <flux:input wire:model="suffix" placeholder="{{ __('Jr., Sr., III') }}" />
                    <flux:error name="suffix" />
                </flux:field>
            </div>
        </div>

        <flux:separator />

        {{-- Section: Personal Details --}}
        <div class="space-y-4">
            <div class="flex items-center gap-2.5">
                <div class="flex items-center justify-center size-7 rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <flux:icon name="cake" class="size-4 text-blue-600 dark:text-blue-400" />
                </div>
                <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">{{ __('Personal Details') }}</h2>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Birthdate') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input type="date" wire:model="birthdate" required />
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
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Civil Status') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="civil_status" required>
                        <option value="single">{{ __('Single') }}</option>
                        <option value="married">{{ __('Married') }}</option>
                        <option value="widowed">{{ __('Widowed') }}</option>
                        <option value="separated">{{ __('Separated') }}</option>
                        <option value="divorced">{{ __('Divorced') }}</option>
                    </flux:select>
                    <flux:error name="civil_status" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Contact Number') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="contact_number" type="tel" required placeholder="09XX XXX XXXX" />
                    <flux:error name="contact_number" />
                </flux:field>
            </div>
        </div>

        <flux:separator />

        {{-- Section: Address --}}
        <div class="space-y-4">
            <div class="flex items-center gap-2.5">
                <div class="flex items-center justify-center size-7 rounded-lg bg-amber-100 dark:bg-amber-900/30">
                    <flux:icon name="map-pin" class="size-4 text-amber-600 dark:text-amber-400" />
                </div>
                <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">{{ __('Address & Location') }}</h2>
            </div>

            <flux:field>
                <flux:label>{{ __('Home Address') }} <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="address" required rows="2" placeholder="{{ __('House No., Street, Sitio') }}" />
                <flux:error name="address" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Purok / Zone') }}</flux:label>
                <flux:input wire:model="purok" placeholder="{{ __('Optional') }}" />
                <flux:error name="purok" />
            </flux:field>
        </div>

        {{-- Submit Area --}}
        <div class="pt-2">
            <flux:button
                variant="primary"
                type="submit"
                class="w-full"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="submitProfile">
                    {{ __('Submit for Approval') }}
                </span>
                <span wire:loading wire:target="submitProfile">
                    {{ __('Submitting...') }}
                </span>
            </flux:button>
        </div>
    </form>

    <div class="px-8 pb-7 text-center">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">
                {{ __('Log out') }}
            </button>
        </form>
    </div>
</div>

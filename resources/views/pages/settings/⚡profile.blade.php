<?php

use App\Concerns\ProfileValidationRules;
use App\Models\Resident;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Profile settings')]
#[Layout('layouts::app')]
class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    public string $house_number = '';
    public string $street = '';
    public string $purok = '';
    public string $residency_start_date = '';
    public string $birthdate = '';
    public string $gender = '';
    public string $civil_status = '';
    public string $contact_number = '';
    public string $occupation = '';
    public ?float $monthly_income = null;
    public bool $is_voter = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;

        if ($this->hasResidentProfile) {
            $resident = Auth::user()->resident;
            $this->house_number = $resident->house_number ?? '';
            $this->street = $resident->street ?? '';
            $this->purok = $resident->purok ?? '';
            $this->residency_start_date = $resident->residency_start_date?->format('Y-m-d') ?? '';
            $this->birthdate = $resident->birthdate?->format('Y-m-d') ?? '';
            $this->gender = $resident->gender ?? '';
            $this->civil_status = $resident->civil_status ?? '';
            $this->contact_number = $resident->contact_number ?? '';
            $this->occupation = $resident->occupation ?? '';
            $this->monthly_income = $resident->monthly_income;
            $this->is_voter = $resident->is_voter ?? false;
        }
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Update the resident profile fields.
     */
    public function updateResidentProfile(): void
    {
        $validated = $this->validate([
            'house_number' => ['nullable', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:255'],
            'purok' => ['required', Rule::in(Resident::PUROKS)],
            'residency_start_date' => ['required', 'date', 'before_or_equal:today'],
            'birthdate' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'civil_status' => ['required', Rule::in(['single', 'married', 'widowed', 'separated'])],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
            'is_voter' => ['boolean'],
        ]);

        Auth::user()->resident->update($validated);

        $this->dispatch('resident-profile-updated');
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }

    #[Computed]
    public function hasResidentProfile(): bool
    {
        return Auth::user()->isResident() && Auth::user()->resident !== null;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        @if ($this->hasResidentProfile)
            <section class="mt-10 space-y-6">
                <div class="relative mb-5">
                    <flux:heading>{{ __('Resident profile') }}</flux:heading>
                    <flux:subheading>{{ __('Update your personal information and address') }}</flux:subheading>
                </div>

                <form wire:submit="updateResidentProfile" class="w-full space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
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
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Civil status') }} <span class="text-red-500">*</span></flux:label>
                            <flux:select wire:model="civil_status" required>
                                <option value="">{{ __('Select status') }}</option>
                                <option value="single">{{ __('Single') }}</option>
                                <option value="married">{{ __('Married') }}</option>
                                <option value="widowed">{{ __('Widowed') }}</option>
                                <option value="separated">{{ __('Separated') }}</option>
                            </flux:select>
                            <flux:error name="civil_status" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Contact number') }}</flux:label>
                            <flux:input wire:model="contact_number" type="tel" placeholder="+63 9XX XXX XXXX" />
                            <flux:error name="contact_number" />
                        </flux:field>
                    </div>

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
                    </div>

                    <x-purok-select wire="purok" required />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Occupation') }}</flux:label>
                            <flux:input wire:model="occupation" />
                            <flux:error name="occupation" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Monthly income') }}</flux:label>
                            <flux:input wire:model="monthly_income" type="number" min="0" step="0.01" placeholder="₱0.00" />
                            <flux:error name="monthly_income" />
                        </flux:field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Registered in barangay since') }} <span class="text-red-500">*</span></flux:label>
                            <flux:input wire:model="residency_start_date" type="date" required max="{{ now()->toDateString() }}" />
                            <flux:error name="residency_start_date" />
                        </flux:field>

                        <div class="flex items-end pb-2">
                            <flux:checkbox wire:model="is_voter" label="{{ __('Registered Voter') }}" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="flex items-center justify-end">
                            <flux:button variant="primary" type="submit" class="w-full" data-test="update-resident-profile-button">
                                {{ __('Save') }}
                            </flux:button>
                        </div>

                        <x-action-message class="me-3" on="resident-profile-updated">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>
                </form>
            </section>
        @endif

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
